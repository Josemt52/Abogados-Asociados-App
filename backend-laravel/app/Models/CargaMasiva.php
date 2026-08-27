<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CargaMasiva extends Model
{
    protected $table = 'cargas_masivas';

    protected $fillable = [
        'uuid',
        'user_id',
        'estado',
        'total_archivos',
        'archivos_recibidos',
        'procesados',
        'registrados',
        'pendientes',
        'en_revision',
        'fallidos',
        'registro_automatico',
        'confianza_minima',
        'iniciado_at',
        'completado_at',
    ];

    protected function casts(): array
    {
        return [
            'registro_automatico' => 'boolean',
            'confianza_minima' => 'float',
            'iniciado_at' => 'datetime',
            'completado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CargaMasiva $carga): void {
            $carga->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CargaMasivaItem::class, 'carga_masiva_id');
    }

    public function actualizarContadores(): void
    {
        DB::transaction(function (): void {
            $locked = self::query()->lockForUpdate()->findOrFail($this->id);
            $counts = $locked->items()
                ->selectRaw('estado, COUNT(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado');

            $recibidos = (int) $counts->except([CargaMasivaItem::ESTADO_ESPERANDO_ARCHIVO])->sum();
            $registrados = (int) ($counts[CargaMasivaItem::ESTADO_REGISTRADO] ?? 0);
            $pendientes = (int) ($counts[CargaMasivaItem::ESTADO_PENDIENTE] ?? 0);
            $revision = (int) ($counts[CargaMasivaItem::ESTADO_REVISION] ?? 0);
            $fallidos = (int) ($counts[CargaMasivaItem::ESTADO_ERROR] ?? 0);
            $procesados = $registrados + $pendientes + $revision + $fallidos;
            $completo = $procesados >= $locked->total_archivos;

            $locked->forceFill([
                'archivos_recibidos' => min($recibidos, $locked->total_archivos),
                'procesados' => min($procesados, $locked->total_archivos),
                'registrados' => $registrados,
                'pendientes' => $pendientes,
                'en_revision' => $revision,
                'fallidos' => $fallidos,
                'estado' => $completo ? 'completado' : ($recibidos > 0 ? 'procesando' : 'cargando'),
                'iniciado_at' => $recibidos > 0 ? ($locked->iniciado_at ?? now()) : null,
                'completado_at' => $completo ? ($locked->completado_at ?? now()) : null,
            ])->save();

            $this->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** @return array<string, int|string> */
    public function progresoParaUsuario(): array
    {
        $total = max(1, (int) $this->total_archivos);

        return [
            'id' => $this->uuid,
            'estado' => $this->estado,
            'total' => (int) $this->total_archivos,
            'recibidos' => (int) $this->archivos_recibidos,
            'procesados' => (int) $this->procesados,
            'progreso' => (int) floor(((int) $this->procesados / $total) * 100),
        ];
    }
}
