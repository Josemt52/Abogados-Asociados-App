<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expediente extends Model
{
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
    ];

    protected $casts = [
        'archivo' => 'boolean',
    ];

    /**
     * Get the archivo data associated with this expediente
     */
    public function archivoData(): HasOne
    {
        return $this->hasOne(Archivo::class, 'expediente_id');
    }

    /**
     * Scope to filter by estado
     */
    public function scopeByEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }
}
