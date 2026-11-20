<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AiEvaluatorContract.php
 * Ubicación:      app/Contracts/AiEvaluatorContract.php
 * Requerimiento:  RF-EVA-004, RF-EVA-005, RF-IAR-001..014, RNF-PRI-001, ADR-059
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Contrato del evaluador semántico asistido por IA para
 *                 reactivos abiertos y de código fuente. Admite contexto
 *                 estrictamente pedagógico sin PII (LFPDPPP) e informa si el
 *                 resultado proviene de un driver de contingencia (fallback).
 * ============================================================================
 */

namespace App\Contracts;

/**
 * Contrato para los drivers de evaluación asistida por inteligencia artificial.
 *
 * @package App\Contracts
 */
interface AiEvaluatorContract
{
    /**
     * Evalúa una respuesta libre (abierta o código) contra la respuesta de
     * referencia del docente usando opcionalmente contexto pedagógico sin PII.
     *
     * @param string $studentAnswer Respuesta o código capturado por el estudiante.
     * @param string $referenceAnswer Respuesta o solución de referencia del docente.
     * @param array<string, mixed> $context Contexto estrictamente pedagógico (sin datos personales).
     * @return array{similarity_score: float, meets_goal: bool, feedback: string, is_fallback?: bool, model?: string}
     */
    public function evaluate(string $studentAnswer, string $referenceAnswer, array $context = []): array;
}
