<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GeminiAiEvaluator.php
 * Ubicación:      app/Services/Ai/GeminiAiEvaluator.php
 * Requerimiento:  RF-EVA-004, RF-EVA-005, RF-EVA-008, RF-IAR-001..014,
 *                 RNF-PRI-001, RNF-CON-002, ADR-041, ADR-059
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 08/11/2026
 * Descripción:    Driver de evaluación semántica conectado a Google Gemini
 *                 (gemini-3.5-flash-lite por defecto) con contexto pedagógico
 *                 estricto sin PII (LFPDPPP), Circuit Breaker en caché,
 *                 esquema JSON estructurado y degradación resiliente a
 *                 MockAiEvaluator sin consumo de créditos.
 * ============================================================================
 */

namespace App\Services\Ai;

use App\Contracts\AiEvaluatorContract;
use App\Services\MockAiEvaluator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Evaluador semántico asistido por Google Gemini con Circuit Breaker
 * en caché y fallback transparente a MockAiEvaluator.
 *
 * @package App\Services\Ai
 */
class GeminiAiEvaluator implements AiEvaluatorContract
{
    /**
     * Endpoint base oficial de la API generativa de Google Gemini.
     */
    private const GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    public const DEFAULT_MODEL = 'gemini-3.5-flash-lite';

    /**
     * Clave de caché para la marca temporal de apertura del Circuit Breaker.
     */
    public const CIRCUIT_OPEN_UNTIL_KEY = 'ai:gemini:circuit_open_until';

    /**
     * Clave de caché para el contador consecutivo de fallas del Circuit Breaker.
     */
    public const CIRCUIT_FAILURES_KEY = 'ai:gemini:consecutive_failures';

    /**
     * Lista blanca estricta de campos pedagógicos permitidos en el prompt (RNF-PRI-001).
     *
     * @var list<string>
     */
    private const ALLOWED_CONTEXT_KEYS = [
        'question_type',
        'stem',
        'evaluation_rubric',
        'programming_language',
        'instructions',
        'is_find_error',
        'starter_code',
        'points_weight',
    ];

    /**
     * Inicializa el evaluador Gemini permitiendo sobreescritura opcional por constructor.
     *
     * @param MockAiEvaluator $fallbackEvaluator Driver local de contingencia.
     * @param string|null $apiKey Llave de API opcional; si es null se resuelve de configuración.
     * @param string|null $model Identificador del modelo; si es null usa config('services.gemini.model').
     * @param int|null $timeoutSeconds Tiempo límite HTTP; si es null usa config('services.gemini.timeout').
     */
    public function __construct(
        private readonly MockAiEvaluator $fallbackEvaluator,
        private readonly ?string $apiKey = null,
        private readonly ?string $model = null,
        private readonly ?int $timeoutSeconds = null,
    ) {
    }

    /**
     * Evalúa la respuesta libre o código del alumno contra la referencia docente
     * utilizando Google Gemini con salida JSON estructurada, Circuit Breaker y fallback.
     *
     * @param string $studentAnswer Texto o código capturado por el estudiante.
     * @param string $referenceAnswer Respuesta o solución esperada por el docente.
     * @param array<string, mixed> $context Contexto pedagógico sin datos personales.
     * @return array{similarity_score: float, meets_goal: bool, feedback: string, is_fallback: bool, model: string}
     */
    public function evaluate(string $studentAnswer, string $referenceAnswer, array $context = []): array
    {
        $cleanStudent = trim($studentAnswer);
        $cleanReference = trim($referenceAnswer);

        if ($cleanStudent === '') {
            return [
                'similarity_score' => 0.0,
                'meets_goal' => false,
                'feedback' => 'Respuesta en blanco; se asigna puntaje 0.',
                'is_fallback' => false,
                'model' => 'none',
            ];
        }

        $apiKey = trim($this->apiKey ?? (string) config('services.gemini.api_key', ''));

        // Si no hay API Key configurada, degrada al evaluador de contingencia local sin costo.
        if ($apiKey === '') {
            return $this->buildFallbackResult($studentAnswer, $referenceAnswer, $context, false);
        }

        $resolvedModel = $this->resolveModel();
        $resolvedTimeout = $this->resolveTimeoutSeconds();

        if ($this->isCircuitOpen()) {
            Log::warning('Gemini AI Evaluator circuit breaker is open; executing contingency fallback', [
                'model' => $resolvedModel,
                'circuit_open_until' => Cache::get(self::CIRCUIT_OPEN_UNTIL_KEY),
            ]);

            return $this->buildFallbackResult($studentAnswer, $referenceAnswer, $context, true);
        }

        try {
            $identifiers = $context['local_privacy_identifiers'] ?? [];
            $sanitizedContext = $this->sanitizeContext($context, $identifiers);
            $result = $this->callGeminiApi(
                EvaluationPrivacyFilter::minimize($cleanStudent, $identifiers),
                EvaluationPrivacyFilter::minimize($cleanReference, $identifiers),
                $apiKey,
                $resolvedModel,
                $resolvedTimeout,
                $sanitizedContext,
            );

            $this->resetCircuitBreaker();

            return $result;
        } catch (Throwable $e) {
            $failures = $this->recordCircuitFailure();

            Log::warning('Gemini AI Evaluator unavailable or failed; executing fallback', [
                'failure_type' => $e::class,
                'failure_code' => $e->getCode(),
                'model' => $resolvedModel,
                'consecutive_failures' => $failures,
            ]);

            return $this->buildFallbackResult($studentAnswer, $referenceAnswer, $context, true);
        }
    }

