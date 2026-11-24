<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_23_000004_bd01_2026_041_add_free_exam_timing.php
 * Ubicación:      database/migrations/2026_11_23_000004_bd01_2026_041_add_free_exam_timing.php
 * Requerimiento:  RF-EXA-012, 014, 033 y 043
 * Folio BD-01:    BD01-2026-041
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Almacena duración de la modalidad libre, plazos por intento y revisión de respuesta.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Aplica el cambio de esquema y conserva los registros anteriores.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('acs_exams', fn (Blueprint $table) => $table->unsignedInteger('free_duration_minutes')->nullable());
        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->timestamp('free_edit_until')->nullable();
            $table->timestamp('free_close_at')->nullable();
            $table->index(['status', 'free_close_at']);
        });
        Schema::table('acs_student_answers', fn (Blueprint $table) => $table->unsignedInteger('free_revision')->default(0));
        DB::statement('ALTER TABLE acs_exams ADD CHECK (free_duration_minutes > 0)');
        DB::statement('ALTER TABLE acs_student_attempts ADD CHECK (
            (free_edit_until IS NULL AND free_close_at IS NULL) OR
            (free_edit_until IS NOT NULL AND free_close_at IS NOT NULL AND free_close_at >= free_edit_until AND free_edit_until > started_at))');
        DB::statement('ALTER TABLE acs_student_answers ADD CHECK (free_revision >= 0)');
    }

    /**
     * Revierte las estructuras que incorpora esta migración.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('acs_student_answers', fn (Blueprint $table) => $table->dropColumn('free_revision'));
        Schema::table('acs_student_attempts', fn (Blueprint $table) => $table->dropColumn(['free_edit_until', 'free_close_at']));
        Schema::table('acs_exams', fn (Blueprint $table) => $table->dropColumn('free_duration_minutes'));
    }
};
