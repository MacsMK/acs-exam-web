<?php

declare(strict_types=1);

/**
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * Proyecto: acs-exam-web
 * Archivo: 2026_11_23_000007_bd01_2026_044_persist_exam_drafts_and_presenter.php
 * Ubicación: database/migrations
 * Folio BD-01: BD01-2026-044
 * Requerimiento: RF-EXA-024, RF-EXA-040, RF-EXA-046 y RF-EXA-049
 * Autor: Codex (implementación local)
 * Versión: 1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción: Persiste el borrador confirmado y la sesión presentadora del intento.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Agrega el borrador y los datos mínimos de la sesión activa. @return void */
    public function up(): void
    {
        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->string('presenter_hash', 64)->nullable();
            $table->timestampTz('presenter_seen_at', 6)->nullable();
        });

        Schema::create('acs_attempt_drafts', function (Blueprint $table): void {
            $table->foreignId('student_attempt_id')->primary()->constrained('acs_student_attempts')->cascadeOnDelete();
            $table->foreignId('exam_question_id')->constrained('acs_exam_question')->cascadeOnDelete();
            $table->jsonb('payload');
            $table->timestampTz('saved_at', 6);
        });
    }

    /** Retira la persistencia adicional del intento. @return void */
    public function down(): void
    {
        Schema::dropIfExists('acs_attempt_drafts');

        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->dropColumn(['presenter_hash', 'presenter_seen_at']);
        });
    }
};
