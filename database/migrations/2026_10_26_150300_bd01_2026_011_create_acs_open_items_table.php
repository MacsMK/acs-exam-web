<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_150300_bd01_2026_011_create_acs_open_items_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Variante 3 del banco de preguntas (pregunta abierta,
 *              evaluada por IA contra reference_answer, RW-09).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_open_items', function (Blueprint $table) {
            $table->id();
            $table->text('reference_answer');
            $table->text('evaluation_rubric')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_open_items');
    }
};
