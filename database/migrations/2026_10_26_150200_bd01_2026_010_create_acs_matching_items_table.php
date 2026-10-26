<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_150200_bd01_2026_010_create_acs_matching_items_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Variante 2 del banco de preguntas (relación de columnas).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_matching_items', function (Blueprint $table) {
            $table->id();
            $table->jsonb('left_column_payload');
            $table->jsonb('right_column_payload');
            $table->jsonb('pairs_payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_matching_items');
    }
};
