<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Idempotent - can be run multiple times safely.
     */
    public function run(): void
    {
        // Crear usuario administrador por defecto
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'nombre' => 'Administrador',
                'password' => Hash::make('admin123'),
                'rol_id' => 1, // ADMIN
            ]
        );

        // Crear usuario normal de prueba
        User::updateOrCreate(
            ['username' => 'usuario'],
            [
                'nombre' => 'Usuario Test',
                'password' => Hash::make('usuario123'),
                'rol_id' => 2, // USUARIO
            ]
        );
    }
}
