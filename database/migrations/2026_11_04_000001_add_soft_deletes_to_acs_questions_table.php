<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_04_000001_add_soft_deletes_to_acs_questions_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, Módulo 5)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 04/11/2026
 * Descripción:    Incorpora la columna deleted_at para borrado lógico (Soft
 *                 Deletes) en la tabla acs_questions.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones agregando deleted_at a acs_questions.
     */
    public function up(): void
    {
        Schema::table('acs_questions', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    /**
     * Revierte las migraciones eliminando deleted_at de acs_questions.
     */
    public function down(): void
    {
        Schema::table('acs_questions', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
