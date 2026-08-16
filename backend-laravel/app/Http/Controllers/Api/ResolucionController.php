<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Archivo;
use App\Models\Expediente;
use App\Models\Resolucion;
use App\Services\DocumentConversionService;
use App\Services\PdfMergeService;
use App\Services\ResolutionTemplateService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ResolucionController extends Controller
{
    public function index(string $id)
    {
        $expediente = Expediente::findOrFail($id);
        $resoluciones = $expediente->resoluciones()
            ->select([
                'id',
                'expediente_id',
                'numero',
                'estado',
                'es_documento_base',
                'nombre_archivo',
                'tipo_archivo',
                'completada_at',
                'created_at',
                'updated_at',
            ])
            ->orderBy('numero')
            ->get();

        return response()->json([
            'ultima_resolucion' => $expediente->ultima_resolucion,
            'resolucion_detectada' => $expediente->resolucion_detectada,
            'resoluciones' => $resoluciones,
        ]);
    }

    public function confirmarInicial(Request $request, string $id)
    {
        $validated = $request->validate([
            'numero' => ['required', 'integer', 'min:0', 'max:999999'],
        ]);
        $number = (int) $validated['numero'];

        $result = DB::transaction(function () use ($id, $number) {
            $expediente = Expediente::lockForUpdate()->findOrFail($id);

            if ($expediente->ultima_resolucion !== null) {
                throw new HttpResponseException(response()->json([
                    'message' => 'La resolución inicial de este expediente ya fue confirmada.',
                ], 409));
            }

            $archivo = $expediente->archivoData()->first();

            if ($archivo !== null) {
                $conflict = $expediente->resoluciones()
                    ->where('numero', $number)
                    ->where('es_documento_base', false)
                    ->exists();

                if ($conflict) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'El número indicado ya pertenece a otra resolución del expediente.',
                    ], 409));
                }

                $baseResolution = $expediente->resoluciones()
                    ->where('es_documento_base', true)
                    ->first() ?? new Resolucion(['expediente_id' => $expediente->id]);

                $baseResolution->fill([
                    'numero' => $number,
                    'estado' => Resolucion::ESTADO_BASE,
                    'es_documento_base' => true,
                    'nombre_archivo' => $archivo->nombre_archivo,
                    'tipo_archivo' => $archivo->tipo_archivo,
                    'documento_data' => $archivo->documento_data,
                ]);
                $baseResolution->save();
            }

            $expediente->ultima_resolucion = $number;
            $expediente->resolucion_detectada = null;
            $expediente->save();

            return $expediente->fresh();
        });

        return response()->json($result);
    }

    public function descargar(string $id, string $resolucionId)
    {
        $expediente = Expediente::findOrFail($id);
        $resolution = $expediente->resoluciones()->findOrFail($resolucionId);

        if ($resolution->documento_data === null || $resolution->nombre_archivo === null) {
            return response()->json(['message' => 'Esta resolución todavía no tiene un documento asociado.'], 404);
        }

        $binary = base64_decode($resolution->documento_data, true);

        if ($binary === false || $binary === '') {
            return response()->json(['message' => 'El documento almacenado de la resolución no es válido.'], 422);
        }

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            $this->safeDownloadName($resolution->nombre_archivo),
            [
                'Content-Type' => $this->documentMime($resolution->nombre_archivo),
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function siguiente(
        string $id,
        ResolutionTemplateService $templates
    ) {
        [$expediente, $resolution] = DB::transaction(function () use ($id) {
            $expediente = Expediente::lockForUpdate()->findOrFail($id);

            if ($expediente->ultima_resolucion === null) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Primero debes confirmar la última resolución del documento existente.',
                    'resolucion_detectada' => $expediente->resolucion_detectada,
                ], 409));
            }

            $nextNumber = $expediente->ultima_resolucion + 1;
            $resolution = $expediente->resoluciones()
                ->where('estado', Resolucion::ESTADO_PENDIENTE)
                ->orderBy('numero')
                ->first();

            if ($resolution !== null && $resolution->numero !== $nextNumber) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Existe una resolución pendiente que no coincide con la secuencia actual.',
                ], 409));
            }

            $resolution ??= $expediente->resoluciones()->firstOrCreate(
                ['numero' => $nextNumber],
                [
                    'estado' => Resolucion::ESTADO_PENDIENTE,
                    'es_documento_base' => false,
                ]
            );

            if ($resolution->estado !== Resolucion::ESTADO_PENDIENTE) {
                throw new HttpResponseException(response()->json([
                    'message' => 'La siguiente resolución ya fue completada. Actualiza el expediente e inténtalo nuevamente.',
                ], 409));
            }

            return [$expediente, $resolution];
        });

        $path = $templates->generate($expediente, $resolution->numero);

        return response()->download(
            $path,
            $templates->downloadName($expediente, $resolution->numero),
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'X-Resolucion-Id' => (string) $resolution->id,
                'X-Resolucion-Numero' => (string) $resolution->numero,
            ]
        )->deleteFileAfterSend(true);
    }

    public function completar(
        Request $request,
        string $id,
        string $resolucionId,
        DocumentConversionService $converter,
        PdfMergeService $merger
    ) {
        $request->validate([
            'file' => ['required', 'file', 'mimes:doc,docx', 'extensions:doc,docx', 'max:10240'],
        ]);

        $expediente = Expediente::with('archivoData')->findOrFail($id);
        $resolution = $expediente->resoluciones()->findOrFail($resolucionId);

        if ($resolution->estado !== Resolucion::ESTADO_PENDIENTE
            || $expediente->ultima_resolucion === null
            || $resolution->numero !== $expediente->ultima_resolucion + 1) {
            throw new HttpResponseException(response()->json([
                'message' => 'La resolución no está pendiente o no es la siguiente del expediente.',
            ], 409));
        }

        $uploadedFile = $request->file('file');
        $fileName = $uploadedFile->getClientOriginalName();
        $mimeType = $this->normalizeWordMime($fileName);
        $wordBinary = file_get_contents($uploadedFile->getRealPath());

        if ($wordBinary === false || $wordBinary === '') {
            return response()->json(['message' => 'El documento Word está vacío o no se pudo leer.'], 422);
        }

        $storedDocumentFingerprint = $expediente->archivoData === null
            ? null
            : hash('sha256', (string) $expediente->archivoData->documento_data);

        try {
            $newResolutionPdf = $converter->convertToPdfStrict($wordBinary, $fileName, $mimeType);
            $documentsToMerge = [];

            if ($expediente->archivoData !== null) {
                $storedBinary = base64_decode($expediente->archivoData->documento_data, true);

                if ($storedBinary === false || $storedBinary === '') {
                    throw new \RuntimeException('El documento consolidado almacenado no es válido.');
                }

                $documentsToMerge[] = $converter->convertToPdfStrict(
                    $storedBinary,
                    $expediente->archivoData->nombre_archivo,
                    $expediente->archivoData->tipo_archivo
                );
            }

            $documentsToMerge[] = $newResolutionPdf;
            $consolidatedPdf = $merger->merge($documentsToMerge);
        } catch (Throwable $exception) {
            Log::warning('No se pudo consolidar una resolución', [
                'expediente_id' => $expediente->id,
                'resolucion_id' => $resolution->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo convertir o consolidar el documento. La resolución continúa pendiente.',
            ], 422);
        }

        $result = DB::transaction(function () use (
            $id,
            $resolucionId,
            $fileName,
            $mimeType,
            $wordBinary,
            $consolidatedPdf,
            $storedDocumentFingerprint
        ) {
            $lockedExpediente = Expediente::lockForUpdate()->findOrFail($id);
            $lockedResolution = Resolucion::where('expediente_id', $id)
                ->lockForUpdate()
                ->findOrFail($resolucionId);
            $lockedArchivo = Archivo::where('expediente_id', $id)->lockForUpdate()->first();
            $currentFingerprint = $lockedArchivo === null
                ? null
                : hash('sha256', (string) $lockedArchivo->documento_data);

            if ($lockedResolution->estado !== Resolucion::ESTADO_PENDIENTE
                || $lockedExpediente->ultima_resolucion === null
                || $lockedResolution->numero !== $lockedExpediente->ultima_resolucion + 1
                || $currentFingerprint !== $storedDocumentFingerprint) {
                throw new HttpResponseException(response()->json([
                    'message' => 'El expediente cambió durante la consolidación. Vuelve a intentarlo.',
                ], 409));
            }

            $lockedResolution->fill([
                'estado' => Resolucion::ESTADO_COMPLETADA,
                'es_documento_base' => false,
                'nombre_archivo' => $fileName,
                'tipo_archivo' => $mimeType,
                'documento_data' => base64_encode($wordBinary),
                'completada_at' => now(),
            ])->save();

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

        return response()->json($result);
    }

    private function normalizeWordMime(string $fileName): string
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'doc'
            ? 'application/msword'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    private function documentMime(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }

    private function safeDownloadName(string $fileName): string
    {
        $name = basename(str_replace('\\', '/', trim($fileName)));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '_', $name) ?? '';

        return trim($name) !== '' ? $name : 'resolucion.docx';
    }
}
