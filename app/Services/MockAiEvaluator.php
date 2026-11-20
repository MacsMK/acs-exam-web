<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        MockAiEvaluator.php
 * Ubicación:      app/Services/MockAiEvaluator.php
 * Requerimiento:  RF-EVA-004, RF-IAR-002, RF-IAR-011, ADR-002, ADR-059
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Driver local de contingencia ("mock-local") de
 *                 AiEvaluatorContract para desarrollo, demostración sin llave
 *                 y fallback de resiliencia. Marca siempre is_fallback = true
 *                 para garantizar que no descuente créditos de IA (RF-IAR-011).
 * ============================================================================
 */

namespace App\Services;

use App\Contracts\AiEvaluatorContract;

/**
 * Evaluador léxico local utilizado en modo demo o contingencia sin consumo de créditos.
 *
 * @package App\Services
 */
class MockAiEvaluator implements AiEvaluatorContract
{
    /**
     * Estima la similitud léxica local entre la respuesta del alumno y la referencia.
     *
     * @param string $studentAnswer Respuesta o código proporcionado por el estudiante.
     * @param string $referenceAnswer Respuesta o solución esperada por el docente.
     * @param array<string, mixed> $context Contexto pedagógico opcional (ignorado en modo léxico).
     * @return array{similarity_score: float, meets_goal: bool, feedback: string, is_fallback: bool, model: string}
     */
    public function evaluate(string $studentAnswer, string $referenceAnswer, array $context = []): array
    {
        $studentWords = $this->wordSet($studentAnswer);
        $referenceWords = $this->wordSet($referenceAnswer);

        if ($referenceWords === []) {
            return [
                'similarity_score' => 0.0,
                'meets_goal' => false,
                'feedback' => 'Sin respuesta de referencia.',
                'is_fallback' => true,
                'model' => 'mock-local',
            ];
        }

        $intersection = array_intersect($studentWords, $referenceWords);
        $similarity = round((count($intersection) / count($referenceWords)) * 100, 2);

        return [
            'similarity_score' => $similarity,
            'meets_goal' => $similarity >= 60.0,
            'feedback' => "[EVALUACIÓN SIMULADA] Coincidencia léxica aproximada: {$similarity}%. "
                .'Sustituir por el driver de IA real antes de usarse en producción.',
            'is_fallback' => true,
            'model' => 'mock-local',
        ];
    }

    /**
     * Normaliza el texto en un conjunto de palabras únicas en minúsculas.
     *
     * @param string $text Cadena de entrada a tokenizar.
     * @return array<int, string>
     */
    private function wordSet(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        $words = preg_split('/\W+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($words));
    }
}
