<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentPeriodGrade.php
 * Ubicación:   app/Models/StudentPeriodGrade.php
 * Descripción: Modelo Eloquent de acs_student_period_grades.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPeriodGrade extends Model
{
    protected $table = 'acs_student_period_grades';

    protected $fillable = [
        'evaluation_period_id',
        'student_id',
        'exams_component',
        'homework_component',
        'project_component',
        'other_component',
        'final_grade',
    ];

    protected function casts(): array
    {
        return [
            'exams_component' => 'decimal:2',
            'homework_component' => 'decimal:2',
            'project_component' => 'decimal:2',
            'other_component' => 'decimal:2',
            'final_grade' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<EvaluationPeriod, StudentPeriodGrade>
     */
    public function evaluationPeriod(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriod::class);
    }

    /**
     * @return BelongsTo<User, StudentPeriodGrade>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
