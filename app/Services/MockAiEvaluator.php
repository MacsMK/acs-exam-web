<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     MockAiEvaluator.php
 * Ubicación:   app/Services/MockAiEvaluator.php
 * Descripción: Driver "mock" de AiEvaluatorContract para demo/desarrollo
 *              sin llave de API: estima similitud por palabras en común
 *              (no es el evaluador real de IA, es un placeholder
 *              declarado como tal en AI_EVALUATOR_DRIVER=mock).
 * ============================================================================
 */

namespace App\Services;

use App\Contracts\AiEvaluatorContract;

class MockAiEvaluator implements AiEvaluatorContract
{
    public function evaluate(string $studentAnswer, string $referenceAnswer): array
    {
        $studentWords = $this->wordSet($studentAnswer);
        $referenceWords = $this->wordSet($referenceAnswer);

        if ($referenceWords === []) {
            return ['similarity_score' => 0.0, 'meets_goal' => false, 'feedback' => 'Sin respuesta de referencia.'];
        }

        $intersection = array_intersect($studentWords, $referenceWords);
        $similarity = round((count($intersection) / count($referenceWords)) * 100, 2);

        return [
            'similarity_score' => $similarity,
            'meets_goal' => $similarity >= 60.0,
            'feedback' => "[EVALUACIÓN SIMULADA] Coincidencia léxica aproximada: {$similarity}%. "
                .'Sustituir por el driver de IA real antes de usarse en producción.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function wordSet(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        $words = preg_split('/\W+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($words));
    }
}
