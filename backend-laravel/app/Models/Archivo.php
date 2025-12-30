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
    ];

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
