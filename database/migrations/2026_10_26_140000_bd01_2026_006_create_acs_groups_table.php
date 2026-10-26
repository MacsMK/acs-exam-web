<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_10_26_140000_bd01_2026_006_create_acs_groups_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-GRU (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-006 adjunto)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Crea acs_groups: grupo de alumnos que crea y administra
 *                 un docente (MBD-V4 §6.2 / Listado de requerimientos,
 *                 gestión de grupos). access_folio es el código corto que
 *                 un alumno captura para unirse al grupo (MPM-V2, pantalla
 *                 de unirse a grupo); se indexa único porque debe poder
 *                 buscarse por sí solo, sin conocer el id ni el docente.
 *                 is_closed/closed_at (diseño de Balam, acordado
 *                 26/10/2026): permiten cerrar un grupo al terminar el
 *                 semestre sin borrarlo, conservando su historial de
 *                 exámenes y calificaciones para la auditoría. subject
 *                 (materia) también se toma del diseño de Balam: un mismo
 *                 docente puede tener varios grupos de materias distintas.
 *                 teacher_id usa onDelete('cascade'): si se elimina el
 *                 docente, sus grupos pierden sentido (decisión razonable,
 *                 no normada explícitamente por el MBD-V4; a confirmar por
 *                 el Revisor técnico en BD-01).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aplica la migración: crea acs_groups (BD01-2026-006).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('acs_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('subject', 120)->nullable();
            $table->string('access_folio', 12)->unique();
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Revierte la migración: elimina acs_groups.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_groups');
    }
};
