<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamQuestion.php
 * Ubicación:   app/Models/ExamQuestion.php
 * Descripción: Modelo Eloquent de acs_exam_question (pivote enriquecida:
 *              reactivo + tiempo + ponderación dentro de un examen/
 *              apartado específico, RW-16/17/18).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamQuestion extends Model
{
    protected $table = 'acs_exam_question';

    protected $fillable = [
        'exam_id',
        'exam_section_id',
        'question_id',
        'time_limit_seconds',
        'points_weight',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'points_weight' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Exam, ExamQuestion>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<ExamSection, ExamQuestion>
     */
    public function examSection(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class);
    }

    /**
     * @return BelongsTo<Question, ExamQuestion>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Respuestas de alumnos a este reactivo dentro del examen.
     *
     * @return HasMany<StudentAnswer>
     */
    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }
}
