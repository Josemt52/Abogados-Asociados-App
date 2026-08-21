<?php

namespace App\Services;

use App\Exceptions\OnlyOfficeException;
use App\Models\Archivo;
use App\Models\Expediente;
use App\Models\Resolucion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpedienteMasterDocumentService
{
    public function __construct(
        private readonly DocumentConversionService $converter,
        private readonly PdfMergeService $merger
    ) {}

    /** @return array{pdf: string, name: string, snapshot: string} */
    public function prepareCurrent(int $expedienteId): array
    {
        $expediente = Expediente::with([
            'archivoData',
            'resoluciones' => fn ($query) => $query
                ->where(function ($states): void {
                    $states->where('es_documento_base', true)
                        ->orWhere('estado', Resolucion::ESTADO_COMPLETADA);
                })
                ->orderBy('numero'),
        ])->findOrFail($expedienteId);

        /** @var Collection<int, Resolucion> $resoluciones */
        $resoluciones = $expediente->resoluciones;
        $base = $resoluciones->firstWhere('es_documento_base', true);
        $foundation = $base ?? $resoluciones
            ->where('estado', Resolucion::ESTADO_COMPLETADA)
            ->sortBy('numero')
            ->first();

        if ($foundation === null) {
            throw new OnlyOfficeException(
                'No se puede reconstruir de forma segura el PDF maestro porque no existe un documento inicial editable.',
                409
            );
        }

        $documents = [];

        foreach ($resoluciones as $resolution) {
            $binary = $this->decodeResolution($resolution);
            $name = (string) $resolution->nombre_archivo;
            $type = (string) $resolution->tipo_archivo;

            $documents[] = $resolution->id === $foundation->id
                ? $this->converter->convertToPdfStrict($binary, $name, $type)
                : $this->converter->convertResolutionToPdfStrict($binary, $name, $type);
        }

        if ($documents === []) {
            throw new OnlyOfficeException('No hay documentos para reconstruir el expediente.', 409);
        }

        $lastNumber = max(
            (int) ($expediente->ultima_resolucion ?? 0),
            (int) ($resoluciones->where('estado', Resolucion::ESTADO_COMPLETADA)->max('numero') ?? 0)
        );
        $safeNumber = Str::slug($expediente->numero, '_');

        return [
            'pdf' => $this->merger->merge($documents),
            'name' => "expediente_{$safeNumber}_resolucion_{$lastNumber}.pdf",
            'snapshot' => $this->snapshotFromModels(
                $expediente,
                $expediente->archivoData,
                $resoluciones
            ),
        ];
    }

    /**
     * Publish a prepared PDF only while it still represents the newest save.
     *
     * @param  array{pdf: string, name: string, snapshot: string}  $prepared
     */
    public function publishPreparedIfCurrent(
        int $expedienteId,
        int $rebuildVersion,
        array $prepared
    ): bool {
        return DB::transaction(function () use ($expedienteId, $rebuildVersion, $prepared): bool {
            $expediente = Expediente::lockForUpdate()->find($expedienteId);

            if ($expediente === null
                || (int) $expediente->master_pdf_rebuild_version !== $rebuildVersion
                || $expediente->master_pdf_rebuild_status !== Expediente::MASTER_PDF_PENDING) {
                return false;
            }

            $archivo = Archivo::where('expediente_id', $expedienteId)->lockForUpdate()->first();
            $resoluciones = Resolucion::where('expediente_id', $expedienteId)
                ->where(function ($states): void {
                    $states->where('es_documento_base', true)
                        ->orWhere('estado', Resolucion::ESTADO_COMPLETADA);
                })
                ->lockForUpdate()
                ->get();
            $currentSnapshot = $this->snapshotFromModels($expediente, $archivo, $resoluciones);

            if (! hash_equals($prepared['snapshot'], $currentSnapshot)) {
                throw new OnlyOfficeException(
                    'Las fuentes del expediente cambiaron durante la reconstrucción del PDF maestro.',
                    409
                );
            }

            $archivo ??= new Archivo(['expediente_id' => $expediente->id]);
            $archivo->fill([
                'nombre_archivo' => $prepared['name'],
                'tipo_archivo' => 'application/pdf',
                'documento_data' => base64_encode($prepared['pdf']),
                'onlyoffice_version' => (int) ($archivo->onlyoffice_version ?? 0) + 1,
                'onlyoffice_saved_at' => null,
                'onlyoffice_session_open' => false,
                'onlyoffice_session_expires_at' => null,
            ])->save();
            $expediente->fill([
                'archivo' => true,
                'nombre_archivo' => $prepared['name'],
                'master_pdf_rebuild_status' => Expediente::MASTER_PDF_READY,
                'master_pdf_rebuild_error' => null,
                'master_pdf_rebuilt_at' => now(),
            ])->save();

            return true;
        });
    }

    public function isCurrentRequest(int $expedienteId, int $rebuildVersion): bool
    {
        return Expediente::query()
            ->whereKey($expedienteId)
            ->where('master_pdf_rebuild_version', $rebuildVersion)
            ->where('master_pdf_rebuild_status', Expediente::MASTER_PDF_PENDING)
            ->exists();
    }

    public function markFailedIfCurrent(int $expedienteId, int $rebuildVersion): void
    {
        DB::transaction(function () use ($expedienteId, $rebuildVersion): void {
            $expediente = Expediente::lockForUpdate()->find($expedienteId);

            if ($expediente === null
                || (int) $expediente->master_pdf_rebuild_version !== $rebuildVersion
                || $expediente->master_pdf_rebuild_status !== Expediente::MASTER_PDF_PENDING) {
                return;
            }

            $expediente->fill([
                'master_pdf_rebuild_status' => Expediente::MASTER_PDF_FAILED,
                'master_pdf_rebuild_error' => 'No se pudo actualizar el PDF maestro. Puede reintentar la actualización.',
                'master_pdf_rebuilt_at' => null,
            ])->save();
        });
    }

    /** @return array{expediente_id: int, version: int, status: string, requested_at: string} */
    public function retryFailed(int $expedienteId): array
    {
        return DB::transaction(function () use ($expedienteId): array {
            $expediente = Expediente::lockForUpdate()->findOrFail($expedienteId);

            $staleBefore = now()->subMinutes(
                max(1, (int) config('onlyoffice.master_rebuild_stale_minutes', 10))
            );
            $pendingIsRecent = $expediente->master_pdf_rebuild_requested_at !== null
                && $expediente->master_pdf_rebuild_requested_at->greaterThan($staleBefore);

            if ($expediente->master_pdf_rebuild_status === Expediente::MASTER_PDF_PENDING
                && $pendingIsRecent) {
                throw new OnlyOfficeException(
                    'La actualización del PDF maestro todavía está en proceso.',
                    409
                );
            }

            if (! in_array(
                $expediente->master_pdf_rebuild_status,
                [Expediente::MASTER_PDF_FAILED, Expediente::MASTER_PDF_PENDING],
                true
            )) {
                throw new OnlyOfficeException(
                    'El PDF maestro no tiene una reconstrucción fallida para reintentar.',
                    409
                );
            }

            $version = (int) $expediente->master_pdf_rebuild_version + 1;
            $requestedAt = now();
            $expediente->fill([
                'master_pdf_rebuild_version' => $version,
                'master_pdf_rebuild_status' => Expediente::MASTER_PDF_PENDING,
                'master_pdf_rebuild_error' => null,
                'master_pdf_rebuild_requested_at' => $requestedAt,
                'master_pdf_rebuilt_at' => null,
            ])->save();

            return [
                'expediente_id' => (int) $expediente->id,
                'version' => $version,
                'status' => Expediente::MASTER_PDF_PENDING,
                'requested_at' => $requestedAt->toISOString(),
            ];
        });
    }

    /** @param Collection<int, Resolucion> $resoluciones */
    public function snapshotFromModels(
        Expediente $expediente,
        ?Archivo $archivo,
        Collection $resoluciones
    ): string {
        $state = [
            'expediente' => [
                'id' => $expediente->id,
                'ultima_resolucion' => $expediente->ultima_resolucion,
            ],
            'archivo' => $archivo === null ? null : [
                'id' => $archivo->id,
                'nombre' => $archivo->nombre_archivo,
                'tipo' => $archivo->tipo_archivo,
                'documento' => hash('sha256', (string) $archivo->documento_data),
                'version' => $archivo->onlyoffice_version,
            ],
            'resoluciones' => $resoluciones
                ->sortBy('id')
                ->map(static fn (Resolucion $resolution): array => [
                    'id' => $resolution->id,
                    'numero' => $resolution->numero,
                    'estado' => $resolution->estado,
                    'base' => $resolution->es_documento_base,
                    'nombre' => $resolution->nombre_archivo,
                    'tipo' => $resolution->tipo_archivo,
                    'documento' => hash('sha256', (string) $resolution->documento_data),
                    'version' => $resolution->onlyoffice_version,
                ])
                ->values()
                ->all(),
        ];

        return hash('sha256', serialize($state));
    }

    private function decodeResolution(Resolucion $resolution): string
    {
        if ($resolution->documento_data === null || $resolution->nombre_archivo === null) {
            throw new OnlyOfficeException(
                "La resolución {$resolution->numero} no tiene un documento Word para reconstruir el expediente.",
                409
            );
        }

        $extension = strtolower(pathinfo($resolution->nombre_archivo, PATHINFO_EXTENSION));

        $supported = in_array($extension, ['doc', 'docx'], true)
            || ($resolution->es_documento_base && $extension === 'pdf');

        if (! $supported) {
            throw new OnlyOfficeException(
                "La resolución {$resolution->numero} no conserva una fuente apta para reconstruir el expediente.",
                409
            );
        }

        $binary = base64_decode($resolution->documento_data, true);

        if ($binary === false || $binary === '') {
            throw new OnlyOfficeException(
                "El documento de la resolución {$resolution->numero} no es válido.",
                422
            );
        }

        return $binary;
    }
}
