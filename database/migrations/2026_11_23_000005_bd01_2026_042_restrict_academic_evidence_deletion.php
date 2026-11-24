<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_23_000005_bd01_2026_042_restrict_academic_evidence_deletion.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-GRU-015, MBD-030, WEB-MBD-002
 * Folio BD-01:    BD01-2026-042
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Protege intentos y respuestas del borrado implícito en cascada.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restringe el borrado indirecto de la evidencia académica.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('acs_users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::table('acs_student_answers', function (Blueprint $table): void {
            $table->dropForeign(['student_attempt_id']);
            $table->foreign('student_attempt_id')->references('id')->on('acs_student_attempts')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Restablece el comportamiento anterior de las llaves foráneas.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('acs_student_answers', function (Blueprint $table): void {
            $table->dropForeign(['student_attempt_id']);
            $table->foreign('student_attempt_id')->references('id')->on('acs_student_attempts')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('acs_users')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }
};
