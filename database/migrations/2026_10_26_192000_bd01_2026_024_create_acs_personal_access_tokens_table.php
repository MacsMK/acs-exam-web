<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_192000_bd01_2026_024_create_acs_personal_access_tokens_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Tabla oficial de Laravel Sanctum para tokens Bearer de la
 *              app móvil (acs-exam-mobile), renombrada con el prefijo
 *              institucional acs_ (ver App\Models\PersonalAccessToken y
 *              AppServiceProvider::usePersonalAccessTokenModel()).
 *              Mismo diccionario que la migración oficial de Sanctum.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_personal_access_tokens');
    }
};
