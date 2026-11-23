<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_23_000001_bd01_2026_038_add_reapplication_to_acs_student_attempts_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-EXA-051 (control de intentos), RF-EXA-052 (reaplicación)
 * Folio BD-01:    BD01-2026-038
 * Actividad:      ACT-WEB-032
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Permite conservar varios intentos del mismo alumno en un
 *                 examen. Agrega attempt_number (consecutivo del intento,
 *                 1 por omisión) y reapplication_authorized_at (fecha en que
 *                 el docente autorizó otro intento a partir de este; nula si
 *                 no hay autorización). La restricción única pasa de
 *                 (exam_id, student_id) a (exam_id, student_id, attempt_number).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->smallInteger('attempt_number')->default(1);
            $table->timestamp('reapplication_authorized_at')->nullable();
        });

        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->dropUnique(['exam_id', 'student_id']);
            $table->unique(['exam_id', 'student_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->dropUnique(['exam_id', 'student_id', 'attempt_number']);
            $table->unique(['exam_id', 'student_id']);
        });

        Schema::table('acs_student_attempts', function (Blueprint $table): void {
            $table->dropColumn(['attempt_number', 'reapplication_authorized_at']);
        });
    }
};
