<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_150100_bd01_2026_009_create_acs_multiple_choice_items_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Variante 1 del banco de preguntas (opción múltiple).
 *              options_payload: arreglo JSON [{"index":0,"text":"..."}, ...].
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_multiple_choice_items', function (Blueprint $table) {
            $table->id();
            $table->jsonb('options_payload');
            $table->unsignedSmallInteger('correct_option_index');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_multiple_choice_items');
    }
};
