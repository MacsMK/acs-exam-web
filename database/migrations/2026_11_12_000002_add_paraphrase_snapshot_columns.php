<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_12_000002_add_paraphrase_snapshot_columns.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-BAN-010 / RF-REP-005 (Trazabilidad Inmutable de Paráfrasis)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 12/11/2026
 * Descripción:    Agrega columnas de snapshot de paráfrasis en acs_student_attempts
 *                 (paraphrase_snapshot_payload) y acs_student_answers
 *                 (question_paraphrase_id, served_stem_snapshot) para preservar
 *                 la redacción exacta servida al estudiante durante su intento.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración agregando las columnas de snapshot de paráfrasis.
     */
    public function up(): void
    {
        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->jsonb('paraphrase_snapshot_payload')
                ->nullable()
                ->after('question_sequence_payload');
        });

        Schema::table('acs_student_answers', function (Blueprint $table): void {
            $table->foreignId('question_paraphrase_id')
                ->nullable()
                ->after('exam_question_id')
                ->constrained('acs_question_paraphrases')
                ->nullOnDelete();
            $table->text('served_stem_snapshot')
                ->nullable()
                ->after('question_paraphrase_id');
        });
    }

    /**
     * Revierte las columnas y llaves foráneas agregadas por esta migración.
     */
    public function down(): void
    {
        Schema::table('acs_student_answers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('question_paraphrase_id');
            $table->dropColumn('served_stem_snapshot');
        });

        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->dropColumn('paraphrase_snapshot_payload');
        });
    }
};
