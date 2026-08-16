<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('estado', 20)->default('pendiente');
            $table->boolean('es_documento_base')->default(false);
            $table->string('nombre_archivo')->nullable();
            $table->string('tipo_archivo')->nullable();
            $table->longText('documento_data')->nullable();
            $table->timestamp('completada_at')->nullable();
            $table->timestamps();

            $table->unique(['expediente_id', 'numero']);
            $table->index(['expediente_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resoluciones');
    }
};
