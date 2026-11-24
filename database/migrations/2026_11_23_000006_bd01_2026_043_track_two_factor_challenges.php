<?php

declare(strict_types=1);

/**
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * Proyecto: Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo: 2026_11_23_000006_bd01_2026_043_track_two_factor_challenges.php
 * Ubicación: database/migrations/
 * Requerimiento: RF-AUT-003, RF-AUT-004, RF-AUT-005, RF-AUT-007
 * Folio BD-01: BD01-2026-043
 * Autor: Codex (implementación local)
 * Versión: 1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción: Conserva el contador y acredita el canal del desafío 2FA vigente.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Añade el estado del desafío. Los códigos anteriores carecen de canal acreditado.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('acs_users', function (Blueprint $table): void {
            $table->uuid('two_factor_challenge_id')->nullable();
            $table->string('two_factor_delivery', 10)->nullable();
            $table->unsignedSmallInteger('two_factor_failed_attempts')->default(0);
        });
    }

    /**
     * Revierte las columnas agregadas para el control del desafío.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('acs_users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_challenge_id', 'two_factor_delivery', 'two_factor_failed_attempts']);
        });
    }
};
