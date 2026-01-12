<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';
    
    public $timestamps = false; // Desactivar timestamps
    
    protected $fillable = [
        'nombre',
    ];

    /**
     * Get the users associated with this role
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'rol_id');
    }
}
