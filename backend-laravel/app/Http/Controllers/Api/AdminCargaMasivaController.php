<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCargaMasivaItem;
use App\Models\CargaMasivaItem;
use App\Models\ConfiguracionCargaMasiva;
use App\Services\CargaMasivaProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminCargaMasivaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['nullable', Rule::in([
                CargaMasivaItem::ESTADO_PENDIENTE,
                CargaMasivaItem::ESTADO_REVISION,
                CargaMasivaItem::ESTADO_ERROR,
                CargaMasivaItem::ESTADO_REGISTRADO,
            ])],
            'buscar' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = CargaMasivaItem::query()
            ->with([
                'carga:id,uuid,user_id,created_at',
                'carga.usuario:id,nombre,username',
                'expediente:id,numero',
            ])
            ->when(
                $validated['estado'] ?? null,
                fn ($builder, string $status) => $builder->where('estado', $status),
                fn ($builder) => $builder->whereIn('estado', [
                    CargaMasivaItem::ESTADO_PENDIENTE,
                    CargaMasivaItem::ESTADO_REVISION,
                    CargaMasivaItem::ESTADO_ERROR,
                ])
            )
            ->when($validated['buscar'] ?? null, function ($builder, string $term): void {
                $builder->where(function ($inner) use ($term): void {
                    $inner->where('nombre_original', 'like', '%'.$term.'%')
                        ->orWhere('datos_extraidos->numero', 'like', '%'.$term.'%');
                });
            })
            ->latest('updated_at');

        $paginator = $query->paginate(25);
        $paginator->getCollection()->transform(fn (CargaMasivaItem $item): array => $this->itemPayload($item));

        $summary = CargaMasivaItem::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->whereIn('estado', [
                CargaMasivaItem::ESTADO_PENDIENTE,
                CargaMasivaItem::ESTADO_REVISION,
                CargaMasivaItem::ESTADO_ERROR,
            ])
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return response()->json([
            'resumen' => [
                'pendientes' => (int) ($summary[CargaMasivaItem::ESTADO_PENDIENTE] ?? 0),
                'revision' => (int) ($summary[CargaMasivaItem::ESTADO_REVISION] ?? 0),
                'errores' => (int) ($summary[CargaMasivaItem::ESTADO_ERROR] ?? 0),
            ],
            'items' => $paginator,
        ]);
    }

    public function show(CargaMasivaItem $item): JsonResponse
    {
        return response()->json($this->itemPayload($item->load(['carga.usuario', 'expediente'])));
    }

    public function approve(Request $request, CargaMasivaItem $item, CargaMasivaProcessor $processor): JsonResponse
    {
        $validated = $request->validate([
            'numero' => ['required', 'string', 'max:100'],
            'materia' => ['nullable', 'string', 'max:500'],
            'juzgado' => ['nullable', 'string', 'max:255'],
            'especialista' => ['nullable', 'string', 'max:255'],
            'tercero' => ['nullable', 'array'],
            'tercero.*' => ['string', 'max:1000'],
            'demandado' => ['nullable', 'array'],
            'demandado.*' => ['string', 'max:1000'],
            'demandante' => ['nullable', 'array'],
            'demandante.*' => ['string', 'max:1000'],
        ]);

        try {
            $resolved = $processor->approve($item, $validated);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'numero' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $resolved->es_duplicado
                ? 'El documento quedó asociado al expediente existente.'
                : 'El expediente fue registrado correctamente.',
            'item' => $this->itemPayload($resolved),
        ]);
    }

    public function retry(CargaMasivaItem $item): JsonResponse
    {
        $item = DB::transaction(function () use ($item): CargaMasivaItem {
            $locked = CargaMasivaItem::query()->lockForUpdate()->findOrFail($item->id);

            abort_unless(in_array($locked->estado, [
                CargaMasivaItem::ESTADO_PENDIENTE,
                CargaMasivaItem::ESTADO_ERROR,
            ], true), 409, 'Solo se pueden reprocesar documentos pendientes o con error.');

            abort_if(blank($locked->ruta_almacenamiento), 409, 'El archivo original ya no está disponible para reprocesar.');

            $locked->forceFill([
                'estado' => CargaMasivaItem::ESTADO_EN_COLA,
                'progreso' => 5,
                'metodo_extraccion' => null,
                'confianza' => null,
                'datos_extraidos' => null,
                'motivo_revision' => null,
                'mensaje_error' => null,
                'procesado_at' => null,
            ])->save();

            return $locked->fresh(['carga']);
        });

        try {
            $item->carga->actualizarContadores();
            ProcessCargaMasivaItem::dispatch($item->id)->afterCommit();
        } catch (Throwable $exception) {
            CargaMasivaItem::query()
                ->whereKey($item->id)
                ->where('estado', CargaMasivaItem::ESTADO_EN_COLA)
                ->update([
                    'estado' => CargaMasivaItem::ESTADO_ERROR,
                    'progreso' => 100,
                    'motivo_revision' => 'error_tecnico',
                    'mensaje_error' => str($exception->getMessage())->limit(2000, ''),
                    'procesado_at' => now(),
                ]);
            $item->carga->actualizarContadores();
            Log::error('No se pudo reenviar un documento de carga masiva.', [
                'item_id' => $item->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo reenviar el documento. Sigue disponible en la cola administrativa.',
            ], 500);
        }

        return response()->json(['message' => 'El documento fue enviado nuevamente a procesamiento.'], 202);
    }

    public function download(CargaMasivaItem $item): StreamedResponse|JsonResponse
    {
        $binary = null;

        if (filled($item->ruta_almacenamiento)) {
            $disk = Storage::disk((string) config('carga_masiva.disk', 'local'));
            if ($disk->exists((string) $item->ruta_almacenamiento)) {
                $binary = $disk->get((string) $item->ruta_almacenamiento);
            }
        }

        if (! is_string($binary) && $item->archivo) {
            $decoded = base64_decode((string) $item->archivo->documento_data, true);
            $binary = is_string($decoded) ? $decoded : null;
        }

        if (! is_string($binary) || $binary === '') {
            return response()->json(['message' => 'El documento original no está disponible.'], 404);
        }

        return response()->streamDownload(
            static fn () => print ($binary),
            $this->safeName($item->nombre_original),
            [
                'Content-Type' => $item->tipo_mime ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function configuration(): JsonResponse
    {
        $configuration = ConfiguracionCargaMasiva::actual();

        return response()->json([
            'registro_automatico' => $configuration->registro_automatico,
            'confianza_minima' => $configuration->confianza_minima,
        ]);
    }

    public function updateConfiguration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registro_automatico' => ['required', 'boolean'],
            'confianza_minima' => ['sometimes', 'numeric', 'min:0.5', 'max:0.99'],
        ]);
        $configuration = ConfiguracionCargaMasiva::actual();
        $configuration->fill($validated);
        $configuration->updated_by = auth('api')->id();
        $configuration->save();

        return $this->configuration();
    }

    /** @return array<string, mixed> */
    private function itemPayload(CargaMasivaItem $item): array
    {
        return [
            'id' => $item->id,
            'nombre' => $item->nombre_original,
            'extension' => $item->extension,
            'tamano' => (int) $item->tamano_bytes,
            'estado' => $item->estado,
            'motivo' => $item->motivo_revision,
            'confianza' => $item->confianza,
            'metodo_extraccion' => $item->metodo_extraccion,
            'datos' => $item->datos_extraidos,
            'error' => $item->mensaje_error,
            'es_duplicado' => $item->es_duplicado,
            'expediente' => $item->expediente
                ? ['id' => $item->expediente->id, 'numero' => $item->expediente->numero]
                : null,
            'lote' => $item->carga
                ? [
                    'id' => $item->carga->uuid,
                    'usuario' => $item->carga->usuario?->username,
                ]
                : null,
            'procesado_at' => $item->procesado_at?->toISOString(),
            'created_at' => $item->created_at?->toISOString(),
        ];
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));

        return preg_replace('/[\x00-\x1F\x7F]+/u', '_', $name) ?: 'documento.docx';
    }
}