    /**
     * Ejecuta la llamada HTTP a Google Gemini API minimizando datos personales (RNF-PRI-001).
     *
     * @param string $studentAnswer Respuesta depurada del estudiante.
     * @param string $referenceAnswer Referencia depurada del docente.
     * @param string $apiKey Llave de API de Gemini.
     * @param string $resolvedModel Identificador efectivo del modelo Gemini.
     * @param int $resolvedTimeout Tiempo máximo de espera HTTP en segundos.
     * @param array<string, mixed> $sanitizedContext Contexto pedagógico filtrado por whitelist.
     * @return array{similarity_score: float, meets_goal: bool, feedback: string, is_fallback: bool, model: string}
     */
    private function callGeminiApi(
        string $studentAnswer,
        string $referenceAnswer,
        string $apiKey,
        string $resolvedModel,
        int $resolvedTimeout,
        array $sanitizedContext,
    ): array {
        $endpoint = sprintf('%s/%s:generateContent', self::GEMINI_BASE_URL, rawurlencode($resolvedModel));
        $prompt = $this->buildPrompt($studentAnswer, $referenceAnswer, $sanitizedContext);

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
                'responseFormat' => [
                    'text' => [
                        'mimeType' => 'APPLICATION_JSON',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'similarity_score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                                'meets_goal' => ['type' => 'boolean', 'description' => 'Cumple el objetivo si el puntaje es de al menos 60'],
                                'feedback' => ['type' => 'string', 'description' => 'Justificación técnica de la calificación sugerida'],
                            ],
                            'required' => ['similarity_score', 'meets_goal', 'feedback'],
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::timeout($resolvedTimeout)
            ->withoutRedirecting()
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini API rechazó la solicitud.', $response->status());
        }

        return $this->parseEvaluationResponse($response->json('candidates.0'), $resolvedModel);
    }

