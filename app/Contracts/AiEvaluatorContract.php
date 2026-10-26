<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AiEvaluatorContract.php
 * Ubicación:   app/Contracts/AiEvaluatorContract.php
 * Descripción: Contrato del evaluador de IA para preguntas abiertas/código
 *              (RW-09, RW-10). Permite cambiar de driver (mock -> Gemini)
 *              sin tocar el resto del código. Implementación real pendiente
 *              de integrar (fuera de alcance de esta entrega de urgencia);
 *              MockAiEvaluator la sustituye para que la demo no dependa de
 *              una llave de API externa.
 * ============================================================================
 */

namespace App\Contracts;

interface AiEvaluatorContract
{
    /**
     * Evalúa una respuesta libre (abierta o código) contra la respuesta de
     * referencia del docente.
     *
     * @return array{similarity_score: float, meets_goal: bool, feedback: string}
     */
    public function evaluate(string $studentAnswer, string $referenceAnswer): array;
}
