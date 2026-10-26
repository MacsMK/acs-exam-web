<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_10_26_120500_bd01_2026_002_create_acs_privacy_notices_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-AUT (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-002 adjunto)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Crea acs_privacy_notices conforme al Manual de Base de
 *                 Datos (MBD-V4, Tabla 29, sección 5.5.7): versiones del
 *                 aviso de privacidad, consultado por MPM-V2 §6.6.3
 *                 (api.v1.privacy.show) para mostrar siempre la versión
 *                 vigente. NOTA: la Tabla 29 del MBD-V4 es un resumen, no
 *                 un diccionario de datos completo (a diferencia de las
 *                 Tablas 20-28); las longitudes de "version" y el tipo
 *                 exacto de "content" se proponen aquí de forma razonable
 *                 y deben confirmarse con su propio Formato BD-02 antes de
 *                 darse por definitivas.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aplica la migración: crea acs_privacy_notices (BD01-2026-002).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('acs_privacy_notices', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20);
            $table->text('content');
            $table->timestamp('published_at');
            $table->timestamps();
        });
    }

    /**
     * Revierte la migración: elimina acs_privacy_notices.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_privacy_notices');
    }
};
