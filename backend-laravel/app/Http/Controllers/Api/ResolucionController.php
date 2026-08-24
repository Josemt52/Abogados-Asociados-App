<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DocumentConversionException;
use App\Http\Controllers\Controller;
use App\Models\Expediente;
use App\Models\Resolucion;
use App\Services\ResolutionCompletionService;
use App\Services\ResolutionRichTextService;
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
                'version_editor',
                'contenido_editado_at',
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
        [$expediente, $resolution, $originalDocumentName] = $this->pendingResolution($id);
        $downloadName = $templates->downloadName(
            $expediente,
            $resolution->numero,
            $originalDocumentName
        );

        if ($resolution->documento_data !== null) {
            $storedDocument = base64_decode($resolution->documento_data, true);

            if (is_string($storedDocument) && str_starts_with($storedDocument, "PK\x03\x04")) {
                return response()->streamDownload(
                    static function () use ($storedDocument): void {
                        echo $storedDocument;
                    },
                    $downloadName,
                    [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'X-Resolucion-Id' => (string) $resolution->id,
                        'X-Resolucion-Numero' => (string) $resolution->numero,
                    ]
                );
            }
        }

        $path = $templates->generate($expediente, $resolution->numero);

        return response()->download(
            $path,
            $downloadName,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'X-Resolucion-Id' => (string) $resolution->id,
                'X-Resolucion-Numero' => (string) $resolution->numero,
            ]
        )->deleteFileAfterSend(true);
    }

    public function iniciarEditor(
        string $id,
        ResolutionRichTextService $richText,
        ResolutionTemplateService $templates
    ) {
        [$expediente, $resolution, $originalDocumentName] = $this->pendingResolution($id);

        if ($resolution->contenido_editor === null) {
            $content = $richText->emptyDocument();
            $fileName = $templates->downloadName(
                $expediente,
                $resolution->numero,
                $originalDocumentName
            );
            $binary = $richText->generateDocx($expediente, $resolution, $content);

            $resolution = DB::transaction(function () use (
                $expediente,
                $resolution,
                $content,
                $fileName,
                $binary
            ): Resolucion {
                $locked = Resolucion::where('expediente_id', $expediente->id)
                    ->lockForUpdate()
                    ->findOrFail($resolution->id);

                if ($locked->estado !== Resolucion::ESTADO_PENDIENTE) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'La resolución ya no está disponible para edición.',
                    ], 409));
                }

                if ($locked->contenido_editor === null) {
                    $locked->fill([
                        'contenido_editor' => $content,
                        'esquema_editor' => ResolutionRichTextService::SCHEMA_VERSION,
                        'nombre_archivo' => $fileName,
                        'tipo_archivo' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'documento_data' => base64_encode($binary),
                    ])->save();
                }

                return $locked->fresh();
            });
        }

        return response()->json($this->editorPayload($expediente, $resolution, $richText));
    }

    public function editor(
        string $id,
        string $resolucionId,
        ResolutionRichTextService $richText
    ) {
        $expediente = Expediente::findOrFail($id);
        $resolution = $expediente->resoluciones()->findOrFail($resolucionId);
        $this->assertPendingNext($expediente, $resolution);

        if ($resolution->contenido_editor === null) {
            return response()->json([
                'message' => 'Esta resolución todavía no fue preparada para el editor.',
            ], 409);
        }

        return response()->json($this->editorPayload($expediente, $resolution, $richText));
    }

    public function guardarEditor(
        Request $request,
        string $id,
        string $resolucionId,
        ResolutionRichTextService $richText,
        ResolutionTemplateService $templates
    ) {
        $validated = $request->validate([
            'content' => ['required', 'array'],
            'version' => ['required', 'integer', 'min:0'],
        ]);
        $content = $richText->normalize($validated['content']);
        $expectedVersion = (int) $validated['version'];
        $expediente = Expediente::findOrFail($id);
        $resolution = $expediente->resoluciones()->findOrFail($resolucionId);
        $this->assertPendingNext($expediente, $resolution);

        if ((int) $resolution->version_editor !== $expectedVersion) {
            throw new HttpResponseException(response()->json([
                'message' => 'La resolución fue modificada en otra ventana. Recarga el editor antes de guardar.',
            ], 409));
        }

        $originalDocumentName = $this->originalDocumentName($expediente);
        $fileName = $templates->downloadName(
            $expediente,
            $resolution->numero,
            $originalDocumentName
        );
        $binary = $richText->generateDocx($expediente, $resolution, $content);

        $resolution = DB::transaction(function () use (
            $id,
            $resolucionId,
            $expectedVersion,
            $content,
            $fileName,
            $binary
        ): Resolucion {
            $lockedExpediente = Expediente::lockForUpdate()->findOrFail($id);
            $lockedResolution = Resolucion::where('expediente_id', $id)
                ->lockForUpdate()
                ->findOrFail($resolucionId);
            $this->assertPendingNext($lockedExpediente, $lockedResolution);

            if ((int) $lockedResolution->version_editor !== $expectedVersion) {
                throw new HttpResponseException(response()->json([
                    'message' => 'La resolución fue modificada en otra ventana. Recarga el editor antes de guardar.',
                ], 409));
            }

            $lockedResolution->fill([
                'contenido_editor' => $content,
                'esquema_editor' => ResolutionRichTextService::SCHEMA_VERSION,
                'version_editor' => $expectedVersion + 1,
                'contenido_editado_at' => now(),
                'nombre_archivo' => $fileName,
                'tipo_archivo' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'documento_data' => base64_encode($binary),
            ])->save();

            return $lockedResolution->fresh();
        });

        return response()->json($this->editorPayload($expediente->fresh(), $resolution, $richText));
    }

    public function finalizarEditor(
        Request $request,
        string $id,
        string $resolucionId,
        ResolutionRichTextService $richText,
        ResolutionTemplateService $templates,
        ResolutionCompletionService $completion
    ) {
        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
        ]);
        $expectedVersion = (int) $validated['version'];
        $expediente = Expediente::findOrFail($id);
        $resolution = $expediente->resoluciones()->findOrFail($resolucionId);
        $this->assertPendingNext($expediente, $resolution);

        if ((int) $resolution->version_editor !== $expectedVersion) {
            throw new HttpResponseException(response()->json([
                'message' => 'La resolución cambió antes de finalizarse. Recarga el editor.',
            ], 409));
        }

        if (! is_array($resolution->contenido_editor)) {
            return response()->json(['message' => 'Primero guarda el contenido de la resolución.'], 422);
        }

        $content = $richText->normalize($resolution->contenido_editor);

        if (! $richText->hasMeaningfulContent($content)) {
            return response()->json(['message' => 'Escribe el contenido de la resolución antes de finalizarla.'], 422);
        }

        $fileName = $templates->downloadName(
            $expediente,
            $resolution->numero,
            $this->originalDocumentName($expediente)
        );
        $binary = $richText->generateDocx($expediente, $resolution, $content);

        try {
            $result = $completion->complete(
                (int) $expediente->id,
                (int) $resolution->id,
                $binary,
                $fileName,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                $expectedVersion,
                true
            );
        } catch (DocumentConversionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
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

        $expediente = Expediente::findOrFail($id);
        $resolution = $expediente->resoluciones()->findOrFail($resolucionId);
        $this->assertPendingNext($expediente, $resolution);

        $uploadedFile = $request->file('file');
        $fileName = $uploadedFile->getClientOriginalName();
        $mimeType = $this->normalizeWordMime($fileName);
        $wordBinary = file_get_contents($uploadedFile->getRealPath());

        if ($wordBinary === false || $wordBinary === '') {
            return response()->json(['message' => 'El documento Word está vacío o no se pudo leer.'], 422);
        }

        try {
            $result = $completion->complete(
                (int) $expediente->id,
                (int) $resolution->id,
                $wordBinary,
                $fileName,
                $mimeType
            );
        } catch (DocumentConversionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }

    /** @return array{0: Expediente, 1: Resolucion, 2: ?string} */
    private function pendingResolution(string $id): array
    {
        return DB::transaction(function () use ($id): array {
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

            return [$expediente, $resolution, $this->originalDocumentName($expediente)];
        });
    }

    private function originalDocumentName(Expediente $expediente): ?string
    {
        return $expediente->resoluciones()
            ->where('es_documento_base', true)
            ->whereNotNull('nombre_archivo')
            ->value('nombre_archivo');
    }

    /** @return array<string, mixed> */
    private function editorPayload(
        Expediente $expediente,
        Resolucion $resolution,
        ResolutionRichTextService $richText
    ): array {
        return [
            'expediente_id' => (int) $expediente->id,
            'resolucion_id' => (int) $resolution->id,
            'numero' => (int) $resolution->numero,
            'estado' => $resolution->estado,
            'document_name' => $resolution->nombre_archivo,
            'header' => $richText->headerFields($expediente),
            'content' => $resolution->contenido_editor ?? $richText->emptyDocument(),
            'version' => (int) $resolution->version_editor,
            'saved_at' => $resolution->contenido_editado_at?->toIso8601String(),
        ];
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
