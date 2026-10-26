<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        Group.php
 * Ubicación:      app/Models/Group.php
 * Requerimiento:  RF-GRU (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-006)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_groups: grupo de alumnos
 *                 administrado por un docente, identificable por un folio
 *                 de acceso corto (access_folio).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo de alumnos de un docente.
 *
 * @package App\Models
 */
class Group extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_groups';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'teacher_id',
        'name',
        'subject',
        'access_folio',
        'is_closed',
        'closed_at',
    ];

    /**
     * Conversión de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Docente que administra el grupo.
     *
     * @return BelongsTo<User, Group>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Alumnos inscritos en el grupo (acs_group_student).
     *
     * @return BelongsToMany<User>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'acs_group_student', 'group_id', 'student_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * Exámenes asignados a este grupo (acs_exam_group).
     *
     * @return BelongsToMany<Exam>
     */
    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'acs_exam_group');
    }

    /**
     * Periodos de evaluación del grupo (parciales con fórmula).
     *
     * @return HasMany<EvaluationPeriod>
     */
    public function evaluationPeriods(): HasMany
    {
        return $this->hasMany(EvaluationPeriod::class);
    }

    /**
     * Intentos de examen realizados por alumnos de este grupo.
     *
     * @return HasMany<StudentAttempt>
     */
    public function studentAttempts(): HasMany
    {
        return $this->hasMany(StudentAttempt::class);
    }
}
