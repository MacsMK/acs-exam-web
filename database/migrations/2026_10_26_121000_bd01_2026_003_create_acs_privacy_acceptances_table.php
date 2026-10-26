<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_10_26_121000_bd01_2026_003_create_acs_privacy_acceptances_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-AUT (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-003 adjunto)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Crea acs_privacy_acceptances conforme al Manual de Base
 *                 de Datos (MBD-V4, Tabla 29, sección 5.5.7): registra la
 *                 aceptación de un usuario a una versión específica del
 *                 aviso de privacidad (api.v1.privacy.accept, MPM-V2
 *                 §6.6.3). Relación N:M entre acs_users y
 *                 acs_privacy_notices, modelada como tabla de hechos con
 *                 su propio id (no como pivote simple), porque conserva la
 *                 marca de tiempo de cada aceptación individual.
 *                 Decisión de diseño NO normada explícitamente por el
 *                 MBD-V4 (debe confirmarse por BD-01): user_id usa
 *                 onDelete('cascade') porque una aceptación no tiene
 *                 sentido sin su usuario; privacy_notice_id usa
 *                 onDelete('restrict') para no permitir borrar una versión
 *                 del aviso mientras existan aceptaciones registradas
 *                 contra ella, dado que la trazabilidad de quién aceptó
 *                 qué versión es evidencia relevante para la auditoría
 *                 (MBD-V4 §6.4).
 *                 CAMBIO v1.1.0 (acuerdo de equipo con Balam, 26/10/2026):
 *                 se agrega ip_address para reforzar la evidencia de
 *                 auditoría de cada aceptación (de dónde se aceptó), idea
 *                 tomada del diseño de privacidad de Balam combinada con
 *                 esta tabla de hechos propia (en vez de una sola columna
 *                 "privacy_accepted_ip" en acs_users, que solo guardaría
 *                 la última aceptación y perdería el historial).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aplica la migración: crea acs_privacy_acceptances (BD01-2026-003).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('acs_privacy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('privacy_notice_id')
                ->constrained('acs_privacy_notices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Revierte la migración: elimina acs_privacy_acceptances.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_privacy_acceptances');
    }
};
