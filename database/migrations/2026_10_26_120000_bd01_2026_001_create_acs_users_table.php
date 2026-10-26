<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_10_26_120000_bd01_2026_001_create_acs_users_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-AUT (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-001 adjunto)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Crea acs_users conforme al Manual de Base de Datos
 *                 (MBD-V4, Tabla 20, sección 5.5.1): super admin, docentes
 *                 y alumnos en una sola tabla, diferenciados por la columna
 *                 "role". Contrato de autenticación: Laravel Fortify (web)
 *                 + Laravel Sanctum (móvil), hashing Argon2id (MBD-V4 §6.3).
 *
 *                 CAMBIOS v2.0.0 (acuerdo de equipo con Balam, 26/10/2026,
 *                 sobre los 3 puntos abiertos de la v1.0.0):
 *                 - Se confirma first_name/last_name por separado (no
 *                   "name" único) y email obligatorio para los 3 roles.
 *                 - two_factor_enabled (boolean) se sustituye por el
 *                   mecanismo de código de un solo uso de MPM-V2 §6.6.1
 *                   (enviado por correo/SMS, no TOTP de Fortify):
 *                   two_factor_code, two_factor_expires_at,
 *                   two_factor_verified_at. email_verified_at y
 *                   phone_verified_at se conservan aparte porque
 *                   corresponden a la verificación de contacto en el
 *                   registro (Listado de requerimientos: "El sistema
 *                   deberá realizar la verificación del usuario mediante
 *                   el teléfono y email"), un momento distinto al 2FA de
 *                   cada inicio de sesión.
 *                 - phone se acorta a 10 dígitos (validación MX a 10
 *                   dígitos, confirmada por Balam para el rol docente en
 *                   MPW-V2); sigue siendo nullable porque para el alumno
 *                   el teléfono es opcional (MPM-V2, pantalla de registro
 *                   de alumno) — la obligatoriedad condicionada por rol se
 *                   valida en los Form Request de Laravel, no en la
 *                   columna, ya que es una regla de negocio por rol y no
 *                   una restricción fija de la tabla.
 *                 - Se agrega is_active (boolean), confirmado por Balam
 *                   para dar de baja/suspender una cuenta sin borrarla
 *                   (relevante para RW-05, suspensión de docentes).
 *                 - Se agrega remember_token (estándar de
 *                   Illuminate\Auth\Authenticatable para "recordar sesión"
 *                   en el login web de Fortify).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aplica la migración: crea acs_users (BD01-2026-001).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('acs_users', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['super_admin', 'teacher', 'student'])->default('student');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique();
            $table->string('phone', 10)->nullable();
            $table->string('password', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('two_factor_code', 6)->nullable();
            $table->timestamp('two_factor_expires_at')->nullable();
            $table->timestamp('two_factor_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Revierte la migración: elimina acs_users.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_users');
    }
};
