<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->unsignedInteger('ultima_resolucion')->nullable()->default(0)->after('nombre_archivo');
            $table->unsignedInteger('resolucion_detectada')->nullable()->after('ultima_resolucion');
        });

        // Existing uploaded documents need an explicit initial confirmation.
        DB::table('expedientes')->where('archivo', true)->update(['ultima_resolucion' => null]);
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn(['ultima_resolucion', 'resolucion_detectada']);
        });
    }
};
