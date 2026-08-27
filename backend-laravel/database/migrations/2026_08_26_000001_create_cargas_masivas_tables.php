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
            $table->string('numero_normalizado', 100)->nullable()->index()->after('numero');
            $table->boolean('requiere_revision')->default(false)->index()->after('estado');
            $table->text('tercero')->nullable()->change();
            $table->text('demandado')->nullable()->change();
            $table->text('demandante')->nullable()->change();
        });

        DB::table('expedientes')
            ->select(['id', 'numero'])
            ->orderBy('id')
            ->eachById(function (object $expediente): void {
                $normalized = mb_strtoupper(trim((string) $expediente->numero), 'UTF-8');
                $normalized = preg_replace('/\s+/u', '', $normalized) ?: null;

                DB::table('expedientes')
                    ->where('id', $expediente->id)
                    ->update(['numero_normalizado' => $normalized]);
            });

        Schema::table('archivos', function (Blueprint $table) {
            $table->boolean('es_principal')->default(true)->index()->after('expediente_id');
            $table->string('origen', 30)->default('manual')->index()->after('es_principal');
        });

        Schema::create('expediente_numero_locks', function (Blueprint $table) {
            $table->string('numero_normalizado', 100)->primary();
        });

        Schema::create('configuraciones_carga_masiva', function (Blueprint $table) {
            $table->id();
            $table->boolean('registro_automatico')->default(true);
            $table->decimal('confianza_minima', 5, 4)->default(0.6500);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('configuraciones_carga_masiva')->insert([
            'id' => 1,
            'registro_automatico' => true,
            'confianza_minima' => 0.6500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('cargas_masivas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 30)->default('cargando')->index();
            $table->unsignedSmallInteger('total_archivos');
            $table->unsignedSmallInteger('archivos_recibidos')->default(0);
            $table->unsignedSmallInteger('procesados')->default(0);
            $table->unsignedSmallInteger('registrados')->default(0);
            $table->unsignedSmallInteger('pendientes')->default(0);
            $table->unsignedSmallInteger('en_revision')->default(0);
            $table->unsignedSmallInteger('fallidos')->default(0);
            $table->boolean('registro_automatico');
            $table->decimal('confianza_minima', 5, 4);
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('completado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('carga_masiva_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_masiva_id')->constrained('cargas_masivas')->cascadeOnDelete();
            $table->foreignId('expediente_id')->nullable()->constrained('expedientes')->nullOnDelete();
            $table->foreignId('archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->string('nombre_original');
            $table->string('extension', 10);
            $table->string('tipo_mime')->nullable();
            $table->string('ruta_almacenamiento')->nullable();
            $table->unsignedBigInteger('tamano_bytes');
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->string('estado', 30)->default('esperando_archivo')->index();
            $table->unsignedTinyInteger('progreso')->default(0);
            $table->string('metodo_extraccion', 40)->nullable();
            $table->decimal('confianza', 5, 4)->nullable();
            $table->json('datos_extraidos')->nullable();
            $table->string('motivo_revision', 60)->nullable()->index();
            $table->text('mensaje_error')->nullable();
            $table->boolean('es_duplicado')->default(false)->index();
            $table->timestamp('procesado_at')->nullable();
            $table->timestamps();

            $table->index(['carga_masiva_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carga_masiva_items');
        Schema::dropIfExists('cargas_masivas');
        Schema::dropIfExists('configuraciones_carga_masiva');
        Schema::dropIfExists('expediente_numero_locks');

        Schema::table('archivos', function (Blueprint $table) {
            $table->dropColumn(['es_principal', 'origen']);
        });

        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn(['numero_normalizado', 'requiere_revision']);
            $table->string('tercero')->nullable()->change();
            $table->string('demandado')->nullable()->change();
            $table->string('demandante')->nullable()->change();
        });
    }
};
