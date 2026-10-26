<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_150500_bd01_2026_013_create_acs_question_paraphrases_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Variantes de redacción de un reactivo canónico (Listado:
 *              "replantear las preguntas haciendo uso de la paráfrasis para
 *              que no siempre sea la misma pregunta, pero sí la misma
 *              respuesta"). Comparte respuesta de referencia y ponderación
 *              con acs_questions; distinto propósito de parent_question_id
 *              (que es versionado de edición, no anti-copia) — ver
 *              propuesta enviada al equipo el 26/10/2026.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_question_paraphrases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')
                ->constrained('acs_questions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->text('stem');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_question_paraphrases');
    }
};
