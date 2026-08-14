<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            // Drop default email and name columns
            $table->dropColumn(['name', 'email', 'email_verified_at', 'remember_token']);

            // Add custom columns from Spring Boot Usuario entity
            $table->string('nombre')->after('id')->nullable();
            $table->string('username')->unique()->after('nombre');
            $table->foreignId('rol_id')->after('password')->constrained('roles')->onDelete('cascade');
            $table->string('email')->after('rol_id')->nullable();
        });

        // NO renombramos la tabla, la dejamos como 'users'
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restore original Laravel columns
            $table->dropForeign(['rol_id']);
            $table->dropColumn(['nombre', 'username', 'rol_id', 'email']);

            $table->string('name')->after('id');
            $table->string('email')->unique()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('remember_token', 100)->nullable();
        });
    }
};
