<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     Exam.php
 * Ubicación:   app/Models/Exam.php
 * Descripción: Modelo Eloquent de acs_exams.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $table = 'acs_exams';

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
}
