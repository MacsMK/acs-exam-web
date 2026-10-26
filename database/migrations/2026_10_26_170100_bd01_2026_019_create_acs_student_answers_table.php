<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_170100_bd01_2026_019_create_acs_student_answers_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Respuesta de un alumno a un reactivo dentro de un intento.
 *              El resultado de la evaluación por IA va embebido aquí
 *              (ai_*; relación 1:1 respuesta-evaluación, acordado con el
 *              equipo) en vez de una tabla aparte. teacher_recoteje_*
 *              soporta la segunda revisión docente (RW-11).
 *              NOTA: columnas construidas a partir de los requerimientos
 *              confirmados (RW-09 similitud IA, RW-11 recotejo) y de
 *              review-attempt.blade.php (comparativa + puntaje de
 *              similitud + dictamen + retroalimentación) — el documento de
 *              arquitectura de Balam no mostró el diccionario completo de
 *              esta tabla, así que vale la pena que él la revise en BD-01.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_student_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_attempt_id')
                ->constrained('acs_student_attempts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('exam_question_id')
                ->constrained('acs_exam_question')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->jsonb('answer_payload')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_awarded', 6, 2)->nullable();
            $table->decimal('ai_similarity_score', 5, 2)->nullable();
            $table->boolean('ai_meets_goal')->nullable();
            $table->text('ai_feedback')->nullable();
            $table->timestamp('ai_evaluated_at')->nullable();
            $table->decimal('teacher_recoteje_score', 6, 2)->nullable();
            $table->text('teacher_recoteje_notes')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['student_attempt_id', 'exam_question_id'], 'acs_student_answers_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_student_answers');
    }
};
