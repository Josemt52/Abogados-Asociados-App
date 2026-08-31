<?php

namespace App\Services;

use App\Models\Archivo;
use App\Models\CargaMasiva;
use App\Models\CargaMasivaItem;
use App\Models\Expediente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class CargaMasivaProcessor
{
    public function __construct(
        private readonly CargaMasivaDocumentService $documents,
        private readonly ExpedienteHeaderParser $parser,
        private readonly ResolutionNumberDetector $resolutionDetector,
        private readonly ExpedienteNumberLock $numberLock,
    ) {}

    public function process(int $itemId): void
    {
        $terminalBatchId = null;
        $item = DB::transaction(function () use ($itemId, &$terminalBatchId): ?CargaMasivaItem {
            $locked = CargaMasivaItem::query()->lockForUpdate()->findOrFail($itemId);

            $isProcessing = $locked->estado === CargaMasivaItem::ESTADO_PROCESANDO;
            $recentlyStarted = $isProcessing && $locked->updated_at?->isAfter(now()->subMinutes(4));

            if ($locked->estaTerminado()) {
                $terminalBatchId = $locked->carga_masiva_id;

                return null;
            }

            if ($recentlyStarted) {
                return null;
            }

            if ((! $isProcessing && $locked->estado !== CargaMasivaItem::ESTADO_EN_COLA)
                || blank($locked->ruta_almacenamiento)) {
                throw new InvalidArgumentException('El documento todavía no está disponible para procesarse.');
            }

            $locked->forceFill([
                'estado' => CargaMasivaItem::ESTADO_PROCESANDO,
                'progreso' => 20,
                'mensaje_error' => null,
            ])->save();

            return $locked->fresh(['carga']);
        });

        if ($item === null) {
            if ($terminalBatchId !== null) {
                CargaMasiva::query()->find($terminalBatchId)?->actualizarContadores();
            }

            return;
        }

        try {
            $binary = $this->readStagedFile($item);
            $extraction = $this->documents->extract($binary, $item->extension);
            $parsed = $this->parser->parse(
                $extraction['text'],
                $extraction['method'],
                $extraction['ocr_confidence']
            );

            $item->forceFill([
                'progreso' => 70,
                'metodo_extraccion' => $extraction['method'],
                'confianza' => $parsed['confidence'],
                'datos_extraidos' => array_merge($parsed['fields'], [
                    'confianza_campos' => $parsed['field_confidence'],
                    'limite_pagina' => $extraction['page_boundary'],
                ]),
            ])->save();

            $this->finish($item->fresh(['carga']), $binary, $extraction['text']);
        } catch (InvalidArgumentException $exception) {
            $this->markPending($itemId, 'documento_ilegible', $exception->getMessage());
        } catch (Throwable $exception) {
            // Una falla posterior al commit (p. ej. al limpiar el temporal o
            // recalcular contadores) nunca debe volver reprocesable un registro
            // que ya creó su expediente y archivo.
            CargaMasivaItem::query()
                ->whereKey($itemId)
                ->whereNotIn('estado', [
                    CargaMasivaItem::ESTADO_REGISTRADO,
                    CargaMasivaItem::ESTADO_PENDIENTE,
                    CargaMasivaItem::ESTADO_REVISION,
                    CargaMasivaItem::ESTADO_ERROR,
                ])
                ->update([
                    'estado' => CargaMasivaItem::ESTADO_EN_COLA,
                    'progreso' => 5,
                    'mensaje_error' => str($exception->getMessage())->limit(2000, ''),
                ]);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $fields */
    public function approve(CargaMasivaItem $item, array $fields): CargaMasivaItem
    {
        $sourceBinary = $this->readItemDocument($item);
        $normalizedFields = $this->normalizeFields($fields);

        if (blank($normalizedFields['numero'])) {
            throw new InvalidArgumentException('El número de expediente es obligatorio para registrar el documento.');
        }

        // La normalización puede renderizar todas las páginas de un PDF. Se
        // ejecuta antes de tomar locks para no bloquear otros registros.
        $document = $this->documents->normalizeForStorage(
            $sourceBinary,
            $item->nombre_original,
            $item->extension
        );
        $detectedResolution = $this->resolutionDetector->detect(
            $document['binary'],
            $document['name'],
            $document['mime']
        );

        $result = DB::transaction(function () use ($item, $document, $normalizedFields, $detectedResolution): CargaMasivaItem {
            $locked = CargaMasivaItem::query()->lockForUpdate()->findOrFail($item->id);
            $previousExpedienteId = $locked->expediente_id;

            if (! in_array($locked->estado, [
                CargaMasivaItem::ESTADO_PENDIENTE,
                CargaMasivaItem::ESTADO_REVISION,
                CargaMasivaItem::ESTADO_ERROR,
            ], true)) {
                throw new InvalidArgumentException('Este documento ya fue resuelto.');
            }

            $normalizedNumber = $this->numberLock->acquire($normalizedFields['numero']);
            $existing = Expediente::query()
                ->where('numero_normalizado', $normalizedNumber)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $archivo = $locked->archivo_id ? Archivo::query()->find($locked->archivo_id) : null;

                if ($archivo === null) {
                    $hasPrimaryDocument = $existing->archivoData()->exists();
                    $archivo = $this->createArchivo($existing, $document, ! $hasPrimaryDocument);

                    if (! $hasPrimaryDocument) {
                        $existing->archivo = true;
                        $existing->nombre_archivo = $document['name'];
                        $existing->ultima_resolucion = null;
                        $existing->resolucion_detectada = $detectedResolution;
                    }
                }

                $this->fillMissingCaseFields($existing, $normalizedFields);

                $locked->forceFill([
                    'expediente_id' => $existing->id,
                    'archivo_id' => $archivo?->id,
                    'estado' => CargaMasivaItem::ESTADO_REGISTRADO,
                    'progreso' => 100,
                    'datos_extraidos' => $normalizedFields,
                    'confianza' => 1,
                    'motivo_revision' => null,
                    'mensaje_error' => null,
                    'es_duplicado' => true,
                    'procesado_at' => now(),
                ])->save();

                $this->refreshReviewFlag($existing, $locked->id);
            } else {
                [$expediente, $archivo] = $this->createCaseAndFile(
                    $locked,
                    $normalizedFields,
                    $document,
                    $detectedResolution
                );
                $locked->forceFill([
                    'expediente_id' => $expediente->id,
                    'archivo_id' => $archivo->id,
                    'estado' => CargaMasivaItem::ESTADO_REGISTRADO,
                    'progreso' => 100,
                    'datos_extraidos' => $normalizedFields,
                    'confianza' => 1,
                    'motivo_revision' => null,
                    'mensaje_error' => null,
                    'es_duplicado' => false,
                    'procesado_at' => now(),
                ])->save();
            }

            if ($previousExpedienteId !== null && $previousExpedienteId !== $locked->expediente_id) {
                $previousExpediente = Expediente::query()->lockForUpdate()->find($previousExpedienteId);
                if ($previousExpediente !== null) {
                    $this->refreshReviewFlag($previousExpediente, $locked->id);
                }
            }

            return $locked->fresh(['carga', 'expediente']);
        });

        $this->deleteStagedFile($result);
        $result->carga->actualizarContadores();

        return $result->fresh(['carga', 'expediente']);
    }

    private function finish(CargaMasivaItem $item, string $binary, string $text): void
    {
        $fields = $this->normalizeFields($item->datos_extraidos ?? []);

        if ($text === '' || blank($fields['numero'])) {
            $this->markPending($item->id, $text === '' ? 'texto_no_detectado' : 'numero_no_detectado');

            return;
        }

        if ((float) $item->confianza < (float) $item->carga->confianza_minima) {
            $this->markPending($item->id, 'confianza_baja');

            return;
        }

        if (! $item->carga->registro_automatico) {
            $this->markForReview($item->id, 'registro_manual_configurado');

            return;
        }

        $detectedResolution = $this->resolutionDetector->detectInText($text);
        $document = $this->documents->normalizeForStorage(
            $binary,
            $item->nombre_original,
            $item->extension
        );

        try {
            $registered = DB::transaction(function () use ($item, $document, $fields, $detectedResolution): CargaMasivaItem {
                $locked = CargaMasivaItem::query()->lockForUpdate()->findOrFail($item->id);
                $normalizedNumber = $this->numberLock->acquire($fields['numero']);
                $existing = Expediente::query()
                    ->where('numero_normalizado', $normalizedNumber)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $existing->forceFill(['requiere_revision' => true])->save();
                    $locked->forceFill([
                        'expediente_id' => $existing->id,
                        'estado' => CargaMasivaItem::ESTADO_REVISION,
                        'progreso' => 100,
                        'motivo_revision' => 'numero_duplicado',
                        'es_duplicado' => true,
                        'procesado_at' => now(),
                    ])->save();

                    return $locked->fresh(['carga']);
                }

                [$expediente, $archivo] = $this->createCaseAndFile(
                    $locked,
                    $fields,
                    $document,
                    $detectedResolution
                );
                $locked->forceFill([
                    'expediente_id' => $expediente->id,
                    'archivo_id' => $archivo->id,
                    'estado' => CargaMasivaItem::ESTADO_REGISTRADO,
                    'progreso' => 100,
                    'motivo_revision' => null,
                    'mensaje_error' => null,
                    'es_duplicado' => false,
                    'procesado_at' => now(),
                ])->save();

                return $locked->fresh(['carga']);
            });

            if ($registered->estado === CargaMasivaItem::ESTADO_REGISTRADO) {
                $this->deleteStagedFile($registered);
            }

            $registered->carga->actualizarContadores();
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $this->markForReview($item->id, 'numero_duplicado', true);
        }
    }

    /** @return array{0: Expediente, 1: Archivo} */
    private function createCaseAndFile(
        CargaMasivaItem $item,
        array $fields,
        array $document,
        ?int $detectedResolution,
    ): array {
        $expediente = Expediente::create([
            'numero' => $fields['numero'],
            'materia' => $fields['materia'],
            'juzgado' => $fields['juzgado'],
            'especialista' => $fields['especialista'],
            'tercero' => $this->joinParties($fields['tercero']),
            'demandado' => $this->joinParties($fields['demandado']),
            'demandante' => $this->joinParties($fields['demandante']),
            'estado' => null,
            'archivo' => true,
            'nombre_archivo' => $document['name'],
            'ultima_resolucion' => null,
            'resolucion_detectada' => $detectedResolution,
            'requiere_revision' => false,
        ]);
        $archivo = $this->createArchivo($expediente, $document, true);

        return [$expediente, $archivo];
    }

    /** @param array{binary: string, name: string, mime: string, extension: string} $document */
    private function createArchivo(Expediente $expediente, array $document, bool $primary): Archivo
    {
        return Archivo::create([
            'expediente_id' => $expediente->id,
            'es_principal' => $primary,
            'origen' => 'carga_masiva',
            'nombre_archivo' => $document['name'],
            'tipo_archivo' => $document['mime'],
            'documento_data' => base64_encode($document['binary']),
        ]);
    }

    /** @param array<string, mixed> $fields */
    private function fillMissingCaseFields(Expediente $expediente, array $fields): void
    {
        foreach (['materia', 'juzgado', 'especialista'] as $field) {
            if (blank($expediente->{$field}) && filled($fields[$field] ?? null)) {
                $expediente->{$field} = $fields[$field];
            }
        }

        foreach (['tercero', 'demandado', 'demandante'] as $field) {
            if (blank($expediente->{$field}) && ($fields[$field] ?? []) !== []) {
                $expediente->{$field} = $this->joinParties($fields[$field]);
            }
        }
    }

    private function refreshReviewFlag(Expediente $expediente, int $resolvedItemId): void
    {
        $expediente->requiere_revision = CargaMasivaItem::query()
            ->where('expediente_id', $expediente->id)
            ->whereKeyNot($resolvedItemId)
            ->where('estado', CargaMasivaItem::ESTADO_REVISION)
            ->where('motivo_revision', 'numero_duplicado')
            ->exists();
        $expediente->save();
    }

    private function markPending(int $itemId, string $reason, ?string $message = null): void
    {
        $item = CargaMasivaItem::query()->findOrFail($itemId);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_PENDIENTE,
            'progreso' => 100,
            'motivo_revision' => $reason,
            'mensaje_error' => $message,
            'procesado_at' => now(),
        ])->save();
        $item->carga->actualizarContadores();
    }

    private function markForReview(int $itemId, string $reason, bool $duplicate = false): void
    {
        $item = CargaMasivaItem::query()->findOrFail($itemId);
        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_REVISION,
            'progreso' => 100,
            'motivo_revision' => $reason,
            'es_duplicado' => $duplicate,
            'procesado_at' => now(),
        ])->save();
        $item->carga->actualizarContadores();
    }

    public function markError(int $itemId, Throwable $exception): void
    {
        $item = CargaMasivaItem::query()->find($itemId);

        if ($item === null || $item->estaTerminado()) {
            return;
        }

        $item->forceFill([
            'estado' => CargaMasivaItem::ESTADO_ERROR,
            'progreso' => 100,
            'motivo_revision' => 'error_tecnico',
            'mensaje_error' => str($exception->getMessage())->limit(2000, ''),
            'procesado_at' => now(),
        ])->save();
        $item->carga->actualizarContadores();
    }

    private function readStagedFile(CargaMasivaItem $item): string
    {
        $path = (string) $item->ruta_almacenamiento;
        $disk = Storage::disk((string) config('carga_masiva.disk', 'local'));

        if ($path === '' || ! $disk->exists($path)) {
            throw new InvalidArgumentException('El archivo original no está disponible en el almacenamiento privado.');
        }

        $binary = $disk->get($path);

        if ($binary === '' || ($item->checksum_sha256 && ! hash_equals($item->checksum_sha256, hash('sha256', $binary)))) {
            throw new InvalidArgumentException('El archivo almacenado está vacío o no pasó la verificación de integridad.');
        }

        return $binary;
    }

    private function readItemDocument(CargaMasivaItem $item): string
    {
        if (filled($item->ruta_almacenamiento)) {
            $disk = Storage::disk((string) config('carga_masiva.disk', 'local'));
            if ($disk->exists((string) $item->ruta_almacenamiento)) {
                return $this->readStagedFile($item);
            }
        }

        $archivo = $item->archivo;
        $binary = $archivo ? base64_decode((string) $archivo->documento_data, true) : false;

        if (! is_string($binary) || $binary === '') {
            throw new InvalidArgumentException('El documento original ya no está disponible.');
        }

        return $binary;
    }

    private function deleteStagedFile(CargaMasivaItem $item): void
    {
        if (blank($item->ruta_almacenamiento)) {
            return;
        }

        $deleted = Storage::disk((string) config('carga_masiva.disk', 'local'))
            ->delete((string) $item->ruta_almacenamiento);

        if ($deleted) {
            $item->forceFill(['ruta_almacenamiento' => null])->save();
        }
    }

    /** @param array<string, mixed> $fields @return array<string, mixed> */
    private function normalizeFields(array $fields): array
    {
        $parties = function (mixed $value): array {
            $values = is_array($value) ? $value : preg_split('/\R|\s*;\s*/u', (string) $value);

            return array_values(array_filter(array_map(
                fn (mixed $party): string => trim(mb_substr((string) $party, 0, 1000)),
                $values ?: []
            )));
        };

        return [
            'numero' => trim(mb_substr((string) ($fields['numero'] ?? ''), 0, 100)),
            'materia' => $this->nullableString($fields['materia'] ?? null, 500),
            'juzgado' => $this->nullableString($fields['juzgado'] ?? null, 255),
            'especialista' => $this->nullableString($fields['especialista'] ?? null, 255),
            'tercero' => $parties($fields['tercero'] ?? []),
            'demandado' => $parties($fields['demandado'] ?? []),
            'demandante' => $parties($fields['demandante'] ?? []),
        ];
    }

    private function nullableString(mixed $value, int $limit): ?string
    {
        $normalized = trim(mb_substr((string) $value, 0, $limit));

        return $normalized === '' ? null : $normalized;
    }

    private function joinParties(array $parties): ?string
    {
        $value = trim(implode("\n", array_filter($parties)));

        return $value === '' ? null : $value;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505', '19'], true)
            || str_contains(mb_strtolower($exception->getMessage()), 'unique');
    }
}
