<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_160100_bd01_2026_015_create_acs_exam_sections_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Apartados de un examen (RW-12): cuántas preguntas de cada
 *              tipo se sortean del banco para ese apartado.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')
                ->constrained('acs_exams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('title', 100);
            $table->enum('question_type', ['multiple_choice', 'matching', 'open', 'code']);
            $table->smallInteger('requested_quantity');
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_exam_sections');
    }
};
