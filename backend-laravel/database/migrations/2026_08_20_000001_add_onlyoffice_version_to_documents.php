<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->unsignedBigInteger('master_pdf_rebuild_version')->default(0);
            $table->string('master_pdf_rebuild_status', 20)->default('ready');
            $table->text('master_pdf_rebuild_error')->nullable();
            $table->timestamp('master_pdf_rebuild_requested_at')->nullable();
            $table->timestamp('master_pdf_rebuilt_at')->nullable();
        });

        Schema::table('archivos', function (Blueprint $table) {
            $table->unsignedBigInteger('onlyoffice_version')->default(1);
            $table->timestamp('onlyoffice_saved_at')->nullable();
            $table->boolean('onlyoffice_session_open')->default(false);
            $table->timestamp('onlyoffice_session_expires_at')->nullable();
        });

        Schema::table('resoluciones', function (Blueprint $table) {
            $table->unsignedBigInteger('onlyoffice_version')->default(1);
            $table->timestamp('onlyoffice_saved_at')->nullable();
            $table->boolean('onlyoffice_session_open')->default(false);
            $table->timestamp('onlyoffice_session_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn([
                'master_pdf_rebuild_version',
                'master_pdf_rebuild_status',
                'master_pdf_rebuild_error',
                'master_pdf_rebuild_requested_at',
                'master_pdf_rebuilt_at',
            ]);
        });

        Schema::table('archivos', function (Blueprint $table) {
            $table->dropColumn([
                'onlyoffice_version',
                'onlyoffice_saved_at',
                'onlyoffice_session_open',
                'onlyoffice_session_expires_at',
            ]);
        });

        Schema::table('resoluciones', function (Blueprint $table) {
            $table->dropColumn([
                'onlyoffice_version',
                'onlyoffice_saved_at',
                'onlyoffice_session_open',
                'onlyoffice_session_expires_at',
            ]);
        });
    }
};
