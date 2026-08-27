<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionCargaMasiva extends Model
{
    protected $table = 'configuraciones_carga_masiva';

    protected $fillable = [
        'registro_automatico',
        'confianza_minima',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'registro_automatico' => 'boolean',
            'confianza_minima' => 'float',
        ];
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function actual(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            ['registro_automatico' => true, 'confianza_minima' => 0.65]
        );
    }
}
