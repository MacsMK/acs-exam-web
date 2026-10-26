<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_160200_bd01_2026_016_create_acs_exam_group_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Tabla pivote (orden alfabético, Tabla 5/3.4.3): a qué
 *              grupos se asigna un examen (RW-23).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_exam_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')
                ->constrained('acs_exams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('group_id')
                ->constrained('acs_groups')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_exam_group');
    }
};
