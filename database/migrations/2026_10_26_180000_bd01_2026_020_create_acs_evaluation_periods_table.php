<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_180000_bd01_2026_020_create_acs_evaluation_periods_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Periodo de evaluación de un grupo (ej. "Parcial 1") con su
 *              fórmula matemática para combinar componentes (Listado: "...
 *              mediante fórmula matemática asignada por el docente dar el
 *              promedio o calificación final"). formula es una expresión
 *              simple evaluada por GradeCalculatorService (bcmath), ej.
 *              "exams*0.6+homework*0.3+project*0.1".
 *              NOTA: columnas construidas a partir del requerimiento del
 *              Listado, el documento de arquitectura de Balam no mostró el
 *              diccionario completo de este módulo — revisar en BD-01.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_evaluation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                ->constrained('acs_groups')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('formula', 255);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_evaluation_periods');
    }
};
