<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GeminiAiEvaluatorTest.php
 * Ubicación:      tests/Feature/Ai/GeminiAiEvaluatorTest.php
 * Requerimiento:  RF-EVA-004, RF-EVA-005, RF-EVA-008, RF-IAR-002, RF-IAR-011,
 *                 RNF-PRI-001, RNF-CON-002, ADR-041, ADR-059
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Descripción:    Pruebas automatizadas del evaluador asistido por IA Google
 *                 Gemini (modelo predeterminado gemini-3.5-flash-lite): evaluación
 *                 semántica con contexto pedagógico sin PII (LFPDPPP),
 *                 bandera is_fallback, degradación resiliente y Circuit Breaker.
 * ============================================================================
 */

namespace Tests\Feature\Ai;

use App\Contracts\AiEvaluatorContract;
use App\Services\Ai\GeminiAiEvaluator;
use App\Services\MockAiEvaluator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeminiAiEvaluatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('services.ai.driver', 'gemini');
        Config::set('services.gemini.api_key', 'test-gemini-api-key-12345');
        Config::set('services.gemini.model', 'gemini-2.5-flash');
        Config::set('services.gemini.timeout', 10);
        Config::set('services.gemini.circuit_failure_threshold', 3);
        Config::set('services.gemini.circuit_cooldown_seconds', 60);
        Http::preventStrayRequests();
    }

    public function test_uses_gemini_3_5_flash_lite_with_structured_output_and_secure_authentication(): void
    {
        Config::set('services.gemini.model', '');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'STOP',
                        'content' => [
                            'parts' => [
                                ['thought' => true, 'text' => 'Texto interno ajeno a la sugerencia.'],
                                ['text' => '{"similarity_score":92,"meets_goal":true,'],
                                ['text' => '"feedback":"Respuesta clara y precisa."}'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        /** @var GeminiAiEvaluator $evaluator */
        $evaluator = $this->app->make(AiEvaluatorContract::class);
        $this->assertInstanceOf(GeminiAiEvaluator::class, $evaluator);

        $result = $evaluator->evaluate(
            studentAnswer: 'El polimorfismo permite invocar distintos comportamientos bajo una misma interfaz.',
            referenceAnswer: 'Capacidad de objetos de distintas clases de responder al mismo mensaje de manera específica.'
        );

        $this->assertSame(92.0, $result['similarity_score']);
        $this->assertTrue($result['meets_goal']);
        $this->assertSame('Respuesta clara y precisa.', $result['feedback']);
        $this->assertFalse($result['is_fallback']);
        $this->assertSame('gemini-3.5-flash-lite', $result['model']);

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertSame(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent',
                $request->url(),
            );
            $this->assertTrue($request->hasHeader('x-goog-api-key', 'test-gemini-api-key-12345'));

            $generationConfig = $request['generationConfig'];
            $this->assertArrayNotHasKey('temperature', $generationConfig);
            $this->assertArrayNotHasKey('responseSchema', $generationConfig);
            $text = $generationConfig['responseFormat']['text'];
            $this->assertSame('APPLICATION_JSON', $text['mimeType']);
            $this->assertSame('object', $text['schema']['type']);
            $this->assertEqualsCanonicalizing(['similarity_score', 'meets_goal', 'feedback'], $text['schema']['required']);
            $score = $text['schema']['properties']['similarity_score'];
            $this->assertSame('number', $score['type']);
            $this->assertEquals(0, $score['minimum']);
            $this->assertEquals(100, $score['maximum']);

            return true;
        });
    }

    public function test_evaluates_answer_successfully_with_pedagogical_context_and_zero_pii(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'similarity_score' => 88.5,
                                        'meets_goal' => true,
                                        'feedback' => 'El estudiante explica adecuadamente la relación de herencia y abstracción.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        /** @var GeminiAiEvaluator $evaluator */
        $evaluator = $this->app->make(AiEvaluatorContract::class);
        $this->assertInstanceOf(GeminiAiEvaluator::class, $evaluator);

        $result = $evaluator->evaluate(
            studentAnswer: 'La herencia permite compartir atributos y métodos entre clases padre e hijo.',
            referenceAnswer: 'Mecanismo de POO donde una clase derivada hereda comportamiento y estado de una clase base.',
            context: [
                'question_type' => 'code',
                'stem' => 'Corrige la función de ordenamiento en Python.',
                'evaluation_rubric' => 'Debe preservar estabilidad y complejidad O(n log n).',
                'programming_language' => 'Python',
                'instructions' => 'No uses librerías externas y corrige el índice fuera de rango.',
                'is_find_error' => true,
                'starter_code' => 'def sort_items(arr): return arr',
                'points_weight' => 25.0,
                // Claves PII inyectadas deliberadamente para certificar whitelist RNF-PRI-001
                'student_id' => 99999,
                'student_name' => 'Juan Carlos Pérez Privado',
                'email' => 'juan.privado@universidad.edu.mx',
                'matricula' => 'MAT-2026-SECRET',
            ],
        );

        $this->assertSame(88.5, $result['similarity_score']);
        $this->assertTrue($result['meets_goal']);
        $this->assertSame('El estudiante explica adecuadamente la relación de herencia y abstracción.', $result['feedback']);
        $this->assertFalse($result['is_fallback']);
        $this->assertSame('gemini-2.5-flash', $result['model']);

        // Verificar envío de contexto pedagógico y CERO PII (RNF-PRI-001)
        Http::assertSent(function (Request $request): bool {
            $body = (string) $request->body();
            $promptText = (string) ($request['contents'][0]['parts'][0]['text'] ?? '');

            // Contexto pedagógico autorizado
            $this->assertStringContainsString('La herencia permite compartir', $promptText);
            $this->assertStringContainsString('Corrige la función de ordenamiento en Python.', $promptText);
            $this->assertStringContainsString('Debe preservar estabilidad y complejidad O(n log n).', $promptText);
            $this->assertStringContainsString('Python', $promptText);
            $this->assertStringContainsString('No uses librerías externas y corrige el índice fuera de rango.', $promptText);
            $this->assertStringContainsString('Detección y corrección de errores sobre código base', $promptText);
            $this->assertStringContainsString('def sort_items(arr): return arr', $promptText);

            // Cero datos personales (LFPDPPP) tanto en prompt decodificado como en payload crudo
            foreach ([$promptText, $body] as $payload) {
                $this->assertStringNotContainsString('student_id', $payload);
                $this->assertStringNotContainsString('99999', $payload);
                $this->assertStringNotContainsString('Juan Carlos', $payload);
                $this->assertStringNotContainsString('juan.privado@universidad.edu.mx', $payload);
                $this->assertStringNotContainsString('MAT-2026-SECRET', $payload);
                $this->assertStringNotContainsString('matricula', $payload);
            }

            return true;
        });
    }

    public function test_empty_student_answer_returns_zero_score_without_calling_gemini(): void
    {
        Http::fake();

        /** @var GeminiAiEvaluator $evaluator */
        $evaluator = $this->app->make(AiEvaluatorContract::class);

        $result = $evaluator->evaluate('   ', 'Respuesta de referencia');

        $this->assertSame(0.0, $result['similarity_score']);
        $this->assertFalse($result['meets_goal']);
        $this->assertFalse($result['is_fallback']);
        $this->assertSame('none', $result['model']);
        $this->assertStringContainsString('en blanco', $result['feedback']);

        Http::assertNothingSent();
    }

    public function test_falls_back_to_mock_evaluator_when_api_key_is_empty(): void
    {
        Config::set('services.gemini.api_key', '');
        Http::fake();

        $mockEvaluator = new MockAiEvaluator();
        $evaluator = new GeminiAiEvaluator(
            fallbackEvaluator: $mockEvaluator,
            apiKey: '',
        );

        $result = $evaluator->evaluate(
            studentAnswer: 'La fotosíntesis convierte energía lumínica en energía química.',
            referenceAnswer: 'Proceso de conversión de energía lumínica a energía química en plantas.'
        );

        $this->assertArrayHasKey('similarity_score', $result);
        $this->assertArrayHasKey('meets_goal', $result);
        $this->assertArrayHasKey('feedback', $result);
        $this->assertGreaterThan(0.0, $result['similarity_score']);
        $this->assertTrue($result['is_fallback']);
        $this->assertSame('mock-local', $result['model']);

        Http::assertNothingSent();
    }

    public function test_falls_back_gracefully_when_gemini_api_returns_http_error(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 503,
                    'message' => 'Service Unavailable',
                ],
            ], 503),
        ]);

        /** @var GeminiAiEvaluator $evaluator */
        $evaluator = $this->app->make(AiEvaluatorContract::class);

        $result = $evaluator->evaluate(
            studentAnswer: 'Definición de algoritmo como secuencia finita de pasos.',
            referenceAnswer: 'Un algoritmo es un conjunto finito de instrucciones paso a paso.'
        );

        $this->assertArrayHasKey('similarity_score', $result);
        $this->assertArrayHasKey('meets_goal', $result);
        $this->assertTrue($result['is_fallback']);
        $this->assertSame('mock-local', $result['model']);
        $this->assertStringStartsWith('[Degradación por Contingencia]', $result['feedback']);
    }

    public function test_falls_back_gracefully_when_gemini_payload_is_malformed_json(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Texto libre sin formato json valido'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        /** @var GeminiAiEvaluator $evaluator */
        $evaluator = $this->app->make(AiEvaluatorContract::class);

        $result = $evaluator->evaluate(
            studentAnswer: 'Respuesta cualquiera',
            referenceAnswer: 'Respuesta docente'
        );

        $this->assertArrayHasKey('similarity_score', $result);
        $this->assertTrue($result['is_fallback']);
        $this->assertSame('mock-local', $result['model']);
        $this->assertStringStartsWith('[Degradación por Contingencia]', $result['feedback']);
    }

    #[DataProvider('invalidEvaluations')]
    public function test_rejects_incomplete_or_invalid_evaluations(array $evaluation, string $finishReason = 'STOP'): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'finishReason' => $finishReason,
                    'content' => ['parts' => [['text' => json_encode($evaluation, JSON_THROW_ON_ERROR)]]],
                ]],
            ]),
        ]);

        $evaluator = $this->app->make(GeminiAiEvaluator::class);
        $result = $evaluator->evaluate('Texto de prueba', 'Referencia de prueba');

        $this->assertTrue($result['is_fallback']);
        $this->assertSame('mock-local', $result['model']);
        Http::assertSentCount(1);
    }

    public static function invalidEvaluations(): array
    {
        return [
            'sin justificación' => [['similarity_score' => 85, 'meets_goal' => true]],
            'justificación vacía' => [['similarity_score' => 85, 'meets_goal' => true, 'feedback' => '  ']],
            'puntaje fuera de rango' => [['similarity_score' => 101, 'meets_goal' => true, 'feedback' => 'Explicación']],
            'puntaje textual' => [['similarity_score' => '85', 'meets_goal' => true, 'feedback' => 'Explicación']],
            'objetivo textual' => [['similarity_score' => 85, 'meets_goal' => 'true', 'feedback' => 'Explicación']],
            'respuesta truncada' => [
                ['similarity_score' => 85, 'meets_goal' => true, 'feedback' => 'Explicación'],
                'MAX_TOKENS',
            ],
        ];
    }

    public function test_circuit_breaker_opens_after_three_consecutive_503_errors_and_skips_fourth_http_call(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 503,
                    'message' => 'Service Unavailable',
                ],
            ], 503),
        ]);

        /** @var GeminiAiEvaluator $evaluator */
        $evaluator = $this->app->make(AiEvaluatorContract::class);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $res = $evaluator->evaluate(
                studentAnswer: "Intento {$attempt} de respuesta sobre bases de datos relacionales.",
                referenceAnswer: 'Un SGBD relacional organiza los datos en tablas relacionadas mediante llaves.'
            );

            $this->assertTrue($res['is_fallback']);
            $this->assertStringStartsWith('[Degradación por Contingencia]', $res['feedback']);
        }

        // Tras 3 fallos consecutivos el circuito está abierto; la 4ª llamada degrada al instante sin HTTP
        $fourthResult = $evaluator->evaluate(
            studentAnswer: 'Cuarta llamada con circuito abierto.',
            referenceAnswer: 'Un SGBD relacional organiza los datos en tablas relacionadas mediante llaves.'
        );

        $this->assertTrue($fourthResult['is_fallback']);
        $this->assertSame('mock-local', $fourthResult['model']);
        $this->assertStringStartsWith('[Degradación por Contingencia]', $fourthResult['feedback']);

        Http::assertSentCount(3);
    }
}
