<?php

namespace App\Services;

use App\Exceptions\DocumentConversionException;
use App\Models\Archivo;
use App\Models\Expediente;
use App\Models\Resolucion;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ResolutionCompletionService
{
    public function __construct(
        private readonly DocumentConversionService $converter,
        private readonly PdfMergeService $merger
    ) {}

    /** @return array{expediente: Expediente, resolucion: Resolucion} */
    public function complete(
        int $expedienteId,
        int $resolutionId,
        string $wordBinary,
        string $fileName,
        string $mimeType,
        ?array $onlyOfficeSnapshot = null
    ): array {
        $expediente = Expediente::with('archivoData')->findOrFail($expedienteId);
        $resolution = $expediente->resoluciones()->findOrFail($resolutionId);

        $this->assertPendingNext($expediente, $resolution);

        if ($onlyOfficeSnapshot !== null) {
            $this->assertOnlyOfficeSnapshot($resolution, $onlyOfficeSnapshot);
        }

        if ($wordBinary === '') {
            throw new DocumentConversionException('El documento Word está vacío o no se pudo leer.');
        }

        $storedDocumentFingerprint = $expediente->archivoData === null
            ? null
            : hash('sha256', (string) $expediente->archivoData->documento_data);

        try {
            $newResolutionPdf = $expediente->archivoData === null
                ? $this->converter->convertToPdfStrict($wordBinary, $fileName, $mimeType)
                : $this->converter->convertResolutionToPdfStrict($wordBinary, $fileName, $mimeType);
            $documentsToMerge = [];

            if ($expediente->archivoData !== null) {
                $storedBinary = base64_decode($expediente->archivoData->documento_data, true);

                if ($storedBinary === false || $storedBinary === '') {
                    throw new DocumentConversionException('El documento consolidado almacenado no es válido.');
                }

                $documentsToMerge[] = $this->converter->convertToPdfStrict(
                    $storedBinary,
                    $expediente->archivoData->nombre_archivo,
                    $expediente->archivoData->tipo_archivo
                );
            }

            $documentsToMerge[] = $newResolutionPdf;
            $consolidatedPdf = $this->merger->merge($documentsToMerge);
        } catch (Throwable $exception) {
            Log::warning('No se pudo consolidar una resolución', [
                'expediente_id' => $expediente->id,
                'resolucion_id' => $resolution->id,
                'error' => $exception->getMessage(),
            ]);

            throw new DocumentConversionException(
                'No se pudo convertir o consolidar el documento. La resolución continúa pendiente.',
                0,
                $exception
            );
        }

        return DB::transaction(function () use (
            $expedienteId,
            $resolutionId,
            $fileName,
            $mimeType,
            $wordBinary,
            $consolidatedPdf,
            $storedDocumentFingerprint,
            $onlyOfficeSnapshot
        ): array {
            $lockedExpediente = Expediente::lockForUpdate()->findOrFail($expedienteId);
            $lockedResolution = Resolucion::where('expediente_id', $expedienteId)
                ->lockForUpdate()
                ->findOrFail($resolutionId);
            $lockedArchivo = Archivo::where('expediente_id', $expedienteId)->lockForUpdate()->first();
            $currentFingerprint = $lockedArchivo === null
                ? null
                : hash('sha256', (string) $lockedArchivo->documento_data);

            if ($currentFingerprint !== $storedDocumentFingerprint) {
                throw new HttpResponseException(response()->json([
                    'message' => 'El expediente cambió durante la consolidación. Vuelve a intentarlo.',
                ], 409));
            }

            $this->assertPendingNext($lockedExpediente, $lockedResolution);

            if ($onlyOfficeSnapshot !== null) {
                $this->assertOnlyOfficeSnapshot($lockedResolution, $onlyOfficeSnapshot);
            }

            $newEncodedDocument = base64_encode($wordBinary);
            $lockedResolution->fill([
                'estado' => Resolucion::ESTADO_COMPLETADA,
                'es_documento_base' => false,
                'nombre_archivo' => $fileName,
                'tipo_archivo' => $mimeType,
                'documento_data' => $newEncodedDocument,
                // Completing changes the document lifecycle even when the
                // bytes are identical; invalidate all pending editor URLs.
                'onlyoffice_version' => (int) $lockedResolution->onlyoffice_version + 1,
                'onlyoffice_session_open' => false,
                'onlyoffice_session_expires_at' => null,
                'completada_at' => now(),
            ])->save();

            $safeNumber = Str::slug($lockedExpediente->numero, '_');
            $pdfName = "expediente_{$safeNumber}_resolucion_{$lockedResolution->numero}.pdf";
            $archivo = $lockedArchivo ?? new Archivo(['expediente_id' => $lockedExpediente->id]);
            $archivo->fill([
                'nombre_archivo' => $pdfName,
                'tipo_archivo' => 'application/pdf',
                'documento_data' => base64_encode($consolidatedPdf),
                'onlyoffice_version' => (int) ($archivo->onlyoffice_version ?? 0) + 1,
                'onlyoffice_saved_at' => null,
                'onlyoffice_session_open' => false,
                'onlyoffice_session_expires_at' => null,
            ])->save();

            $lockedExpediente->fill([
                'archivo' => true,
                'nombre_archivo' => $pdfName,
                'ultima_resolucion' => $lockedResolution->numero,
                // Invalidate any older after-response rebuild and publish this
                // synchronously consolidated PDF as the current master.
                'master_pdf_rebuild_version' => (int) $lockedExpediente->master_pdf_rebuild_version + 1,
                'master_pdf_rebuild_status' => Expediente::MASTER_PDF_READY,
                'master_pdf_rebuild_error' => null,
                'master_pdf_rebuilt_at' => now(),
            ])->save();

            return [
                'expediente' => $lockedExpediente->fresh(),
                'resolucion' => $lockedResolution->fresh(),
            ];
        });
    }

    private function assertPendingNext(Expediente $expediente, Resolucion $resolution): void
    {
        if ($resolution->estado !== Resolucion::ESTADO_PENDIENTE
            || $expediente->ultima_resolucion === null
            || $resolution->numero !== $expediente->ultima_resolucion + 1) {
            throw new HttpResponseException(response()->json([
                'message' => 'La resolución no está pendiente o no es la siguiente del expediente.',
            ], 409));
        }
    }

    /**
     * @param  array{version: int, document_hash: string, saved_at: int}  $snapshot
     */
    private function assertOnlyOfficeSnapshot(Resolucion $resolution, array $snapshot): void
    {
        $savedAt = $resolution->onlyoffice_saved_at?->getTimestamp();
        $sessionActive = $resolution->onlyoffice_session_open
            && ($resolution->onlyoffice_session_expires_at === null
                || $resolution->onlyoffice_session_expires_at->isFuture());
        $matches = (int) $resolution->onlyoffice_version === (int) $snapshot['version']
            && hash_equals(
                (string) $snapshot['document_hash'],
                hash('sha256', (string) $resolution->documento_data)
            )
            && $savedAt === (int) $snapshot['saved_at']
            && ! $sessionActive;

        if (! $matches) {
            throw new HttpResponseException(response()->json([
                'message' => 'La resolución cambió o volvió a abrirse durante la consolidación. Vuelve a intentarlo.',
            ], 409));
        }
    }
}
