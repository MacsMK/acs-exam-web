<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentAttempt.php
 * Ubicación:   app/Models/StudentAttempt.php
 * Descripción: Modelo Eloquent de acs_student_attempts.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAttempt extends Model
{
    protected $table = 'acs_student_attempts';

    protected $fillable = [
        'exam_id',
        'student_id',
        'group_id',
        'question_sequence_payload',
        'current_question_index',
        'started_at',
        'submitted_at',
        'status',
        'objective_score',
        'ai_estimated_score',
        'final_score',
        'pending_ai_count',
        'incidents_count',
    ];

    protected function casts(): array
    {
        return [
            'question_sequence_payload' => 'array',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'objective_score' => 'decimal:2',
            'ai_estimated_score' => 'decimal:2',
            'final_score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Exam, StudentAttempt>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<User, StudentAttempt>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<Group, StudentAttempt>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return HasMany<StudentAnswer>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }
}
