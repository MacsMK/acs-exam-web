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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
            'uses_exam_selection' => 'boolean',
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
     * Consulta los periodos fuente y la contribución de cada uno.
     *
     * @return BelongsToMany Relación con el porcentaje de la dependencia.
     */
    public function previousPeriods(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'acs_period_dependencies', 'period_id', 'source_id')->withPivot('weight');
    }

    /** @return list<int> Aplicaciones requeridas en la selección explícita. */
    public function requiredExamApplicationIds(): array
    {
        return DB::table('acs_period_required_exams')->where('period_id', $this->id)
            ->orderBy('application_id')->pluck('application_id')->map(static fn ($id): int => (int) $id)->all();
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
     * Comprueba que la fórmula histórica tenga una nota por cada rubro con peso.
     *
     * @param array<string, string|float|int|null> $components Notas capturadas por rubro.
     * @return bool Si se puede mostrar una nota definitiva con los datos existentes.
     */
    public function hasCompleteManualGrades(array $components): bool
    {
        $weights = $this->parsed_weights;
        // Una fórmula histórica sin estructura solo permite acreditar que todos los rubros están presentes.
        $required = $weights === []
            ? array_keys($components)
            : array_keys(array_filter($weights, static fn (float $weight): bool => $weight > 0));

        foreach ($required as $component) {
            if (($components[$component] ?? null) === null) {
                return false;
            }
        }

        return $required !== [];
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

        $total = array_sum($weights) + ($this->uses_grade_plan ? $this->previousPeriods->sum('pivot.weight') : 0);

        return abs($total - 100.0) < 0.01;
    }

    /**
     * Determina si el periodo alcanzó su primer día en la zona horaria de la aplicación.
     */
    public function hasStarted(): bool
    {
        return $this->starts_at !== null && now()->gte($this->starts_at->startOfDay());
    }

    /**
     * Determina si el periodo está en curso, incluida toda la fecha de cierre.
     */
    public function getIsActiveTodayAttribute(): bool
    {
        return $this->hasStarted() && $this->ends_at !== null && now()->lte($this->ends_at->endOfDay());
    }
}
