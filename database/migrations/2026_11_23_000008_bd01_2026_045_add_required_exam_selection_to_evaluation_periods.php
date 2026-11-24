<?php

declare(strict_types=1);

/**
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * Proyecto: acs-exam-web
 * Folio BD-01: BD01-2026-045
 * Requerimiento: RF-CAL-006
 * Descripción: Registra la selección manual de aplicaciones requeridas por periodo.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acs_evaluation_periods', function (Blueprint $table): void {
            $table->boolean('uses_exam_selection')->default(false);
        });

        Schema::create('acs_period_required_exams', function (Blueprint $table): void {
            $table->foreignId('period_id')->constrained('acs_evaluation_periods')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('acs_exam_group')->cascadeOnDelete();
            $table->primary(['period_id', 'application_id']);
            $table->index('application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_period_required_exams');
        Schema::table('acs_evaluation_periods', function (Blueprint $table): void {
            $table->dropColumn('uses_exam_selection');
        });
    }
};
