<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_22_000004_bd01_2026_035_add_concept_to_acs_payment_transactions_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-SUS-013 (Consulta de operaciones de pago)
 * Actividad:      ACT-WEB-023, ACT-WEB-024, ACT-WEB-025
 * Folio BD-01:    BD01-2026-035
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Agrega a acs_payment_transactions el concepto de cada
 *                 operación: contratación (subscription), renovación
 *                 (renewal) o actualización de nivel (upgrade). Las
 *                 operaciones existentes quedan como contratación.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acs_payment_transactions', function (Blueprint $table): void {
            $table->string('concept', 20)->default('subscription');
        });
    }

    public function down(): void
    {
        Schema::table('acs_payment_transactions', function (Blueprint $table): void {
            $table->dropColumn('concept');
        });
    }
};
