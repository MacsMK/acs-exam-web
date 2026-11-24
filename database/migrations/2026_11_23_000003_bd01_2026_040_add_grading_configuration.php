<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_23_000003_bd01_2026_040_add_grading_configuration.php
 * Ubicación:      database/migrations/2026_11_23_000003_bd01_2026_040_add_grading_configuration.php
 * Requerimiento:  RF-CAL-006 a 008, 010 y 011, 013, 015, 016 y 018 a 020, 023 a 025 y 028, RF-REP-008 y 017
 * Folio BD-01:    BD01-2026-040
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Agrega la configuración de calificaciones, dependencias y avisos.
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
        Schema::table('acs_groups', function (Blueprint $table): void {
            $table->string('grading_scale', 5)->nullable();
            $table->decimal('passing_score', 5, 2)->nullable();
        });
        Schema::table('acs_evaluation_periods', function (Blueprint $table): void {
            $table->boolean('uses_grade_plan')->default(false);
            $table->decimal('group_final_weight', 5, 2)->nullable();
        });
        Schema::table('acs_student_period_grades', function (Blueprint $table): void {
            $table->jsonb('manual_comments')->nullable();
        });
        Schema::create('acs_period_exam_weights', function (Blueprint $table): void {
            $table->foreignId('application_id')->primary()->constrained('acs_exam_group')->restrictOnDelete();
            $table->foreignId('period_id')->constrained('acs_evaluation_periods')->cascadeOnDelete();
            $table->decimal('weight', 5, 2);
            $table->index('period_id');
        });
        Schema::create('acs_period_dependencies', function (Blueprint $table): void {
            $table->foreignId('period_id')->constrained('acs_evaluation_periods')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('acs_evaluation_periods')->restrictOnDelete();
            $table->decimal('weight', 5, 2);
            $table->primary(['period_id', 'source_id']);
        });
        Schema::create('acs_grade_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('acs_users')->cascadeOnDelete();
            $table->decimal('new_grade', 5, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['student_id', 'id']);
        });
        DB::statement('ALTER TABLE acs_period_exam_weights ADD CHECK (weight > 0 AND weight <= 100)');
        DB::statement('ALTER TABLE acs_period_dependencies ADD CHECK (period_id <> source_id AND weight > 0 AND weight <= 100)');
        DB::statement('ALTER TABLE acs_evaluation_periods ADD CHECK (group_final_weight > 0 AND group_final_weight <= 100)');
        DB::statement("ALTER TABLE acs_groups ADD CHECK ((grading_scale IS NULL AND passing_score IS NULL) OR
            (grading_scale IS NOT NULL AND passing_score IS NOT NULL AND
            ((grading_scale = '0-10' AND passing_score BETWEEN 0 AND 10) OR
             (grading_scale = '0-100' AND passing_score BETWEEN 0 AND 100 AND passing_score = trunc(passing_score)))))");
    }

    /**
     * Revierte las estructuras que incorpora esta migración.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_grade_notices');
        Schema::dropIfExists('acs_period_dependencies');
        Schema::dropIfExists('acs_period_exam_weights');
        Schema::table('acs_student_period_grades', fn (Blueprint $table) => $table->dropColumn('manual_comments'));
        Schema::table('acs_evaluation_periods', fn (Blueprint $table) => $table->dropColumn(['group_final_weight', 'uses_grade_plan']));
        Schema::table('acs_groups', fn (Blueprint $table) => $table->dropColumn(['grading_scale', 'passing_score']));
    }
};
