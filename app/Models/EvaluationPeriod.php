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

    /**
     * Parsea la fórmula estructurada (exams*0.5+homework*0.25+...) y retorna los porcentajes.
     *
     * @return array<string, float>
     */
    public function getParsedWeightsAttribute(): array
    {
        $formula = preg_replace('/\s+/', '', (string) $this->formula);
        if ($formula === '') {
            return [];
        }

        $weights = [];
        foreach (explode('+', $formula) as $term) {
            if (preg_match('/^(exams|homework|project|other)\*(\d+(?:\.\d+)?)$/', $term, $matches)) {
                $weights[$matches[1]] = (float) $matches[2] * 100;
            }
        }

        return $weights;
    }

    /**
     * Valida si la fórmula contiene rubros válidos y suma exactamente 100%.
     */
    public function getIsValidFormulaAttribute(): bool
    {
        $weights = $this->parsed_weights;
        if (empty($weights)) {
            return false;
        }

        $total = array_sum($weights);

        return abs($total - 100.0) < 0.01;
    }

    /**
     * Determina si el periodo está en curso en la fecha actual.
     */
    public function getIsActiveTodayAttribute(): bool
    {
        $now = now();

        return $this->starts_at !== null && $this->ends_at !== null && $now->between($this->starts_at, $this->ends_at);
    }
}
