<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_22_000002_bd01_2026_033_add_deleted_at_to_acs_groups_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-GRU-015 (Eliminación automática de grupos, CAM-009),
 *                 RF-SUS-010 (Control de consumo de la prueba)
 * Actividad:      ACT-WEB-022
 * Folio BD-01:    BD01-2026-033
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Agrega deleted_at a acs_groups para el borrado lógico.
 *                 Al eliminar un grupo vencido se borra su información
 *                 académica y el registro queda como constancia mínima, de
 *                 modo que la eliminación no restablece el beneficio de la
 *                 prueba gratuita.
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
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('acs_groups', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
