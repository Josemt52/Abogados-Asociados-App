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
        ?int $expectedEditorVersion = null,
        bool $keepEditorContent = false
    ): array {
        $expediente = Expediente::with('archivoData')->findOrFail($expedienteId);
        $resolution = $expediente->resoluciones()->findOrFail($resolutionId);
        $this->assertPendingNext($expediente, $resolution);
        $this->assertEditorVersion($resolution, $expectedEditorVersion);

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
            $expectedEditorVersion,
            $keepEditorContent
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
            $this->assertEditorVersion($lockedResolution, $expectedEditorVersion);
            $resolutionValues = [
                'estado' => Resolucion::ESTADO_COMPLETADA,
                'es_documento_base' => false,
                'nombre_archivo' => $fileName,
                'tipo_archivo' => $mimeType,
                'documento_data' => base64_encode($wordBinary),
                'version_editor' => (int) $lockedResolution->version_editor + 1,
                'completada_at' => now(),
            ];

            if (! $keepEditorContent) {
                $resolutionValues['contenido_editor'] = null;
                $resolutionValues['contenido_editado_at'] = null;
            }

            $lockedResolution->fill($resolutionValues)->save();

            $safeNumber = Str::slug($lockedExpediente->numero, '_');
            $pdfName = "expediente_{$safeNumber}_resolucion_{$lockedResolution->numero}.pdf";
            $archivo = $lockedArchivo ?? new Archivo(['expediente_id' => $lockedExpediente->id]);
            $archivo->fill([
                'nombre_archivo' => $pdfName,
                'tipo_archivo' => 'application/pdf',
                'documento_data' => base64_encode($consolidatedPdf),
            ])->save();

            $lockedExpediente->fill([
                'archivo' => true,
                'nombre_archivo' => $pdfName,
                'ultima_resolucion' => $lockedResolution->numero,
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

    private function assertEditorVersion(Resolucion $resolution, ?int $expectedVersion): void
    {
        if ($expectedVersion !== null && (int) $resolution->version_editor !== $expectedVersion) {
            throw new HttpResponseException(response()->json([
                'message' => 'La resolución fue modificada en otra ventana. Recarga el editor antes de continuar.',
            ], 409));
        }
    }
}
