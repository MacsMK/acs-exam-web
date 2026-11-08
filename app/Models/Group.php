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
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_groups: grupo de alumnos
 *                 administrado por un docente, identificable por un folio
 *                 de acceso corto (access_folio), con aislamiento por
 *                 docente (MPW-V2 §6.6) y generador acotado de folio
 *                 único (MPW-V2 §5.2.1).
 * ============================================================================
 */

namespace App\Models;

use RuntimeException;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Grupo de alumnos de un docente.
 *
 * @package App\Models
 */
class Group extends Model
{
    /**
     * Cota máxima de intentos para generar un folio aleatorio sin colisión (MPW-V2 §5.2.1).
     */
    public const MAX_FOLIO_ATTEMPTS = 100;

    /**
     * Longitud estándar del folio de acceso alfanumérico.
     */
    public const FOLIO_LENGTH = 8;

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

    /**
     * Scope local de aislamiento multi-docente (MPW-V2 §2.3.4, §6.6).
     *
     * @param Builder<Group> $query     Constructor de la consulta Eloquent.
     * @param int            $teacherId Identificador del docente propietario.
     * @return Builder<Group>
     */
    public function scopeForTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where($this->qualifyColumn('teacher_id'), $teacherId);
    }

    /**
     * Scope local para filtrar únicamente grupos abiertos (ciclo activo, RF-GRU-019).
     *
     * @param Builder<Group> $query Constructor de la consulta Eloquent.
     * @return Builder<Group>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_closed'), false);
    }

    /**
     * Genera un folio de acceso único en base de datos con guarda de seguridad
     * máxima obligatoria para prevenir ciclos infinitos (MPW-V2 §5.2.1).
     *
     * @return string Folio único en mayúsculas de 8 caracteres.
     * @throws RuntimeException Si se excede el límite máximo de intentos sin hallar folio libre.
     */
    public static function generateUniqueFolio(): string
    {
        $maxAttempts = self::MAX_FOLIO_ATTEMPTS;
        $attempts = 0;

        do {
            if ($attempts >= $maxAttempts) {
                throw new RuntimeException(
                    "No fue posible generar un folio de acceso único tras {$maxAttempts} intentos."
                );
            }

            $folio = strtoupper(Str::random(self::FOLIO_LENGTH));
            $attempts++;
        } while (self::query()->where('access_folio', $folio)->exists());

        return $folio;
    }
}
