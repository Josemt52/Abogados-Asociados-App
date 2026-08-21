<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expediente extends Model
{
    public const MASTER_PDF_READY = 'ready';

    public const MASTER_PDF_PENDING = 'pending';

    public const MASTER_PDF_FAILED = 'failed';

    protected $table = 'expedientes';

    protected $fillable = [
        'numero',
        'materia',
        'juzgado',
        'especialista',
        'tercero',
        'demandado',
        'demandante',
        'estado',
        'archivo',
        'nombre_archivo',
        'ultima_resolucion',
        'resolucion_detectada',
        'master_pdf_rebuild_version',
        'master_pdf_rebuild_status',
        'master_pdf_rebuild_error',
        'master_pdf_rebuild_requested_at',
        'master_pdf_rebuilt_at',
    ];

    protected $casts = [
        'archivo' => 'boolean',
        'ultima_resolucion' => 'integer',
        'resolucion_detectada' => 'integer',
        'master_pdf_rebuild_version' => 'integer',
        'master_pdf_rebuild_requested_at' => 'datetime',
        'master_pdf_rebuilt_at' => 'datetime',
    ];

    /**
     * Get the archivo data associated with this expediente
     */
    public function archivoData(): HasOne
    {
        return $this->hasOne(Archivo::class, 'expediente_id');
    }

    /**
     * Get the resolution history associated with this expediente.
     */
    public function resoluciones(): HasMany
    {
        return $this->hasMany(Resolucion::class, 'expediente_id');
    }

    /**
     * Determine whether ONLYOFFICE may still be saving a source used by the
     * consolidated document. Pending resolutions are intentionally excluded:
     * they do not belong to the master PDF until they are finalized.
     */
    public function hasActiveOnlyOfficeSourceSession(): bool
    {
        $activeLease = static fn ($query) => $query
            ->where('onlyoffice_session_open', true)
            ->where(static fn ($lease) => $lease
                ->whereNull('onlyoffice_session_expires_at')
                ->orWhere('onlyoffice_session_expires_at', '>', now()));

        if ($this->archivoData()->where($activeLease)->exists()) {
            return true;
        }

        return $this->resoluciones()
            ->whereIn('estado', [Resolucion::ESTADO_BASE, Resolucion::ESTADO_COMPLETADA])
            ->where($activeLease)
            ->exists();
    }

    /**
     * Scope to filter by estado
     */
    public function scopeByEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }
}
