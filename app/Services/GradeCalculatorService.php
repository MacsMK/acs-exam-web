<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GradeCalculatorService.php
 * Ubicación:   app/Services/GradeCalculatorService.php
 * Descripción: Aplica la fórmula matemática de un periodo de evaluación
 *              (acs_evaluation_periods.formula) y normaliza la calificación
 *              de un examen base-100 hacia escala 0-10 u 0-100 (ADR-056).
 * ============================================================================
 */

namespace App\Services;

use App\Models\Exam;

class GradeCalculatorService
{
    /**
     * @param array<string, float|null> $components claves: exams, homework, project, other
     */
    public function calculate(string $formula, array $components): float
    {
        $total = '0';

        foreach (explode('+', str_replace(' ', '', $formula)) as $term) {
            if ($term === '') {
                continue;
            }

            [$component, $weight] = array_pad(explode('*', $term), 2, '1');
            $value = (string) ($components[$component] ?? 0);

            $total = bcadd($total, bcmul($value, $weight, 6), 6);
        }

        return (float) round((float) $total, 2);
    }

    /**
     * Normaliza el puntaje bruto ganado al rango de la escala configurada en el
     * examen cuando la suma de ponderaciones del examen equivale a 100.00 (ADR-056).
     */
    public function normalizeScoreForExam(float $rawPointsEarned, Exam $exam, float $totalExamWeight): float
    {
        if ($exam->grading_scale === '0-10' && abs($totalExamWeight - 100.0) <= 0.05) {
            return round($rawPointsEarned / 10.0, 2);
        }

        return round($rawPointsEarned, 2);
    }
}
