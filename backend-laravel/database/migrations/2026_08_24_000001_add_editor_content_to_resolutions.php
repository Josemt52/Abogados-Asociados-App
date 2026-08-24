<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resoluciones', function (Blueprint $table): void {
            $table->longText('contenido_editor')->nullable()->after('documento_data');
            $table->unsignedSmallInteger('esquema_editor')->default(1)->after('contenido_editor');
            $table->unsignedBigInteger('version_editor')->default(0)->after('esquema_editor');
            $table->timestamp('contenido_editado_at')->nullable()->after('version_editor');
        });
    }

    public function down(): void
    {
        Schema::table('resoluciones', function (Blueprint $table): void {
            $table->dropColumn([
                'contenido_editor',
                'esquema_editor',
                'version_editor',
                'contenido_editado_at',
            ]);
        });
    }
};
