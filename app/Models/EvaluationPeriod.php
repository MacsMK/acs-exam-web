<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     EvaluationPeriod.php
 * Ubicación:   app/Models/EvaluationPeriod.php
 * Descripción: Modelo Eloquent de acs_evaluation_periods.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationPeriod extends Model
{
    protected $table = 'acs_evaluation_periods';

    protected $fillable = [
        'group_id',
        'name',
        'formula',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Group, EvaluationPeriod>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return HasMany<StudentPeriodGrade>
     */
    public function studentGrades(): HasMany
    {
        return $this->hasMany(StudentPeriodGrade::class);
    }
}
