<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Archivo extends Model
{
    protected $table = 'archivos';

    public $timestamps = true;

    protected $fillable = [
        'nombre_archivo',
        'tipo_archivo',
        'documento_data',
        'expediente_id',
        'onlyoffice_version',
        'onlyoffice_saved_at',
        'onlyoffice_session_open',
        'onlyoffice_session_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'onlyoffice_version' => 'integer',
            'onlyoffice_saved_at' => 'datetime',
            'onlyoffice_session_open' => 'boolean',
            'onlyoffice_session_expires_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'documento_data', // Hide binary data from JSON responses
    ];

    /**
     * Get the expediente that owns the archivo
     */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }
}
