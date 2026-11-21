<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_18_000001_create_payment_transactions_and_onboarding_column.php
 * Ubicación:      database/migrations/
 * Requerimiento:  ADR-058 / Pasarela de Pago Simulada y Onboarding Docente
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Agrega la columna plan_onboarding_completed_at en
 *                 acs_teacher_verifications (marcando como completadas las
 *                 cuentas docentes ya aprobadas) y crea la tabla
 *                 acs_payment_transactions para registrar transacciones de
 *                 pago simulado (tarjeta y SPEI) con trazabilidad completa.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración agregando el control de onboarding de plan y la
     * tabla de transacciones de pago.
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasColumn('acs_teacher_verifications', 'plan_onboarding_completed_at')) {
            Schema::table('acs_teacher_verifications', function (Blueprint $table): void {
                $table->timestamp('plan_onboarding_completed_at')
                    ->nullable()
                    ->after('reviewed_at');
            });
        }

        DB::table('acs_teacher_verifications')
            ->where('status', 'approved')
            ->whereNull('plan_onboarding_completed_at')
            ->update([
                'plan_onboarding_completed_at' => DB::raw('COALESCE(reviewed_at, CURRENT_TIMESTAMP)'),
            ]);

        Schema::create('acs_payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('teacher_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('subscription_package_id')
                ->constrained('acs_subscription_packages')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('teacher_subscription_id')
                ->nullable()
                ->constrained('acs_teacher_subscriptions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('gateway_provider', 40)->default('simulated');
            $table->string('payment_method', 30);
            $table->string('status', 20)->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MXN');
            $table->string('card_brand', 20)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('card_holder_name', 120)->nullable();
            $table->string('spei_tracking_key', 40)->nullable();
            $table->string('failure_code', 50)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'status']);
        });
    }

    /**
     * Revierte la migración eliminando la tabla de transacciones de pago y
     * la columna plan_onboarding_completed_at en orden inverso.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_payment_transactions');

        Schema::table('acs_teacher_verifications', function (Blueprint $table): void {
            $table->dropColumn('plan_onboarding_completed_at');
        });
    }
};
