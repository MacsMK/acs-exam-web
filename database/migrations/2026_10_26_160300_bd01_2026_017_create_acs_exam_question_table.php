<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_160300_bd01_2026_017_create_acs_exam_question_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Tabla pivote enriquecida de reactivos sorteados y aceptados
 *              por el docente en el armado (RW-16, RW-17, RW-18). Nombre en
 *              singular (acs_exam_question) conforme a la regla de
 *              nomenclatura del propio manual (Tabla 5/3.4.3) — Balam la
 *              había escrito en plural por error de redacción, corregido
 *              aquí (ver doc de avance de BD, 26/10/2026).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_exam_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')
                ->constrained('acs_exams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('exam_section_id')
                ->constrained('acs_exam_sections')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('question_id')
                ->constrained('acs_questions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('time_limit_seconds');
            $table->decimal('points_weight', 6, 2);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_exam_question');
    }
};
