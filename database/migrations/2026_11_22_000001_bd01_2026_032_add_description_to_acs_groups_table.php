<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_22_000001_bd01_2026_032_add_description_to_acs_groups_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-GRU-001 (Creación de grupos),
 *                 RF-GRU-003 (Modificación de grupos)
 * Actividad:      ACT-WEB-020
 * Folio BD-01:    BD01-2026-032
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Agrega a acs_groups la columna description, descripción
 *                 opcional del grupo que el docente captura al crearlo y
 *                 puede modificar mientras el grupo esté activo. Nula
 *                 cuando el docente no la captura.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acs_groups', function (Blueprint $table): void {
            $table->string('description', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('acs_groups', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
