<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AiOnDemandAndCreditsTest.php
 * Ubicación:      tests/Feature/Ai/AiOnDemandAndCreditsTest.php
 * Requerimiento:  RF-IAR-001, RF-IAR-002, RF-IAR-003, RF-IAR-010, RF-IAR-011,
 *                 RF-IAR-012, RF-IAR-014, RF-EVA-004, RF-EVA-005, RF-EVA-008,
 *                 RF-EVA-009, RF-EVA-013, ADR-059, ADR-060
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Certificación integral de la evaluación asistida por IA bajo
 *                 demanda (individual y en lote), descuento atómico de créditos
 *                 solo ante respuestas reales, protección ante contingencia 503
 *                 sin cobro, respuestas en blanco sin consumo, cupo Freemium
 *                 (25), plan Pro ilimitado, reevaluación con preferencia
 *                 dismiss_warning, aislamiento IDOR multi-docente y auditoría
 *                 de recoteje (teacher.grade_reviewed / teacher.grade_corrected).
 * ============================================================================
 */

namespace Tests\Feature\Ai;

use App\Contracts\AiEvaluatorContract;
use App\Models\AiCreditUsage;
use App\Models\AuditLog;
use App\Models\CodeItem;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MultipleChoiceItem;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use App\Services\AttemptService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiOnDemandAndCreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed(SubscriptionPackageSeeder::class);

        Config::set('queue.default', 'sync');
        Config::set('services.ai.driver', 'gemini');
        Config::set('services.gemini.api_key', 'test-gemini-api-key-12345');
        Config::set('services.gemini.model', 'gemini-2.5-flash');
        Config::set('services.gemini.timeout', 10);
        Config::set('services.gemini.circuit_failure_threshold', 3);
        Config::set('services.gemini.circuit_cooldown_seconds', 60);
        Http::preventStrayRequests();
    }

    public function test_credits_indicators_count_only_answers_with_a_saved_external_ai_result(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $attempt = $this->createSubmittedQualitativeAttempt($teacher, $student, includeBlankOpen: true);
        $anotherAttempt = $this->createSubmittedQualitativeAttempt($teacher, $student);
        $otherTeacher = User::factory()->teacher()->create();
        $otherTeachersAttempt = $this->createSubmittedQualitativeAttempt($otherTeacher, $student);

        $externalResult = [
            'ai_evaluated_at' => now()->subMonths(2),
            'ai_similarity_score' => 75,
            'ai_model_used' => 'gemini-2.5-flash',
        ];

        $attempt['open_answer']->update($externalResult);
        $attempt['code_answer']->update(array_replace($externalResult, [
            'ai_model_used' => null,
            'teacher_recoteje_score' => 0,
        ]));
        $attempt['blank_open_answer']->update(array_replace($externalResult, [
            'ai_model_used' => 'none',
            'ai_similarity_score' => 0,
        ]));
        $anotherAttempt['open_answer']->update(array_replace($externalResult, [
            'ai_is_fallback' => true,
            'ai_model_used' => 'mock-local',
            'teacher_recoteje_score' => 8,
        ]));
        $anotherAttempt['code_answer']->update(['teacher_recoteje_score' => 10]);
        $otherTeachersAttempt['open_answer']->update($externalResult);

        $creditsRoute = route('teacher.ai-credits.index');
        $this->actingAs($teacher)->get($creditsRoute)
            ->assertOk()
            ->assertViewHas('aiEvaluatedAnswersCount', 2)
            ->assertViewHas('aiReviewedAnswersCount', 1)
            ->assertSeeTextInOrder([
                'Respuestas con puntaje sugerido por IA',
                'Respuestas en las que guardaste un puntaje',
                '1 de 2',
            ]);

        $attempt['blank_open_answer']->update(['ai_model_used' => null]);
        $attempt['open_answer']->update(['ai_is_fallback' => true, 'ai_model_used' => 'mock-local']);

        $this->get($creditsRoute)
            ->assertOk()
            ->assertViewHas('aiEvaluatedAnswersCount', 1)
            ->assertViewHas('aiReviewedAnswersCount', 1)
            ->assertSeeText('1 de 1')
            ->assertDontSee('Promedio de similitud');
    }

    public function test_submitting_attempt_with_non_empty_open_and_code_answers_does_not_invoke_ai_or_consume_credits(): void
    {
        $aiCalls = 0;

        $this->app->instance(AiEvaluatorContract::class, new class($aiCalls) implements AiEvaluatorContract {
            public function __construct(private int &$calls)
            {
            }

            public function evaluate(string $studentAnswer, string $referenceAnswer, array $context = []): array
            {
                $this->calls++;

                return [
                    'similarity_score' => 95.0,
                    'meets_goal' => true,
                    'feedback' => 'No debería ejecutarse durante la entrega.',
                    'is_fallback' => false,
                    'model' => 'gemini-2.5-flash',
                ];
            }
        });
        $this->app->forgetInstance(AttemptService::class);

        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $fixture = $this->createQualitativeExamFixture($teacher, $student);

        /** @var AttemptService $attemptService */
        $attemptService = $this->app->make(AttemptService::class);
        $attempt = $attemptService->start($fixture['exam'], $student);

        $attemptService->submitAnswer(
            $attempt,
            $fixture['open_eq']->id,
            'La encapsulación oculta el estado interno mediante modificadores de acceso.',
        );
        $attemptService->submitAnswer(
            $attempt,
            $fixture['code_eq']->id,
            '<?php echo array_sum([1, 2, 3]);',
        );

        $submitted = $attemptService->submitFinal($attempt->fresh());

        $this->assertSame(0, $aiCalls);
        $this->assertDatabaseCount('acs_ai_credit_usages', 0);
        $this->assertSame('submitted', $submitted->status);
        $this->assertNull($submitted->final_score);
        $this->assertSame(2, (int) $submitted->pending_ai_count);

        $openAnswer = StudentAnswer::where('student_attempt_id', $submitted->id)
            ->where('exam_question_id', $fixture['open_eq']->id)
            ->firstOrFail();
        $codeAnswer = StudentAnswer::where('student_attempt_id', $submitted->id)
            ->where('exam_question_id', $fixture['code_eq']->id)
            ->firstOrFail();

        $this->assertNull($openAnswer->ai_evaluated_at);
        $this->assertNull($openAnswer->ai_similarity_score);
        $this->assertNull($codeAnswer->ai_evaluated_at);
        $this->assertNull($codeAnswer->ai_similarity_score);
    }

    public function test_owner_teacher_single_ai_evaluation_persists_gemini_result_deducts_one_credit_audits_and_keeps_final_score_null(): void
    {
        Config::set('services.gemini.model', 'gemini-3.5-flash-lite');
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => $this->geminiSuccessResponse(
                85.0,
                true,
                'El estudiante identifica correctamente la encapsulación y el control de visibilidad.',
            ),
        ]);

        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $fixture = $this->createSubmittedQualitativeAttempt($teacher, $student);

        $response = $this->actingAs($teacher)->post(
            route('exams.revision.evaluate-ai', [
                $fixture['exam'],
                $fixture['attempt'],
                $fixture['open_answer'],
            ]),
        );

        $response->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $openAnswer = $fixture['open_answer']->fresh();
        $attempt = $fixture['attempt']->fresh();

        $this->assertEquals(85.0, (float) $openAnswer->ai_similarity_score);
        $this->assertTrue((bool) $openAnswer->ai_meets_goal);
        $this->assertSame(
            'El estudiante identifica correctamente la encapsulación y el control de visibilidad.',
            $openAnswer->ai_feedback,
        );
        $this->assertFalse((bool) $openAnswer->ai_is_fallback);
        $this->assertSame('gemini-3.5-flash-lite', $openAnswer->ai_model_used);
        $this->assertNotNull($openAnswer->ai_evaluated_at);
        $this->assertEquals(42.5, (float) $openAnswer->points_awarded); // 85% de 50 pts

        // RF-EVA-009: NO publica final_score hasta que el docente realice el recoteje
        $this->assertNull($attempt->final_score);
        $this->assertSame(2, (int) $attempt->pending_ai_count);
        $this->assertEquals(42.5, (float) $attempt->ai_estimated_score);

        // Descuenta exactamente 1 crédito en acs_ai_credit_usages
        $this->assertDatabaseCount('acs_ai_credit_usages', 1);
        $this->assertDatabaseHas('acs_ai_credit_usages', [
            'teacher_id' => $teacher->id,
            'student_answer_id' => $openAnswer->id,
            'credits_consumed' => 1,
            'is_reevaluation' => false,
            'model_used' => 'gemini-3.5-flash-lite',
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        // Auditoría inmutable ai.answer_evaluated
        $audit = AuditLog::where('user_id', $teacher->id)
            ->where('action', 'ai.answer_evaluated')
            ->where('auditable_type', StudentAnswer::class)
            ->where('auditable_id', $openAnswer->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(1, $audit->new_values['credits_consumed'] ?? null);
        $this->assertSame('gemini-3.5-flash-lite', $audit->new_values['model_used'] ?? null);
    }

    /**
     * Comprueba que el transporte externo recibe una copia reducida y se conserva el original.
     *
     * @return void
     */
    public function test_external_evaluation_minimizes_free_text_without_changing_the_original(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://generativelanguage.googleapis.com/*' => $this->geminiSuccessResponse(90.0, true, 'Acierto técnico.')]);

        $teacher = User::factory()->teacher()->create(['first_name' => 'Ada', 'last_name' => 'López Yáñez']);
        $student = User::factory()->student()->create(['first_name' => 'Lucía', 'last_name' => 'Flores Cortés', 'phone' => '5512349876']);
        $fixture = $this->createSubmittedQualitativeAttempt($teacher, $student);
        $answer = $fixture['open_answer'];
        $question = $answer->examQuestion->question;
        $original = 'Lucía Flores Cortés usa funciones puras. Correo: lucia.ficticia@example.test. Teléfono: 5512349876';
        $storedStem = 'Explica la inmutabilidad de los datos. Correo: caso.ejemplo@example.test.';
        $referenceText = 'La referencia de Ada López Yáñez compara los efectos laterales. Matrícula: ABC-20260001';
        $answer->update(['answer_payload' => $original, 'served_stem_snapshot' => $storedStem]);
        $question->questionable->update(['reference_answer' => $referenceText]);

        $this->actingAs($teacher)->post(
            route('exams.revision.evaluate-ai', [$fixture['exam'], $fixture['attempt'], $answer]),
        )->assertSessionHasNoErrors();

        Http::assertSent(function (Request $request): bool {
            $prompt = (string) $request['contents'][0]['parts'][0]['text'];
            foreach (['Lucía Flores Cortés', 'Ada López Yáñez', 'lucia.ficticia@example.test', '5512349876',
                'caso.ejemplo@example.test', 'ABC-20260001', 'local_privacy_identifiers'] as $identifier) {
                $this->assertStringNotContainsString($identifier, $prompt);
            }
            foreach (['funciones puras', 'inmutabilidad de los datos', 'compara los efectos laterales'] as $topic) {
                $this->assertStringContainsString($topic, $prompt);
            }

            return true;
        });
        $this->assertSame($original, $answer->fresh()->answer_payload);
        $this->assertSame($storedStem, $answer->fresh()->served_stem_snapshot);
        $this->assertSame($referenceText, $question->questionable->fresh()->reference_answer);
        $this->assertEquals(90.0, $answer->fresh()->ai_similarity_score);
    }

    public function test_gemini_503_saves_contingency_fallback_and_consumes_zero_credits(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['code' => 503, 'message' => 'Service Unavailable'],
            ], 503),
        ]);

        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $fixture = $this->createSubmittedQualitativeAttempt($teacher, $student);

        $response = $this->actingAs($teacher)->post(
            route('exams.revision.evaluate-ai', [
                $fixture['exam'],
                $fixture['attempt'],
                $fixture['open_answer'],
            ]),
        );

        $response->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHas('warning');

        $openAnswer = $fixture['open_answer']->fresh();

        $this->assertTrue((bool) $openAnswer->ai_is_fallback);
        $this->assertSame('mock-local', $openAnswer->ai_model_used);
        $this->assertStringStartsWith('[Degradación por Contingencia]', (string) $openAnswer->ai_feedback);
        $this->assertNotNull($openAnswer->ai_evaluated_at);

        // RF-IAR-011: Contingencia local jamás descuenta créditos
        $this->assertDatabaseCount('acs_ai_credit_usages', 0);
        $this->assertDatabaseMissing('acs_audit_logs', [
            'action' => 'ai.answer_evaluated',
            'auditable_id' => $openAnswer->id,
        ]);
    }

    public function test_gemini_response_without_justification_uses_fallback_and_does_not_consume_credits(): void
    {
        $payload = $this->geminiSuccessPayload(85.0, true, 'Retroalimentación de prueba.');
        $payload['candidates'][0]['content']['parts'][0]['text'] = '{"similarity_score":85,"meets_goal":true}';
        Http::preventStrayRequests();
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response($payload)]);

        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $fixture = $this->createSubmittedQualitativeAttempt($teacher, $student);
        $answer = $fixture['open_answer'];

        $this->actingAs($teacher)->post(
            route('exams.revision.evaluate-ai', [$fixture['exam'], $fixture['attempt'], $answer]),
        )->assertSessionHas('warning');

        $this->assertTrue((bool) $answer->fresh()->ai_is_fallback);
        $this->assertDatabaseCount('acs_ai_credit_usages', 0);
        Http::assertSentCount(1);
    }

    public function test_blank_qualitative_answer_awards_zero_without_calling_gemini_or_consuming_credits(): void
    {
        Http::fake();

        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $fixture = $this->createSubmittedQualitativeAttempt($teacher, $student);

        $fixture['open_answer']->update([
            'answer_payload' => ['text' => '   '],
            'ai_evaluated_at' => null,
            'ai_similarity_score' => null,
            'ai_model_used' => null,
        ]);

        $response = $this->actingAs($teacher)->post(
            route('exams.revision.evaluate-ai', [
                $fixture['exam'],
                $fixture['attempt'],
                $fixture['open_answer'],
            ]),
        );

        $response->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHas('status');

        Http::assertNothingSent();
        $this->assertDatabaseCount('acs_ai_credit_usages', 0);

        $openAnswer = $fixture['open_answer']->fresh();
        $this->assertEquals(0.0, (float) $openAnswer->points_awarded);
        $this->assertEquals(0.0, (float) $openAnswer->ai_similarity_score);
        $this->assertFalse((bool) $openAnswer->ai_meets_goal);
        $this->assertFalse((bool) $openAnswer->ai_is_fallback);
        $this->assertSame('none', $openAnswer->ai_model_used);
    }

    public function test_batch_ai_evaluation_evaluates_all_pending_non_empty_qualitative_answers_and_deducts_exact_n_credits(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiSuccessPayload(80.0, true, 'Buena explicación abierta.'), 200)
                ->push($this->geminiSuccessPayload(90.0, true, 'Código funcional y limpio.'), 200),
        ]);

        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $fixture = $this->createSubmittedQualitativeAttempt($teacher, $student, includeBlankOpen: true, includeMultipleChoice: true);

        $response = $this->actingAs($teacher)->post(
            route('exams.revision.evaluate-ai-batch', [$fixture['exam'], $fixture['attempt']]),
        );

        $response->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        // Solo los 2 reactivos cualitativos no vacíos llaman a Gemini y consumen crédito
        Http::assertSentCount(2);
        $this->assertDatabaseCount('acs_ai_credit_usages', 2);

        $openAnswer = $fixture['open_answer']->fresh();
        $codeAnswer = $fixture['code_answer']->fresh();
        $blankAnswer = $fixture['blank_open_answer']->fresh();

        $this->assertEquals(80.0, (float) $openAnswer->ai_similarity_score);
        $this->assertFalse((bool) $openAnswer->ai_is_fallback);
        $this->assertSame('gemini-2.5-flash', $openAnswer->ai_model_used);

        $this->assertEquals(90.0, (float) $codeAnswer->ai_similarity_score);
        $this->assertFalse((bool) $codeAnswer->ai_is_fallback);
        $this->assertSame('gemini-2.5-flash', $codeAnswer->ai_model_used);

        // Respuesta en blanco se marcó en 0 sin crédito
        $this->assertSame('none', $blankAnswer->ai_model_used);
        $this->assertEquals(0.0, (float) $blankAnswer->points_awarded);
        $this->assertDatabaseMissing('acs_ai_credit_usages', [
            'student_answer_id' => $blankAnswer->id,
        ]);

        // Sigue sin publicarse final_score hasta recoteje
        $this->assertNull($fixture['attempt']->fresh()->final_score);
    }

    public function test_exhausted_freemium_credits_rejects_ai_evaluation_without_calling_gemini_but_allows_manual_recoteje(): void
    {
        Http::fake();

        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $fixture = $this->createSubmittedQualitativeAttempt($teacher, $student);

        // Consumir el cupo completo de Freemium (25 créditos)
        for ($i = 0; $i < 25; $i++) {
            AiCreditUsage::create([
                'teacher_id' => $teacher->id,
                'student_answer_id' => null,
                'credits_consumed' => 1,
                'is_reevaluation' => false,
                'model_used' => 'gemini-2.5-flash',
                'status' => AiCreditUsage::STATUS_CONSUMED,
            ]);
        }

        // Solicitud individual rechazada con error ai_credits y sin llamar a Gemini
        $this->actingAs($teacher)
            ->post(route('exams.revision.evaluate-ai', [
                $fixture['exam'],
                $fixture['attempt'],
                $fixture['open_answer'],
            ]))
            ->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHasErrors('ai_credits');

        // Solicitud en lote también rechazada con error ai_credits
        $this->actingAs($teacher)
            ->post(route('exams.revision.evaluate-ai-batch', [$fixture['exam'], $fixture['attempt']]))
            ->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHasErrors('ai_credits');

        Http::assertNothingSent();
        $this->assertDatabaseCount('acs_ai_credit_usages', 25);

        // RF-EVA-004 / RF-EVA-005: La calificación manual (recoteje) sigue operando sin requerir créditos IA
        $this->actingAs($teacher)
            ->post(route('exams.revision.update', [$fixture['exam'], $fixture['attempt']]), [
                'reviews' => [
                    [
                        'answer_id' => $fixture['open_answer']->id,
                        'score' => 40.0,
                        'notes' => 'Calificado manualmente sin créditos IA.',
                    ],
                    [
                        'answer_id' => $fixture['code_answer']->id,
                        'score' => 45.0,
                        'notes' => 'Código correcto evaluado manualmente.',
                    ],
                ],
            ])
            ->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $attempt = $fixture['attempt']->fresh();
        $this->assertSame(0, (int) $attempt->pending_ai_count);
        $this->assertEquals(8.5, (float) $attempt->final_score); // 85/100 en escala 0-10
    }

    public function test_credits_monitor_distinguishes_available_used_near_limit_and_exhausted_paid_quota(): void
    {
        $this->travelTo(Carbon::parse('2026-02-27 12:00:00'));
        $teacher = User::factory()->teacher()->create();
        $package = SubscriptionPackage::where('tier', 'basic')->firstOrFail();
        $subscription = TeacherSubscription::create([
            'teacher_id' => $teacher->id,
            'subscription_package_id' => $package->id,
            'payment_status' => 'paid',
            'starts_at' => '2026-01-31 10:00:00',
            'expires_at' => '2027-01-31 10:00:00',
        ]);
        $usage = AiCreditUsage::create([
            'teacher_id' => $teacher->id,
            'credits_consumed' => 4,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.ai-credits.index'))
            ->assertOk()
            ->assertSeeTextInOrder([
                '196 créditos disponibles',
                '4 de 200 créditos usados',
                '28/02/2026 a las 10:00',
                'Evaluación bajo demanda',
                'Respuestas con puntaje sugerido por IA',
                'Aún no hay sugerencias de IA.',
                'Privacidad',
            ])
            ->assertSeeText('Google Gemini configurado')
            ->assertSeeText('Se descuenta un crédito por sugerencia de IA utilizable.')
            ->assertSeeText('el sistema sustituye los datos identificativos')
            ->assertDontSeeText('Google Gemini activo')
            ->assertDontSeeText('0 de 0')
            ->assertDontSeeText('totalmente anónima')
            ->assertDontSeeText('Promedio de similitud')
            ->assertDontSeeText('Abrir suscripción');

        $usage->update(['credits_consumed' => 150]);
        $this->get(route('teacher.ai-credits.index'))
            ->assertOk()
            ->assertSeeText('50 créditos disponibles')
            ->assertSeeText('Cerca del límite.')
            ->assertDontSeeText('Has alcanzado el límite.');

        $usage->update(['credits_consumed' => 200]);
        $this->get(route('teacher.ai-credits.index'))
            ->assertOk()
            ->assertSeeText('0 créditos disponibles')
            ->assertSeeText('Has alcanzado el límite.')
            ->assertSeeText('28/02/2026 a las 10:00')
            ->assertSeeText('Puedes seguir calificando manualmente.')
            ->assertDontSeeText('Cerca del límite.')
            ->assertDontSeeText('Abrir suscripción');

        $subscription->update(['expires_at' => '2026-02-28 10:00:00', 'auto_renew' => false]);
        $this->actingAs($teacher->fresh())->get(route('teacher.ai-credits.index'))
            ->assertOk()
            ->assertSeeText('Abrir suscripción')
            ->assertSeeText('Sin reposición programada.')
            ->assertDontSeeText('Próxima reposición');
    }

    public function test_credits_monitor_distinguishes_trial_and_suspended_subscription_with_local_fallback(): void
    {
        Config::set('services.gemini.api_key', '');
        $teacher = User::factory()->teacher()->create();
        $usage = AiCreditUsage::create([
            'teacher_id' => $teacher->id,
            'credits_consumed' => 24,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.ai-credits.index'))
            ->assertOk()
            ->assertSeeText('1 crédito disponible')
            ->assertSeeText('24 de 25 créditos usados')
            ->assertSeeText('en la prueba gratuita')
            ->assertSeeText('Respaldo local configurado')
            ->assertDontSeeText('Próxima reposición');

        $usage->update(['credits_consumed' => 25]);
        $this->get(route('teacher.ai-credits.index'))
            ->assertOk()
            ->assertSeeText('0 créditos disponibles')
            ->assertSeeText('Has alcanzado el límite.')
            ->assertSeeText('Abrir suscripción')
            ->assertDontSeeText('Próxima reposición');

        TeacherSubscription::create([
            'teacher_id' => $teacher->id,
            'subscription_package_id' => SubscriptionPackage::where('tier', 'basic')->firstOrFail()->id,
            'payment_status' => 'suspended',
            'starts_at' => now()->subMonth(),
            'suspended_at' => now(),
        ]);

        $this->actingAs($teacher->fresh())->get(route('teacher.ai-credits.index'))
            ->assertOk()
            ->assertSeeTextInOrder(['0 créditos disponibles', 'Suscripción suspendida', 'Abrir suscripción'])
            ->assertSeeText('Abrir revisión')
            ->assertDontSee('<progress', false)
            ->assertDontSeeText('Has alcanzado el límite.')
            ->assertDontSeeText('Próxima reposición');
    }

    public function test_pro_plan_allows_unlimited_ai_evaluations_and_displays_ilimitado_on_credits_and_subscription_pages(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => $this->geminiSuccessResponse(
                94.0,
                true,
                'Excelente dominio del concepto.',
            ),
        ]);

        $teacher = User::factory()->teacher()->create();
        $proPackage = SubscriptionPackage::where('tier', 'pro')->firstOrFail();
        $this->assertNull($proPackage->ai_requests_limit);

        TeacherSubscription::create([
            'teacher_id' => $teacher->id,
            'subscription_package_id' => $proPackage->id,
            'payment_status' => 'paid',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        // Sembrar 60 consumos previos (> 25 Freemium) para certificar evaluación sin tope
        for ($i = 0; $i < 60; $i++) {
            AiCreditUsage::create([
                'teacher_id' => $teacher->id,
                'student_answer_id' => null,
                'credits_consumed' => 1,
                'is_reevaluation' => false,
                'model_used' => 'gemini-2.5-flash',
                'status' => AiCreditUsage::STATUS_CONSUMED,
            ]);
        }

        $student = User::factory()->student()->create();
        $fixture = $this->createSubmittedQualitativeAttempt($teacher, $student);

        $this->actingAs($teacher)
            ->post(route('exams.revision.evaluate-ai', [
                $fixture['exam'],
                $fixture['attempt'],
                $fixture['open_answer'],
            ]))
            ->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseCount('acs_ai_credit_usages', 61);

        // El consumo histórico de Pro se muestra sin denominador ni barra de cuota.
        $creditsView = $this->actingAs($teacher)->get(route('teacher.ai-credits.index'));
        $creditsView->assertOk()
            ->assertSeeText('Ilimitado')
            ->assertSeeText('61 créditos usados')
            ->assertSeeText('Consumo registrado desde')
            ->assertDontSeeText('61 /')
            ->assertDontSeeText('Próxima reposición')
            ->assertDontSee('<progress', false);

        $subscriptionView = $this->actingAs($teacher)->get(route('teacher.subscription.index'));
        $subscriptionView->assertOk()
            ->assertSee('Ilimitado')
            ->assertSee('Ilimitados')
            ->assertSeeText('61 créditos usados')
            ->assertSeeText('Consumo registrado desde')
            ->assertDontSeeText('61 /')
            ->assertDontSee('/ 1 grupos')
            ->assertDontSee('/ 100 evaluaciones')
            ->assertDontSee('/ 1 evaluaciones');
    }

    public function test_reevaluation_with_dismiss_warning_deducts_new_credit_audits_and_updates_user_preference(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiSuccessPayload(70.0, true, 'Primera evaluación IA.'), 200)
                ->push($this->geminiSuccessPayload(88.0, true, 'Segunda evaluación IA tras reevaluación.'), 200),
        ]);

        $teacher = User::factory()->teacher()->create();
        $this->assertFalse((bool) $teacher->skip_ai_reevaluation_warning);

        $student = User::factory()->student()->create();
        $fixture = $this->createSubmittedQualitativeAttempt($teacher, $student);

        // 1. Primera evaluación
        $this->actingAs($teacher)
            ->post(route('exams.revision.evaluate-ai', [
                $fixture['exam'],
                $fixture['attempt'],
                $fixture['open_answer'],
            ]))
            ->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]));

        $this->assertDatabaseCount('acs_ai_credit_usages', 1);
        $this->assertFalse((bool) $teacher->fresh()->skip_ai_reevaluation_warning);

        // 2. Reevaluación con is_reevaluation=1 y dismiss_warning=1 (RF-IAR-012, RF-IAR-014)
        $this->actingAs($teacher)
            ->post(route('exams.revision.evaluate-ai', [
                $fixture['exam'],
                $fixture['attempt'],
                $fixture['open_answer'],
            ]), [
                'is_reevaluation' => '1',
                'dismiss_warning' => '1',
            ])
            ->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseCount('acs_ai_credit_usages', 2);

        $latestUsage = AiCreditUsage::where('teacher_id', $teacher->id)->latest('id')->firstOrFail();
        $this->assertTrue((bool) $latestUsage->is_reevaluation);
        $this->assertSame(1, $latestUsage->credits_consumed);

        $this->assertTrue((bool) $teacher->fresh()->skip_ai_reevaluation_warning);
        $this->assertEquals(88.0, (float) $fixture['open_answer']->fresh()->ai_similarity_score);

        $this->assertDatabaseHas('acs_audit_logs', [
            'user_id' => $teacher->id,
            'action' => 'ai.answer_reevaluated',
            'auditable_type' => StudentAnswer::class,
            'auditable_id' => $fixture['open_answer']->id,
        ]);
    }

    public function test_multi_teacher_idor_blocks_foreign_single_and_batch_ai_evaluations_with_403(): void
    {
        Http::fake();

        $ownerTeacher = User::factory()->teacher()->create();
        $foreignTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $fixture = $this->createSubmittedQualitativeAttempt($ownerTeacher, $student);

        // Evaluación individual ajena -> 403 Forbidden
        $this->actingAs($foreignTeacher)
            ->post(route('exams.revision.evaluate-ai', [
                $fixture['exam'],
                $fixture['attempt'],
                $fixture['open_answer'],
            ]))
            ->assertForbidden();

        // Evaluación en lote ajena -> 403 Forbidden
        $this->actingAs($foreignTeacher)
            ->post(route('exams.revision.evaluate-ai-batch', [
                $fixture['exam'],
                $fixture['attempt'],
            ]))
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertDatabaseCount('acs_ai_credit_usages', 0);
        $this->assertNull($fixture['open_answer']->fresh()->ai_evaluated_at);
    }

    public function test_recoteje_creates_grade_reviewed_audit_on_first_review_and_grade_corrected_on_modification(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $fixture = $this->createSubmittedQualitativeAttempt($teacher, $student);

        // 1. Primer recoteje -> action = teacher.grade_reviewed (RF-EVA-013)
        $this->actingAs($teacher)
            ->post(route('exams.revision.update', [$fixture['exam'], $fixture['attempt']]), [
                'reviews' => [
                    [
                        'answer_id' => $fixture['open_answer']->id,
                        'score' => 40.0,
                        'notes' => 'Explicación clara con ejemplo.',
                    ],
                    [
                        'answer_id' => $fixture['code_answer']->id,
                        'score' => 50.0,
                        'notes' => 'Implementación óptima.',
                    ],
                ],
            ])
            ->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHasNoErrors();

        $firstAudit = AuditLog::where('user_id', $teacher->id)
            ->where('action', 'teacher.grade_reviewed')
            ->where('auditable_type', StudentAnswer::class)
            ->where('auditable_id', $fixture['open_answer']->id)
            ->first();

        $this->assertNotNull($firstAudit);
        $this->assertNull($firstAudit->old_values['teacher_recoteje_score'] ?? null);
        $this->assertEquals(40.0, (float) ($firstAudit->new_values['teacher_recoteje_score'] ?? 0));
        $this->assertSame('Explicación clara con ejemplo.', $firstAudit->new_values['teacher_recoteje_notes'] ?? null);

        $this->assertEquals(9.0, (float) $fixture['attempt']->fresh()->final_score);

        // 2. Corrección posterior sobre el mismo reactivo -> action = teacher.grade_corrected (RF-EVA-013)
        $this->actingAs($teacher)
            ->post(route('exams.revision.update', [$fixture['exam'], $fixture['attempt']]), [
                'reviews' => [
                    [
                        'answer_id' => $fixture['open_answer']->id,
                        'score' => 45.0,
                        'notes' => 'Ajuste tras revisión presencial con alumno.',
                    ],
                ],
            ])
            ->assertRedirect(route('exams.revision.show', [$fixture['exam'], $fixture['attempt']]))
            ->assertSessionHasNoErrors();

        $correctionAudit = AuditLog::where('user_id', $teacher->id)
            ->where('action', 'teacher.grade_corrected')
            ->where('auditable_type', StudentAnswer::class)
            ->where('auditable_id', $fixture['open_answer']->id)
            ->first();

        $this->assertNotNull($correctionAudit);
        $this->assertEquals(40.0, (float) ($correctionAudit->old_values['teacher_recoteje_score'] ?? 0));
        $this->assertEquals(45.0, (float) ($correctionAudit->new_values['teacher_recoteje_score'] ?? 0));
        $this->assertSame(
            'Ajuste tras revisión presencial con alumno.',
            $correctionAudit->new_values['teacher_recoteje_notes'] ?? null,
        );

        $this->assertEquals(9.5, (float) $fixture['attempt']->fresh()->final_score);
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiSuccessPayload(float $score, bool $meetsGoal, string $feedback): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'similarity_score' => $score,
                                    'meets_goal' => $meetsGoal,
                                    'feedback' => $feedback,
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function geminiSuccessResponse(float $score, bool $meetsGoal, string $feedback)
    {
        return Http::response($this->geminiSuccessPayload($score, $meetsGoal, $feedback), 200);
    }

    /**
     * Crea un examen publicado con 1 reactivo abierto (50 pts) y 1 de código (50 pts).
     *
     * @return array{group: Group, exam: Exam, open_eq: ExamQuestion, code_eq: ExamQuestion}
     */
    private function createQualitativeExamFixture(User $teacher, User $student): array
    {
        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo IA Bajo Demanda',
            'access_folio' => strtoupper(substr(bin2hex(random_bytes(4)), 0, 7)),
            'is_closed' => false,
        ]);
        $group->students()->attach($student->id, ['joined_at' => now()]);

        $exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen Cualitativo IA',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 2,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $exam->groups()->attach($group->id);

        $openSection = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Preguntas Abiertas',
            'question_type' => 'open',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);
        $codeSection = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Programación',
            'question_type' => 'code',
            'requested_quantity' => 1,
            'sort_order' => 1,
        ]);

        $openItem = OpenItem::create([
            'reference_answer' => 'La encapsulación oculta los atributos internos y expone métodos controlados.',
            'evaluation_rubric' => 'Debe mencionar ocultamiento de estado y acceso controlado.',
        ]);
        $openQuestion = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'Encapsulación POO',
            'stem' => 'Explica el principio de encapsulación en programación orientada a objetos.',
            'question_type' => 'open',
            'default_points' => 50,
            'default_time_seconds' => 180,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $openItem->id,
        ]);
        $openEq = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $openSection->id,
            'question_id' => $openQuestion->id,
            'time_limit_seconds' => 180,
            'points_weight' => 50.0,
            'sort_order' => 0,
        ]);

        $codeItem = CodeItem::create([
            'programming_language' => 'php',
            'is_find_error' => false,
            'starter_code' => '<?php // suma el arreglo',
            'reference_solution' => '<?php echo array_sum([1, 2, 3]);',
            'instructions' => 'Imprime la suma de los enteros 1, 2 y 3.',
        ]);
        $codeQuestion = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'Suma en PHP',
            'stem' => 'Implementa un script PHP que imprima la suma de [1, 2, 3].',
            'question_type' => 'code',
            'default_points' => 50,
            'default_time_seconds' => 240,
            'questionable_type' => CodeItem::class,
            'questionable_id' => $codeItem->id,
        ]);
        $codeEq = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $codeSection->id,
            'question_id' => $codeQuestion->id,
            'time_limit_seconds' => 240,
            'points_weight' => 50.0,
            'sort_order' => 0,
        ]);

        return [
            'group' => $group,
            'exam' => $exam,
            'open_eq' => $openEq,
            'code_eq' => $codeEq,
        ];
    }

    /**
     * Crea un intento entregado con respuestas cualitativas pendientes de evaluación IA.
     *
     * @return array{
     *     group: Group,
     *     exam: Exam,
     *     attempt: StudentAttempt,
     *     open_answer: StudentAnswer,
     *     code_answer: StudentAnswer,
     *     blank_open_answer: ?StudentAnswer
     * }
     */
    private function createSubmittedQualitativeAttempt(
        User $teacher,
        User $student,
        bool $includeBlankOpen = false,
        bool $includeMultipleChoice = false,
    ): array {
        $fixture = $this->createQualitativeExamFixture($teacher, $student);
        $exam = $fixture['exam'];
        $group = $fixture['group'];
        $sequence = [$fixture['open_eq']->id, $fixture['code_eq']->id];

        $blankEq = null;
        if ($includeBlankOpen) {
            $blankSection = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => 'Abierta Extra',
                'question_type' => 'open',
                'requested_quantity' => 1,
                'sort_order' => 2,
            ]);
            $blankItem = OpenItem::create([
                'reference_answer' => 'Respuesta esperada de ejemplo.',
            ]);
            $blankQuestion = Question::create([
                'teacher_id' => $teacher->id,
                'title' => 'Pregunta Abierta En Blanco',
                'stem' => 'Describe el patrón observador.',
                'question_type' => 'open',
                'default_points' => 10,
                'default_time_seconds' => 120,
                'questionable_type' => OpenItem::class,
                'questionable_id' => $blankItem->id,
            ]);
            $blankEq = ExamQuestion::create([
                'exam_id' => $exam->id,
                'exam_section_id' => $blankSection->id,
                'question_id' => $blankQuestion->id,
                'time_limit_seconds' => 120,
                'points_weight' => 0.0,
                'sort_order' => 0,
            ]);
            $sequence[] = $blankEq->id;
        }

        $mcEq = null;
        if ($includeMultipleChoice) {
            $mcSection = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => 'Opción Múltiple',
                'question_type' => 'multiple_choice',
                'requested_quantity' => 1,
                'sort_order' => 3,
            ]);
            $mcItem = MultipleChoiceItem::create([
                'options_payload' => [['index' => 0, 'text' => 'No'], ['index' => 1, 'text' => 'Sí']],
                'correct_option_index' => 1,
            ]);
            $mcQuestion = Question::create([
                'teacher_id' => $teacher->id,
                'title' => 'Pregunta Objetiva',
                'stem' => '¿PHP es un lenguaje interpretado en servidor?',
                'question_type' => 'multiple_choice',
                'default_points' => 0,
                'default_time_seconds' => 60,
                'questionable_type' => MultipleChoiceItem::class,
                'questionable_id' => $mcItem->id,
            ]);
            $mcEq = ExamQuestion::create([
                'exam_id' => $exam->id,
                'exam_section_id' => $mcSection->id,
                'question_id' => $mcQuestion->id,
                'time_limit_seconds' => 60,
                'points_weight' => 0.0,
                'sort_order' => 0,
            ]);
            $sequence[] = $mcEq->id;
        }

        $attempt = StudentAttempt::create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now()->subMinutes(5),
            'objective_score' => 0.0,
            'ai_estimated_score' => 0.0,
            'final_score' => null,
            'pending_ai_count' => $includeBlankOpen ? 3 : 2,
            'question_sequence_payload' => $sequence,
            'current_question_index' => count($sequence),
        ]);

        $openAnswer = StudentAnswer::create([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $fixture['open_eq']->id,
            'answer_payload' => ['text' => 'La encapsulación protege el estado interno mediante métodos públicos y atributos privados.'],
            'points_awarded' => 0.0,
            'answered_at' => now()->subMinutes(10),
        ]);

        $codeAnswer = StudentAnswer::create([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $fixture['code_eq']->id,
            'answer_payload' => ['code' => '<?php echo array_sum([1, 2, 3]);'],
            'points_awarded' => 0.0,
            'answered_at' => now()->subMinutes(8),
        ]);

        $blankOpenAnswer = null;
        if ($blankEq !== null) {
            $blankOpenAnswer = StudentAnswer::create([
                'student_attempt_id' => $attempt->id,
                'exam_question_id' => $blankEq->id,
                'answer_payload' => ['text' => '   '],
                'points_awarded' => 0.0,
                'answered_at' => now()->subMinutes(7),
            ]);
        }

        if ($mcEq !== null) {
            StudentAnswer::create([
                'student_attempt_id' => $attempt->id,
                'exam_question_id' => $mcEq->id,
                'answer_payload' => ['selected_option_index' => 1],
                'is_correct' => true,
                'points_awarded' => 0.0,
                'answered_at' => now()->subMinutes(6),
            ]);
        }

        return [
            'group' => $group,
            'exam' => $exam,
            'attempt' => $attempt,
            'open_answer' => $openAnswer,
            'code_answer' => $codeAnswer,
            'blank_open_answer' => $blankOpenAnswer,
        ];
    }
}
