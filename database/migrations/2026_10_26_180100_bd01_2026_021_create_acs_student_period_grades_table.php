<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_180100_bd01_2026_021_create_acs_student_period_grades_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Calificación de un alumno en un periodo de evaluación:
 *              componentes capturados (exámenes, tareas, proyecto, otros)
 *              más el resultado de aplicarles la fórmula del periodo.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_student_period_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_period_id')
                ->constrained('acs_evaluation_periods')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->decimal('exams_component', 6, 2)->nullable();
            $table->decimal('homework_component', 6, 2)->nullable();
            $table->decimal('project_component', 6, 2)->nullable();
            $table->decimal('other_component', 6, 2)->nullable();
            $table->decimal('final_grade', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['evaluation_period_id', 'student_id'], 'acs_student_period_grades_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_student_period_grades');
    }
};
