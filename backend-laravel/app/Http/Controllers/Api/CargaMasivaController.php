<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCargaMasivaItem;
use App\Models\CargaMasiva;
use App\Models\CargaMasivaItem;
use App\Models\ConfiguracionCargaMasiva;
use App\Services\WordFirstPageExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CargaMasivaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $maxFiles = (int) config('carga_masiva.max_archivos', 50);
        $maxBytes = (int) config('carga_masiva.max_kilobytes_por_archivo', 10240) * 1024;
        $validated = $request->validate([
            'archivos' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'archivos.*.nombre' => ['required', 'string', 'max:255'],
            'archivos.*.tamano' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
        ]);

        $files = collect($validated['archivos'])->map(function (array $file, int $index): array {
            $name = $this->safeName($file['nombre']);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, ['doc', 'docx'], true)) {
                throw ValidationException::withMessages([
                    "archivos.$index.nombre" => 'Solo se permiten documentos .doc o .docx.',
                ]);
            }

            return [
                'nombre_original' => $name,
                'extension' => $extension,
                'tamano_bytes' => (int) $file['tamano'],
            ];
        });

        $configuration = ConfiguracionCargaMasiva::actual();
        $batch = DB::transaction(function () use ($files, $configuration): CargaMasiva {
            $batch = CargaMasiva::create([
                'user_id' => auth('api')->id(),
                'estado' => 'cargando',
                'total_archivos' => $files->count(),
                'registro_automatico' => $configuration->registro_automatico,
                'confianza_minima' => $configuration->confianza_minima,
            ]);

            foreach ($files as $file) {
                $batch->items()->create(array_merge($file, [
                    'estado' => CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO,
                    'progreso' => 0,
                ]));
            }

            return $batch->load('items:id,carga_masiva_id,nombre_original');
        });

        return response()->json(array_merge($batch->progresoParaUsuario(), [
            'cargas' => $batch->items->map(fn (CargaMasivaItem $item): array => [
                'id' => $item->id,
                'nombre' => $item->nombre_original,
            ])->values(),
        ]), 201);
    }

    public function upload(
        Request $request,
        CargaMasiva $carga,
        CargaMasivaItem $item,
        WordFirstPageExtractor $extractor,
    ): JsonResponse {
        $this->authorizeOwner($carga);
        $this->assertItemBelongsToBatch($carga, $item);

        $maxKilobytes = (int) config('carga_masiva.max_kilobytes_por_archivo', 10240);
        $request->validate([
            'archivo' => ['required', 'file', 'extensions:doc,docx', 'max:'.$maxKilobytes],
        ]);

        $uploaded = $request->file('archivo');
        $name = $this->safeName($uploaded->getClientOriginalName());
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = (int) $uploaded->getSize();

        if ($name !== $item->nombre_original || $extension !== $item->extension || $size !== (int) $item->tamano_bytes) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no coincide con el documento reservado en este lote.',
            ]);
        }

        $binary = file_get_contents($uploaded->getRealPath());
        if (! is_string($binary) || $binary === '') {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el documento.']);
        }

        $checksum = hash('sha256', $binary);
        $currentItem = $item->fresh();
        if ($currentItem->estado !== CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO) {
            if ($currentItem->checksum_sha256 && hash_equals($currentItem->checksum_sha256, $checksum)) {
                return response()->json($carga->fresh()->progresoParaUsuario(), 202);
            }

            throw ValidationException::withMessages([
                'archivo' => 'Este documento ya fue recibido con un contenido diferente.',
            ]);
        }

        try {
            $extractor->assertValid($binary, $extension);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['archivo' => $exception->getMessage()]);
        }

        $path = 'cargas-masivas/'.$carga->uuid.'/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk((string) config('carga_masiva.disk', 'local'));

        if (! $disk->put($path, $binary)) {
            return response()->json(['message' => 'No se pudo guardar el documento para procesarlo.'], 500);
        }

        $alreadyReceived = false;
        try {
            DB::transaction(function () use ($item, $path, $binary, $checksum, $extension, &$alreadyReceived): void {
                $locked = CargaMasivaItem::query()->lockForUpdate()->findOrFail($item->id);

                if ($locked->estado !== CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO) {
                    if ($locked->checksum_sha256 && hash_equals($locked->checksum_sha256, $checksum)) {
                        $alreadyReceived = true;

                        return;
                    }

                    throw ValidationException::withMessages([
                        'archivo' => 'Este documento ya fue recibido con un contenido diferente.',
                    ]);
                }

                $locked->forceFill([
                    'ruta_almacenamiento' => $path,
                    'tipo_mime' => $extension === 'doc'
                        ? 'application/msword'
                        : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'tamano_bytes' => strlen($binary),
                    'checksum_sha256' => $checksum,
                    'estado' => CargaMasivaItem::ESTADO_EN_COLA,
                    'progreso' => 5,
                ])->save();
            });
        } catch (\Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        if ($alreadyReceived) {
            $disk->delete($path);

            return response()->json($carga->fresh()->progresoParaUsuario(), 202);
        }

        $carga->actualizarContadores();
        try {
            ProcessCargaMasivaItem::dispatch($item->id)->afterCommit();
        } catch (\Throwable $exception) {
            CargaMasivaItem::query()
                ->whereKey($item->id)
                ->whereIn('estado', [
                    CargaMasivaItem::ESTADO_EN_COLA,
                    CargaMasivaItem::ESTADO_PROCESANDO,
                ])
                ->update([
                    'estado' => CargaMasivaItem::ESTADO_ERROR,
                    'progreso' => 100,
                    'motivo_revision' => 'error_tecnico',
                    'mensaje_error' => str($exception->getMessage())->limit(2000, ''),
                    'procesado_at' => now(),
                ]);
            $carga->actualizarContadores();
            Log::error('No se pudo encolar un documento de carga masiva.', [
                'item_id' => $item->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json($carga->fresh()->progresoParaUsuario(), 202);
    }

    public function show(CargaMasiva $carga): JsonResponse
    {
        $this->authorizeOwner($carga);

        return response()->json($carga->fresh()->progresoParaUsuario());
    }

    private function authorizeOwner(CargaMasiva $carga): void
    {
        abort_unless((int) $carga->user_id === (int) auth('api')->id(), 404);
    }

    private function assertItemBelongsToBatch(CargaMasiva $carga, CargaMasivaItem $item): void
    {
        abort_unless((int) $item->carga_masiva_id === (int) $carga->id, 404);
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '_', $name) ?? '';

        return $name !== '' ? $name : 'documento.docx';
    }
}
