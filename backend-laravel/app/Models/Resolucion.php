<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resolucion extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_BASE = 'base';

    protected $table = 'resoluciones';

    protected $fillable = [
        'expediente_id',
        'numero',
        'estado',
        'es_documento_base',
        'nombre_archivo',
        'tipo_archivo',
        'documento_data',
        'completada_at',
    ];

    protected $hidden = [
        'documento_data',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'es_documento_base' => 'boolean',
            'completada_at' => 'datetime',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }
}
