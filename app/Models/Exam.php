<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        Exam.php
 * Ubicación:      app/Models/Exam.php
 * Requerimiento:  RF-EXA (Armado y Aplicación de Exámenes, RW-12 a RW-24)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_exams con relaciones y scopes de
 *                 aislamiento multi-docente (MPW-V2 §2.3.4, §6.6).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Examen configurado y armado por un docente.
 *
 * @package App\Models
 */
class Exam extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_exams';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'teacher_id',
        'title',
        'description',
        'grading_scale',
        'randomize_question_order',
        'result_visibility_mode',
        'require_webcam',
        'max_allowed_incidents',
        'starts_at',
        'ends_at',
        'share_token',
        'is_published',
    ];

    /**
     * Conversión de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'randomize_question_order' => 'boolean',
            'require_webcam' => 'boolean',
            'is_published' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Docente autor del examen.
     *
     * @return BelongsTo<User, Exam>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Grupos a los que se asignó este examen.
     *
     * @return BelongsToMany<Group>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'acs_exam_group');
    }

    /**
     * Apartados del examen (RW-12).
     *
     * @return HasMany<ExamSection>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class);
    }

    /**
     * Reactivos sorteados/aceptados en el armado (tabla enriquecida).
     *
     * @return HasMany<ExamQuestion>
     */
    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }

    /**
     * Intentos de alumnos sobre este examen.
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
     * @param Builder<Exam> $query     Constructor de la consulta Eloquent.
     * @param int           $teacherId Identificador del docente propietario.
     * @return Builder<Exam>
     */
    public function scopeForTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where($this->qualifyColumn('teacher_id'), $teacherId);
    }

    /**
     * Scope local para filtrar exámenes publicados (MPW-V2 §2.3.4).
     *
     * @param Builder<Exam> $query Constructor de la consulta Eloquent.
     * @return Builder<Exam>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_published'), true);
    }

    /**
     * Scope local para filtrar exámenes publicados y vigentes en ventana de tiempo (MPW-V2 §2.3.4).
     *
     * @param Builder<Exam> $query Constructor de la consulta Eloquent.
     * @return Builder<Exam>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where($this->qualifyColumn('is_published'), true)
            ->where($this->qualifyColumn('starts_at'), '<=', $now)
            ->where($this->qualifyColumn('ends_at'), '>=', $now);
    }
}
