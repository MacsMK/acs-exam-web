<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_10_26_130500_bd01_2026_005_create_acs_teacher_subscriptions_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-SUS (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-005 adjunto)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Crea acs_teacher_subscriptions: la suscripción de un
 *                 docente a un paquete (acs_subscription_packages), con su
 *                 estado de pago y vigencia. Nombre y columnas tomados del
 *                 diseño de Balam (acordado 26/10/2026), más completo que
 *                 el borrador previo de este equipo (que solo tenía
 *                 started_at/ends_at): agrega payment_status y
 *                 suspended_at, que dan soporte directo a RW-05
 *                 (suspensión de un docente por falta de pago).
 *                 teacher_id usa onDelete('cascade') porque una suscripción
 *                 no tiene sentido sin su docente; subscription_package_id
 *                 usa onDelete('restrict') para no permitir borrar un
 *                 paquete mientras algún docente esté suscrito a él.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aplica la migración: crea acs_teacher_subscriptions (BD01-2026-005).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('acs_teacher_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('subscription_package_id')
                ->constrained('acs_subscription_packages')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->enum('payment_status', ['paid', 'pending', 'overdue', 'suspended'])
                ->default('pending');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Revierte la migración: elimina acs_teacher_subscriptions.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_teacher_subscriptions');
    }
};
