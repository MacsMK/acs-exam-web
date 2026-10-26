<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_10_26_130000_bd01_2026_004_create_acs_subscription_packages_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-SUS (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-004 adjunto)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Crea acs_subscription_packages: catálogo de los paquetes
 *                 de suscripción que puede contratar un docente (MBD-V4
 *                 Tabla 21 / Listado de requerimientos, tabla de paquetes).
 *                 Nombres de nivel (tier) acordados con Balam el
 *                 26/10/2026: basic / advanced / pro (MPW-V2 Tabla 8),
 *                 sustituyendo los nombres provisionales free/standard/
 *                 premium de la v1.0.0 anterior de este borrador.
 *                 max_concurrent_students no es nullable porque los 3
 *                 niveles tienen tope definido (40/120/500 según Balam);
 *                 max_groups y max_questions sí son nullable porque el
 *                 Listado no fija un tope explícito para el nivel "pro" en
 *                 esas dos columnas (pendiente de que el equipo confirme
 *                 si "pro" es realmente ilimitado o solo un número alto).
 *                 ai_requests_limit queda nullable y SIN resolver: el
 *                 Listado marca con nota al pie que el límite de uso de IA
 *                 por paquete "lo definirá el equipo" (MBD-V4 §8.3, punto
 *                 abierto aparte de los 3 ya cerrados hoy) — no se inventa
 *                 un número aquí.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aplica la migración: crea acs_subscription_packages (BD01-2026-004).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('acs_subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->enum('tier', ['basic', 'advanced', 'pro'])->unique();
            $table->string('name', 100);
            $table->unsignedInteger('max_groups')->nullable();
            $table->unsignedInteger('max_questions')->nullable();
            $table->unsignedInteger('max_concurrent_students');
            $table->unsignedInteger('max_active_exams')->nullable();
            $table->unsignedInteger('ai_requests_limit')->nullable();
            $table->decimal('monthly_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Revierte la migración: elimina acs_subscription_packages.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_subscription_packages');
    }
};
