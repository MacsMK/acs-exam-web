<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GeminiAiEvaluator.php
 * Ubicación:      app/Services/Ai/GeminiAiEvaluator.php
 * Requerimiento:  RF-EVA-004, RF-EVA-005, RF-IAR-002, RNF-PRI-001, RNF-CON-002
 * Autor:          Equipo de Ingeniería (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Driver de evaluación asistida por IA conectado a la API de
 *                 Google Gemini (1.5 Flash) con esquema JSON estructurado y
 *                 fallback resiliente a MockAiEvaluator.
 * ============================================================================
 */

namespace App\Services\Ai;

use Throwable;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Contracts\AiEvaluatorContract;
use App\Services\MockAiEvaluator;

/**
 * Evaluador semántico asistido por IA Google Gemini 1.5 Flash con salida JSON
 * estructurada y fallback transparente a MockAiEvaluator.
 *
 * @package App\Services\Ai
 */
class GeminiAiEvaluator implements AiEvaluatorContract
{
    private const GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly MockAiEvaluator $fallbackEvaluator,
        private readonly ?string $apiKey = null,
        private readonly string $model = 'gemini-1.5-flash',
        private readonly int $timeoutSeconds = 15,
    ) {
    }

    /**
     * Evalúa la respuesta libre del alumno contra la respuesta de referencia docente
     * utilizando Google Gemini 1.5 Flash con salida JSON estructurada y fallback resiliente.
     *
     * @return array{similarity_score: float, meets_goal: bool, feedback: string}
     */
    public function evaluate(string $studentAnswer, string $referenceAnswer): array
    {
        $cleanStudent = trim($studentAnswer);
        $cleanReference = trim($referenceAnswer);

        if ($cleanStudent === '') {
            return [
                'similarity_score' => 0.0,
                'meets_goal' => false,
                'feedback' => 'Respuesta en blanco; se asigna puntaje 0.',
            ];
        }

        $apiKey = $this->apiKey ?? (string) config('services.gemini.api_key', '');

        // Si no hay API Key configurada, degrada al evaluador de contingencia (Mock)
        if ($apiKey === '') {
            return $this->fallbackEvaluator->evaluate($studentAnswer, $referenceAnswer);
        }

        try {
            return $this->callGeminiApi($cleanStudent, $cleanReference, $apiKey);
        } catch (Throwable $e) {
            Log::warning('Gemini AI Evaluator unavailable or failed; executing fallback', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);

            $fallbackResult = $this->fallbackEvaluator->evaluate($studentAnswer, $referenceAnswer);
            $fallbackResult['feedback'] = '[Degradación por Contingencia] ' . $fallbackResult['feedback'];

            return $fallbackResult;
        }
    }

    /**
     * Ejecuta la llamada HTTP a Google Gemini API minimizando datos personales (RNF-PRI-001).
     *
     * @return array{similarity_score: float, meets_goal: bool, feedback: string}
     */
    private function callGeminiApi(string $studentAnswer, string $referenceAnswer, string $apiKey): array
    {
        $endpoint = sprintf('%s/%s:generateContent?key=%s', self::GEMINI_BASE_URL, $this->model, $apiKey);

        $prompt = <<<PROMPT
Eres un evaluador académico experto en la plataforma evaluadora de A-Code Solutions.
Tu función es calificar de forma objetiva y constructiva la respuesta libre o código proporcionado por un alumno, comparándola semántica y conceptualmente contra la respuesta de referencia del docente.

CRITERIOS DE EVALUACIÓN:
1. Compara la validez conceptual, precisión terminológica y cumplimiento de los objetivos clave descritos en la referencia.
2. Asigna un porcentaje de similitud o cumplimiento conceptual de 0.0 a 100.0 ("similarity_score").
3. Determina si cumple el objetivo esencial del reactivo ("meets_goal": true si score >= 60.0, false si < 60.0).
4. Proporciona una justificación técnica concisa de 1 a 3 oraciones ("feedback") explicando qué aspectos clave se cubrieron o cuáles faltaron.
5. NO incluyas markdown adicional fuera del JSON estructurado.

RESPUESTA DE REFERENCIA DOCENTE:
"""{$referenceAnswer}"""

RESPUESTA CAPTURADA DEL ALUMNO:
"""{$studentAnswer}"""
PROMPT;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.2,
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'similarity_score' => [
                            'type' => 'NUMBER',
                            'description' => 'Porcentaje de similitud conceptual de 0 a 100',
                        ],
                        'meets_goal' => [
                            'type' => 'BOOLEAN',
                            'description' => 'Indica si la respuesta cumple el objetivo (score >= 60)',
                        ],
                        'feedback' => [
                            'type' => 'STRING',
                            'description' => 'Justificación técnica y constructiva de la calificación sugerida',
                        ],
                    ],
                    'required' => ['similarity_score', 'meets_goal', 'feedback'],
                ],
            ],
        ];

        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf('Gemini API HTTP Error %d: %s', $response->status(), $response->body()));
        }

        $body = $response->json();
        $textOutput = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($textOutput === null || ! is_string($textOutput)) {
            throw new \RuntimeException('Respuesta vacía o formato desconocido devuelto por Gemini API.');
        }

        /** @var array{similarity_score?: float|int, meets_goal?: bool, feedback?: string}|null $parsed */
        $parsed = json_decode($textOutput, true);

        if (! is_array($parsed) || ! isset($parsed['similarity_score'], $parsed['meets_goal'])) {
            throw new \RuntimeException('El payload devuelto por Gemini no cumplió el esquema JSON solicitado.');
        }

        $score = round((float) $parsed['similarity_score'], 2);
        // Garantizar límites matemáticos [0.0, 100.0]
        $score = max(0.0, min(100.0, $score));

        return [
            'similarity_score' => $score,
            'meets_goal' => (bool) $parsed['meets_goal'],
            'feedback' => (string) ($parsed['feedback'] ?? 'Evaluación semántica generada por IA.'),
        ];
    }
}
