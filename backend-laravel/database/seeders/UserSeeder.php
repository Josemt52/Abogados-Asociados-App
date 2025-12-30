<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuario administrador por defecto
        User::create([
            'nombre' => 'Administrador',
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'rol_id' => 1, // ADMIN
        ]);

        // Crear usuario normal de prueba
        User::create([
            'nombre' => 'Usuario Test',
            'username' => 'usuario',
            'password' => Hash::make('usuario123'),
            'rol_id' => 2, // USUARIO
        ]);
    }
}