    /**
     * Valida la evaluación externa antes de permitir que el servicio cobre el crédito.
     *
     * @return array{similarity_score: float, meets_goal: bool, feedback: string, is_fallback: bool, model: string}
     * @throws RuntimeException Si el proveedor devuelve una evaluación incompleta o inválida.
     */
    private function parseEvaluationResponse(mixed $candidate, string $model): array
    {
        $parts = $candidate['content']['parts'] ?? null;

        if (! is_array($parts) || ($candidate['finishReason'] ?? 'STOP') !== 'STOP') {
            throw new RuntimeException('Respuesta vacía o formato desconocido devuelto por Gemini API.');
        }

        $text = '';
        foreach ($parts as $part) {
            if (($part['thought'] ?? false) !== true && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        $evaluation = json_decode($text, true);
        $score = $evaluation['similarity_score'] ?? null;
        $meetsGoal = $evaluation['meets_goal'] ?? null;
        $feedback = $evaluation['feedback'] ?? null;

        if (
            (! is_int($score) && ! is_float($score))
            || ! is_finite((float) $score) || $score < 0 || $score > 100
            || ! is_bool($meetsGoal)
            || ! is_string($feedback) || trim($feedback) === ''
        ) {
            throw new RuntimeException('La evaluación devuelta por Gemini no cumple el esquema solicitado.');
        }

        return [
            'similarity_score' => round((float) $score, 2),
            'meets_goal' => $meetsGoal,
            'feedback' => trim($feedback),
            'is_fallback' => false,
            'model' => $model,
        ];
    }

    /**
     * Construye el prompt pedagógico anónimo incorporando únicamente campos
     * autorizados de la lista blanca (RNF-PRI-001 LFPDPPP).
     *
     * @param string $studentAnswer Respuesta capturada.
     * @param string $referenceAnswer Solución modelo del docente.
     * @param array<string, mixed> $context Contexto pedagógico ya sanitizado.
     * @return string
     */
    private function buildPrompt(string $studentAnswer, string $referenceAnswer, array $context): string
    {
        $contextLines = [];

        if (isset($context['question_type']) && is_string($context['question_type']) && $context['question_type'] !== '') {
            $typeLabel = $context['question_type'] === 'code' ? 'Código fuente / programación' : 'Pregunta abierta';
            $contextLines[] = "- Tipo de reactivo: {$typeLabel}";
        }

        if (isset($context['stem']) && is_string($context['stem']) && trim($context['stem']) !== '') {
            $contextLines[] = '- Enunciado del reactivo: '.trim($context['stem']);
        }

        if (isset($context['points_weight']) && is_numeric($context['points_weight'])) {
            $contextLines[] = '- Ponderación máxima del reactivo: '.number_format((float) $context['points_weight'], 2).' pts';
        }

        if (isset($context['evaluation_rubric']) && is_string($context['evaluation_rubric']) && trim($context['evaluation_rubric']) !== '') {
            $contextLines[] = '- Rúbrica de evaluación docente: '.trim($context['evaluation_rubric']);
        }

        if (isset($context['programming_language']) && is_string($context['programming_language']) && trim($context['programming_language']) !== '') {
            $contextLines[] = '- Lenguaje de programación: '.trim($context['programming_language']);
        }

        if (array_key_exists('is_find_error', $context)) {
            $modeLabel = (bool) $context['is_find_error']
                ? 'Detección y corrección de errores sobre código base'
                : 'Implementación desde cero o completado';
            $contextLines[] = "- Modalidad del ejercicio de código: {$modeLabel}";
        }

        if (isset($context['instructions']) && is_string($context['instructions']) && trim($context['instructions']) !== '') {
            $contextLines[] = '- Instrucciones técnicas: '.trim($context['instructions']);
        }

        if (isset($context['starter_code']) && is_string($context['starter_code']) && trim($context['starter_code']) !== '') {
            $contextLines[] = "- Código inicial entregado:\n\"\"\"".trim($context['starter_code']).'"""';
        }

        $contextSection = $contextLines !== []
            ? "\nCONTEXTO PEDAGÓGICO DEL REACTIVO:\n".implode("\n", $contextLines)."\n"
            : '';

        return <<<PROMPT
Eres un evaluador académico experto en la plataforma evaluadora de A-Code Solutions.
Tu función es calificar de forma objetiva y constructiva la respuesta libre o código proporcionado, comparándola semántica y conceptualmente contra la respuesta de referencia del docente.

CRITERIOS DE EVALUACIÓN:
1. Compara la validez conceptual, precisión terminológica, lógica (si aplica código) y cumplimiento de los objetivos clave descritos en la referencia y en la rúbrica.
2. Asigna un porcentaje de similitud o cumplimiento conceptual de 0.0 a 100.0 ("similarity_score").
3. Determina si cumple el objetivo esencial del reactivo ("meets_goal": true si score >= 60.0, false si < 60.0).
4. Proporciona una justificación técnica concisa de 1 a 3 oraciones en español ("feedback") explicando qué aspectos clave se cubrieron o cuáles faltaron.
5. Los identificadores sustituidos representan datos de ejemplo y no son criterios de evaluación.
6. NO incluyas markdown adicional fuera del JSON estructurado.
{$contextSection}
RESPUESTA DE REFERENCIA DOCENTE:
"""{$referenceAnswer}"""

RESPUESTA CAPTURADA A EVALUAR:
"""{$studentAnswer}"""
PROMPT;
    }

    /**
     * Selecciona los campos pedagógicos y minimiza el texto de cada uno.
     *
     * @param array<string, mixed> $context Contexto original.
     * @param array<int|string, string> $identifiers Identificadores conocidos por la aplicación.
     * @return array<string, mixed> Contexto que se puede incluir en la solicitud.
     */
    private function sanitizeContext(array $context, array $identifiers): array
    {
        $allowed = array_intersect_key($context, array_flip(self::ALLOWED_CONTEXT_KEYS));

        return array_map(static fn (mixed $value): mixed => is_string($value)
            ? EvaluationPrivacyFilter::minimize($value, $identifiers)
            : $value, $allowed);
    }

    /**
     * Resuelve el modelo Gemini efectivo desde el constructor o config/services.php.
     *
     * @return string
     */
    private function resolveModel(): string
    {
        if ($this->model !== null && trim($this->model) !== '') {
            return trim($this->model);
        }

        $configured = trim((string) config('services.gemini.model', self::DEFAULT_MODEL));

        return $configured !== '' ? $configured : self::DEFAULT_MODEL;
    }

    /**
     * Resuelve el timeout HTTP efectivo desde el constructor o config/services.php.
     *
     * @return int
     */
    private function resolveTimeoutSeconds(): int
    {
        if ($this->timeoutSeconds !== null && $this->timeoutSeconds > 0) {
            return $this->timeoutSeconds;
        }

        return max(1, (int) config('services.gemini.timeout', 15));
    }

    /**
     * Determina si el Circuit Breaker se encuentra abierto en la ventana de enfriamiento.
     *
     * @return bool
     */
    private function isCircuitOpen(): bool
    {
        $openUntil = Cache::get(self::CIRCUIT_OPEN_UNTIL_KEY);

        return is_numeric($openUntil) && (int) $openUntil > now()->timestamp;
    }

    /**
     * Incrementa el contador de fallas consecutivas y abre el circuito si se
     * alcanza el umbral configurado.
     *
     * @return int Total de fallas consecutivas tras el incremento.
     */
    private function recordCircuitFailure(): int
    {
        $threshold = max(1, (int) config('services.gemini.circuit_failure_threshold', 3));
        $cooldownSeconds = max(5, (int) config('services.gemini.circuit_cooldown_seconds', 60));

        $failures = ((int) Cache::get(self::CIRCUIT_FAILURES_KEY, 0)) + 1;
        Cache::put(self::CIRCUIT_FAILURES_KEY, $failures, now()->addSeconds($cooldownSeconds * 2));

        if ($failures >= $threshold) {
            Cache::put(
                self::CIRCUIT_OPEN_UNTIL_KEY,
                now()->addSeconds($cooldownSeconds)->timestamp,
                now()->addSeconds($cooldownSeconds),
            );
        }

        return $failures;
    }

    /**
     * Restablece los contadores del Circuit Breaker tras una respuesta exitosa.
     *
     * @return void
     */
    private function resetCircuitBreaker(): void
    {
        Cache::forget(self::CIRCUIT_FAILURES_KEY);
        Cache::forget(self::CIRCUIT_OPEN_UNTIL_KEY);
    }

    /**
     * Construye una respuesta de fallback estandarizada garantizando los metadatos
     * de contingencia para no descontar créditos de IA (RF-IAR-011).
     *
     * @param string $studentAnswer Respuesta del alumno.
     * @param string $referenceAnswer Referencia docente.
     * @param array<string, mixed> $context Contexto pedagógico.
     * @param bool $withContingencyPrefix Agrega el prefijo de degradación por contingencia.
     * @return array{similarity_score: float, meets_goal: bool, feedback: string, is_fallback: bool, model: string}
     */
    private function buildFallbackResult(
        string $studentAnswer,
        string $referenceAnswer,
        array $context,
        bool $withContingencyPrefix,
    ): array {
        $fallbackResult = $this->fallbackEvaluator->evaluate($studentAnswer, $referenceAnswer, $context);
        $feedback = (string) $fallbackResult['feedback'];

        if ($withContingencyPrefix && ! str_starts_with($feedback, '[Degradación por Contingencia] ')) {
            $feedback = '[Degradación por Contingencia] '.$feedback;
        }

        return [
            'similarity_score' => (float) $fallbackResult['similarity_score'],
            'meets_goal' => (bool) $fallbackResult['meets_goal'],
            'feedback' => $feedback,
            'is_fallback' => true,
            'model' => 'mock-local',
        ];
    }
}
