<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_10_26_140500_bd01_2026_007_create_acs_group_student_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-GRU (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-007 adjunto)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Crea acs_group_student: relación N:M entre acs_groups y
 *                 acs_users (alumnos) — un alumno puede estar en varios
 *                 grupos y un grupo tiene varios alumnos. Lleva id propio
 *                 (en vez de llave primaria compuesta) y joined_at, para
 *                 conservar la fecha en que el alumno se unió al grupo
 *                 (relevante para la auditoría de quién estuvo inscrito y
 *                 desde cuándo). Restricción única (group_id, student_id)
 *                 evita inscribir dos veces al mismo alumno en el mismo
 *                 grupo. Ambas llaves foráneas usan onDelete('cascade'):
 *                 la inscripción no tiene sentido sin el grupo ni sin el
 *                 alumno.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aplica la migración: crea acs_group_student (BD01-2026-007).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('acs_group_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                ->constrained('acs_groups')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['group_id', 'student_id']);
        });
    }

    /**
     * Revierte la migración: elimina acs_group_student.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_group_student');
    }
};
