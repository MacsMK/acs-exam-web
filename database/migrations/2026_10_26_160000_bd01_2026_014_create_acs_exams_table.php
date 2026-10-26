<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_160000_bd01_2026_014_create_acs_exams_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Examen armado por un docente (Módulo 6/7).
 *              share_token: enlace directo de examen (RW-22, RW-24).
 *              result_visibility_mode: qué ve el alumno al terminar
 *              (RW-26, RW-27). require_webcam: supervisión anti-fraude
 *              (RW-25).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('grading_scale', ['0-10', '0-100'])->default('0-10');
            $table->boolean('randomize_question_order')->default(true);
            $table->enum('result_visibility_mode', [
                'final_score',
                'estimated_score',
                'objective_only_pending',
                'hidden',
            ])->default('objective_only_pending');
            $table->boolean('require_webcam')->default(false);
            $table->smallInteger('max_allowed_incidents')->default(3);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('share_token', 32)->unique();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_exams');
    }
};
