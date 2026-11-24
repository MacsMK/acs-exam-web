<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_23_000002_bd01_2026_039_track_trial_usage_and_terms.php
 * Ubicación:      database/migrations/2026_11_23_000002_bd01_2026_039_track_trial_usage_and_terms.php
 * Requerimiento:  RF-ADM-011 y 015
 * Folio BD-01:    BD01-2026-039
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Registra consumo del beneficio por cuenta y vigencias de los términos.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Aplica el cambio de esquema y conserva los registros anteriores.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('acs_users', function (Blueprint $table): void {
            $table->timestamp('trial_group_used_at')->nullable();
            $table->timestamp('trial_exam_used_at')->nullable();
            $table->boolean('trial_history_complete')->default(false);
            $table->boolean('legacy_group_record')->default(false);
            $table->boolean('legacy_exam_record')->default(false);
        });
        DB::statement('UPDATE acs_users SET legacy_group_record = TRUE WHERE id IN (SELECT teacher_id FROM acs_groups)');
        DB::statement('UPDATE acs_users SET legacy_exam_record = TRUE WHERE id IN (SELECT teacher_id FROM acs_exams)');
        // El historial de las cuentas anteriores no permite identificar todos los consumos de prueba.
        DB::statement('ALTER TABLE acs_users ALTER COLUMN trial_history_complete SET DEFAULT TRUE');

        Schema::create('acs_terms_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 60)->unique();
            $table->text('content');
            $table->timestamp('effective_at')->unique();
            $table->foreignId('published_by')->constrained('acs_users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Revierte las estructuras que incorpora esta migración.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_terms_versions');
        Schema::table('acs_users', function (Blueprint $table): void {
            $table->dropColumn(['trial_group_used_at', 'trial_exam_used_at', 'trial_history_complete', 'legacy_group_record', 'legacy_exam_record']);
        });
    }
};
