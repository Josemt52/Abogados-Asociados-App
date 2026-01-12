<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Idempotent - can be run multiple times safely.
     */
    public function run(): void
    {
        DB::table('roles')->updateOrInsert(
            ['nombre' => 'ADMIN'],
            ['nombre' => 'ADMIN']
        );
        
        DB::table('roles')->updateOrInsert(
            ['nombre' => 'USUARIO'],
            ['nombre' => 'USUARIO']
        );
    }
}
