<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expediente extends Model
{
    protected $table = 'expedientes';

    protected $fillable = [
        'numero',
        'numero_normalizado',
        'materia',
        'juzgado',
        'especialista',
        'tercero',
        'demandado',
        'demandante',
        'estado',
        'requiere_revision',
        'archivo',
        'nombre_archivo',
        'ultima_resolucion',
        'resolucion_detectada',
    ];

    protected $casts = [
        'archivo' => 'boolean',
        'requiere_revision' => 'boolean',
        'ultima_resolucion' => 'integer',
        'resolucion_detectada' => 'integer',
    ];

    protected $hidden = [
        'numero_normalizado',
        'requiere_revision',
    ];

    protected static function booted(): void
    {
        static::saving(function (Expediente $expediente): void {
            if ($expediente->isDirty('numero') || blank($expediente->numero_normalizado)) {
                $expediente->numero_normalizado = self::normalizarNumero((string) $expediente->numero);
            }
        });
    }

    public static function normalizarNumero(string $numero): string
    {
        $normalized = mb_strtoupper(trim($numero), 'UTF-8');

        return preg_replace('/\s+/u', '', $normalized) ?? $normalized;
    }

    /**
     * Get the archivo data associated with this expediente
     */
    public function archivoData(): HasOne
    {
        return $this->hasOne(Archivo::class, 'expediente_id')
            ->where('es_principal', true)
            ->oldestOfMany();
    }

    /**
     * Todos los documentos asociados, incluidos los duplicados en revisión.
     */
    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class, 'expediente_id');
    }

    /**
     * Get the resolution history associated with this expediente.
     */
    public function resoluciones(): HasMany
    {
        return $this->hasMany(Resolucion::class, 'expediente_id');
    }

    /**
     * Scope to filter by estado
     */
    public function scopeByEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }
}
