<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentAnswer.php
 * Ubicación:   app/Models/StudentAnswer.php
 * Descripción: Modelo Eloquent de acs_student_answers (incluye el
 *              resultado de la evaluación por IA y el recotejo docente).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    protected $table = 'acs_student_answers';

    protected $fillable = [
        'student_attempt_id',
        'exam_question_id',
        'answer_payload',
        'is_correct',
        'points_awarded',
        'ai_similarity_score',
        'ai_meets_goal',
        'ai_feedback',
        'ai_evaluated_at',
        'teacher_recoteje_score',
        'teacher_recoteje_notes',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answer_payload' => 'array',
            'is_correct' => 'boolean',
            'points_awarded' => 'decimal:2',
            'ai_similarity_score' => 'decimal:2',
            'ai_meets_goal' => 'boolean',
            'ai_evaluated_at' => 'datetime',
            'teacher_recoteje_score' => 'decimal:2',
            'answered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StudentAttempt, StudentAnswer>
     */
    public function studentAttempt(): BelongsTo
    {
        return $this->belongsTo(StudentAttempt::class);
    }

    /**
     * @return BelongsTo<ExamQuestion, StudentAnswer>
     */
    public function examQuestion(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class);
    }
}
