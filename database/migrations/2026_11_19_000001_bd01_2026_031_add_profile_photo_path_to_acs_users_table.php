<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_19_000001_bd01_2026_031_add_profile_photo_path_to_acs_users_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-AUT-008 (Carga de fotografía del estudiante),
 *                 RF-AUT-009 (Imagen de perfil predeterminada)
 * Actividad:      ACT-WEB-038
 * Folio BD-01:    BD01-2026-031
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Agrega la columna profile_photo_path a acs_users para
 *                 guardar la ruta de la fotografía opcional del estudiante
 *                 en el almacenamiento privado. Nula cuando no hay fotografía
 *                 (se muestra la imagen predeterminada).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acs_users', function (Blueprint $table): void {
            $table->string('profile_photo_path', 255)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('acs_users', function (Blueprint $table): void {
            $table->dropColumn('profile_photo_path');
        });
    }
};
