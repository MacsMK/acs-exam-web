<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_150000_bd01_2026_008_create_acs_questions_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Tabla raíz polimórfica del banco de preguntas (Módulo 5).
 *              questionable_type/questionable_id (morphTo) apuntan a una de
 *              las 4 variantes: acs_multiple_choice_items, acs_matching_items,
 *              acs_open_items, acs_code_items. parent_question_id es el
 *              versionado al editar una pregunta en el armado (RW-15).
 *              Simplificación consciente respecto al diseño original: todas
 *              las tablas del módulo usan BIGSERIAL (no UUID) para quedar
 *              consistentes con el resto del esquema; el morphTo funciona
 *              igual porque Laravel distingue por tipo + id, no solo por id.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('subject_tag', 100)->nullable();
            $table->string('title', 150);
            $table->text('stem');
            $table->enum('question_type', ['multiple_choice', 'matching', 'open', 'code']);
            $table->decimal('default_points', 6, 2)->default(1.00);
            $table->integer('default_time_seconds');
            $table->string('questionable_type');
            $table->unsignedBigInteger('questionable_id');
            $table->foreignId('parent_question_id')
                ->nullable()
                ->constrained('acs_questions')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['questionable_type', 'questionable_id']);
            $table->index('subject_tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_questions');
    }
};
