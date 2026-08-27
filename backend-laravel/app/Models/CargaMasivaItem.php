<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargaMasivaItem extends Model
{
    public const ESTADO_ESPERANDO_ARCHIVO = 'esperando_archivo';

    public const ESTADO_EN_COLA = 'en_cola';

    public const ESTADO_PROCESANDO = 'procesando';

    public const ESTADO_REGISTRADO = 'registrado';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_REVISION = 'revision';

    public const ESTADO_ERROR = 'error';

    protected $table = 'carga_masiva_items';

    protected $fillable = [
        'carga_masiva_id',
        'expediente_id',
        'archivo_id',
        'nombre_original',
        'extension',
        'tipo_mime',
        'ruta_almacenamiento',
        'tamano_bytes',
        'checksum_sha256',
        'estado',
        'progreso',
        'metodo_extraccion',
        'confianza',
        'datos_extraidos',
        'motivo_revision',
        'mensaje_error',
        'es_duplicado',
        'procesado_at',
    ];

    protected $hidden = [
        'ruta_almacenamiento',
        'mensaje_error',
    ];

    protected function casts(): array
    {
        return [
            'datos_extraidos' => 'array',
            'confianza' => 'float',
            'es_duplicado' => 'boolean',
            'procesado_at' => 'datetime',
        ];
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaMasiva::class, 'carga_masiva_id');
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(Archivo::class);
    }

    public function estaTerminado(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_REGISTRADO,
            self::ESTADO_PENDIENTE,
            self::ESTADO_REVISION,
            self::ESTADO_ERROR,
        ], true);
    }
}
