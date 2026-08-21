<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DocumentConversionException;
use App\Http\Controllers\Controller;
use App\Models\Expediente;
use App\Models\Resolucion;
use App\Services\ResolutionCompletionService;
use App\Services\ResolutionTemplateService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'onlyoffice_saved_at',
                'onlyoffice_session_open',
                'onlyoffice_session_expires_at',
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
        [$expediente, $resolution, $originalDocumentName] = DB::transaction(function () use ($id) {
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

            $originalDocumentName = $expediente->resoluciones()
                ->where('es_documento_base', true)
                ->whereNotNull('nombre_archivo')
                ->value('nombre_archivo');

            return [$expediente, $resolution, $originalDocumentName];
        });

        $downloadName = $templates->downloadName(
            $expediente,
            $resolution->numero,
            $originalDocumentName
        );
        $binary = $resolution->documento_data === null
            ? false
            : base64_decode($resolution->documento_data, true);

        if ($binary === false || $binary === '') {
            $path = $templates->generate($expediente, $resolution->numero);

            try {
                $generated = file_get_contents($path);
            } finally {
                @unlink($path);
            }

            if ($generated === false || $generated === '') {
                return response()->json(['message' => 'No se pudo generar la plantilla de resolución.'], 500);
            }

            [$resolution, $binary] = DB::transaction(function () use (
                $id,
                $resolution,
                $generated,
                $downloadName
            ): array {
                $lockedResolution = Resolucion::where('expediente_id', $id)
                    ->lockForUpdate()
                    ->findOrFail($resolution->id);
                $stored = $lockedResolution->documento_data === null
                    ? false
                    : base64_decode($lockedResolution->documento_data, true);

                if ($stored === false || $stored === '') {
                    $hadStoredDocument = $lockedResolution->documento_data !== null;
                    $lockedResolution->fill([
                        'nombre_archivo' => $downloadName,
                        'tipo_archivo' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'documento_data' => base64_encode($generated),
                        'onlyoffice_version' => (int) $lockedResolution->onlyoffice_version
                            + ($hadStoredDocument ? 1 : 0),
                        'onlyoffice_saved_at' => null,
                        'onlyoffice_session_open' => false,
                        'onlyoffice_session_expires_at' => null,
                    ])->save();
                    $stored = $generated;
                }

                return [$lockedResolution, $stored];
            });
        } else {
            $downloadName = $resolution->nombre_archivo ?: $downloadName;
        }

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            $downloadName,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'X-Resolucion-Id' => (string) $resolution->id,
                'X-Resolucion-Numero' => (string) $resolution->numero,
            ]
        );
    }

    public function completar(
        Request $request,
        string $id,
        string $resolucionId,
        ResolutionCompletionService $completion
    ) {
        $request->validate([
            'file' => ['required', 'file', 'mimes:doc,docx', 'extensions:doc,docx', 'max:10240'],
        ]);

        $uploadedFile = $request->file('file');
        $fileName = $uploadedFile->getClientOriginalName();
        $mimeType = $this->normalizeWordMime($fileName);
        $wordBinary = file_get_contents($uploadedFile->getRealPath());

        if ($wordBinary === false || $wordBinary === '') {
            return response()->json(['message' => 'El documento Word está vacío o no se pudo leer.'], 422);
        }

        try {
            $result = $completion->complete(
                (int) $id,
                (int) $resolucionId,
                $wordBinary,
                $fileName,
                $mimeType
            );
        } catch (DocumentConversionException) {
            return response()->json([
                'message' => 'No se pudo convertir o consolidar el documento. La resolución continúa pendiente.',
            ], 422);
        }

        return response()->json($result);
    }

    public function completarOnline(
        string $id,
        string $resolucionId,
        ResolutionCompletionService $completion
    ) {
        $expediente = Expediente::findOrFail($id);
        $resolution = $expediente->resoluciones()->findOrFail($resolucionId);

        if ($resolution->documento_data === null || $resolution->nombre_archivo === null) {
            return response()->json([
                'message' => 'La resolución todavía no tiene un documento guardado para finalizar.',
            ], 409);
        }

        if ($resolution->onlyoffice_saved_at === null) {
            return response()->json([
                'message' => 'Guarda primero la resolución en el editor y espera la confirmación de ONLYOFFICE antes de finalizarla.',
            ], 409);
        }

        if ($resolution->onlyoffice_session_open
            && ($resolution->onlyoffice_session_expires_at === null
                || $resolution->onlyoffice_session_expires_at->isFuture())) {
            return response()->json([
                'message' => 'Cierra primero el editor y espera que ONLYOFFICE confirme el guardado final.',
            ], 409);
        }

        $wordBinary = base64_decode($resolution->documento_data, true);

        if ($wordBinary === false || $wordBinary === '') {
            return response()->json(['message' => 'El documento guardado no es válido.'], 422);
        }

        try {
            $result = $completion->complete(
                (int) $id,
                (int) $resolucionId,
                $wordBinary,
                $resolution->nombre_archivo,
                $this->normalizeWordMime($resolution->nombre_archivo),
                [
                    'version' => (int) $resolution->onlyoffice_version,
                    'document_hash' => hash('sha256', (string) $resolution->documento_data),
                    'saved_at' => $resolution->onlyoffice_saved_at->getTimestamp(),
                ]
            );
        } catch (DocumentConversionException) {
            return response()->json([
                'message' => 'No se pudo convertir o consolidar el documento. La resolución continúa pendiente.',
            ], 422);
        }

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
