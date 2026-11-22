<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_22_000003_bd01_2026_034_add_renewal_columns_to_acs_teacher_subscriptions_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-SUS-002, RF-SUS-003, RF-SUS-005,
 *                 RF-SUS-006, RF-SUS-014
 * Actividad:      ACT-WEB-023, ACT-WEB-024, ACT-WEB-025
 * Folio BD-01:    BD01-2026-034
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Agrega a acs_teacher_subscriptions la renovación automática
 *                 (auto_renew, renewal_attempts), la cancelación de la
 *                 renovación (cancelled_at) y los datos limitados del medio
 *                 de pago domiciliado (marca, últimos 4 dígitos y titular;
 *                 nunca el número completo ni el CVV).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acs_teacher_subscriptions', function (Blueprint $table): void {
            $table->boolean('auto_renew')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedSmallInteger('renewal_attempts')->default(0);
            $table->string('card_brand', 20)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('card_holder_name', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('acs_teacher_subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['auto_renew', 'cancelled_at', 'renewal_attempts', 'card_brand', 'card_last4', 'card_holder_name']);
        });
    }
};
