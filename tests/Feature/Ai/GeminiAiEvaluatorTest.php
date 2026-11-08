<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GeminiAiEvaluatorTest.php
 * Ubicación:      tests/Feature/Ai/GeminiAiEvaluatorTest.php
 * Requerimiento:  RF-EVA-004, RF-EVA-005, RF-IAR-002, RNF-PRI-001, RNF-CON-002
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Pruebas automatizadas del evaluador asistido por IA Google Gemini
 *                 1.5 Flash: evaluación semántica, minimización de datos (LFPDPPP)
 *                 y degradación transparente (fallback).
 * ============================================================================
 */

namespace Tests\Feature\Ai;

use App\Contracts\AiEvaluatorContract;
use App\Services\Ai\GeminiAiEvaluator;
use App\Services\MockAiEvaluator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiAiEvaluatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.ai.driver', 'gemini');
        Config::set('services.gemini.api_key', 'test-gemini-api-key-12345');
        Config::set('services.gemini.model', 'gemini-1.5-flash');
        Config::set('services.gemini.timeout', 10);
    }

    public function test_evaluates_answer_successfully_with_gemini_api(): void
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
            referenceAnswer: 'Mecanismo de POO donde una clase derivada hereda comportamiento y estado de una clase base.'
        );

        $this->assertSame(88.5, $result['similarity_score']);
        $this->assertTrue($result['meets_goal']);
        $this->assertSame('El estudiante explica adecuadamente la relación de herencia y abstracción.', $result['feedback']);

        // Verificar cumplimiento de RNF-PRI-001: No deben enviarse datos personales a Gemini
        Http::assertSent(function (Request $request) {
            $body = (string) $request->body();
            $this->assertStringContainsString('La herencia permite compartir', $body);
            $this->assertStringNotContainsString('student_id', $body);
            $this->assertStringNotContainsString('email', $body);
            $this->assertStringNotContainsString('matricula', $body);
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
        $this->assertStringStartsWith('[Degradación por Contingencia]', $result['feedback']);
    }
}
