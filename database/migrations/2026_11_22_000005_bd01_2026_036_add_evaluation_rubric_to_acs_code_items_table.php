<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_22_000005_bd01_2026_036_add_evaluation_rubric_to_acs_code_items_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-BAN-004 (Registro de preguntas de programación),
 *                 RF-IAR-003 (Evaluación asistida de programación)
 * Actividad:      ACT-WEB-058
 * Folio BD-01:    BD01-2026-036
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Agrega a acs_code_items la columna evaluation_rubric:
 *                 criterios de evaluación opcionales de las preguntas de
 *                 programación, que se envían a la evaluación asistida por
 *                 IA. Nula cuando el docente no los captura.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acs_code_items', function (Blueprint $table): void {
            $table->text('evaluation_rubric')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('acs_code_items', function (Blueprint $table): void {
            $table->dropColumn('evaluation_rubric');
        });
    }
};
