<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->string('materia')->nullable();
            $table->string('juzgado')->nullable();
            $table->string('especialista')->nullable();
            $table->string('tercero')->nullable();
            $table->string('demandado')->nullable();
            $table->string('demandante')->nullable();
            $table->text('estado')->nullable();
            $table->boolean('archivo')->default(false);
            $table->string('nombre_archivo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
