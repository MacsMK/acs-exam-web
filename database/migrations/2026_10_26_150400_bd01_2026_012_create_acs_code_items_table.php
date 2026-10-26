<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_150400_bd01_2026_012_create_acs_code_items_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Variante 4 del banco de preguntas (programación / "encuentra
 *              el error"), evaluada por IA contra reference_solution.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_code_items', function (Blueprint $table) {
            $table->id();
            $table->string('programming_language', 40);
            $table->boolean('is_find_error')->default(false);
            $table->text('starter_code')->nullable();
            $table->text('reference_solution');
            $table->text('instructions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_code_items');
    }
};
