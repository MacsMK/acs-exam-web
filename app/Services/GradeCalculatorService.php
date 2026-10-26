<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GradeCalculatorService.php
 * Ubicación:   app/Services/GradeCalculatorService.php
 * Descripción: Aplica la fórmula matemática de un periodo de evaluación
 *              (acs_evaluation_periods.formula) a los componentes
 *              capturados de un alumno (Listado: "...mediante fórmula
 *              matemática asignada por el docente dar el promedio o
 *              calificación final"). Fórmula soportada: suma de términos
 *              "componente*peso" separados por "+", ej.
 *              "exams*0.6+homework*0.3+project*0.1". Se evalúa con bcmath,
 *              sin eval() de PHP, para no ejecutar código arbitrario.
 * ============================================================================
 */

namespace App\Services;

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
}
