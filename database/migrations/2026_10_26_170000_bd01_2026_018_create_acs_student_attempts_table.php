<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_170000_bd01_2026_018_create_acs_student_attempts_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Intento de examen de un alumno. question_sequence_payload
 *              guarda el orden exacto asignado (RW-20, aleatorio o fijo).
 *              Granularidad de respuesta: filas independientes en
 *              acs_student_answers (no JSON en este registro) — acordado
 *              con el equipo, coincide con el diseño de Balam.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_student_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')
                ->constrained('acs_exams')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('student_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('group_id')
                ->constrained('acs_groups')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->jsonb('question_sequence_payload');
            $table->smallInteger('current_question_index')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->enum('status', ['in_progress', 'submitted', 'expired', 'cancelled_by_incident'])
                ->default('in_progress');
            $table->decimal('objective_score', 6, 2)->default(0);
            $table->decimal('ai_estimated_score', 6, 2)->default(0);
            $table->decimal('final_score', 6, 2)->nullable();
            $table->smallInteger('pending_ai_count')->default(0);
            $table->smallInteger('incidents_count')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_student_attempts');
    }
};
