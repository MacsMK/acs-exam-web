<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Contracts\AiEvaluatorContract;
use App\Models\AiCreditUsage;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Database\Seeders\SubscriptionPackageSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TeacherReviewUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_prioritizes_pending_attempts_and_counts_the_entire_filtered_result(): void
    {
        $teacher = User::factory()->teacher()->create();
        $group = $this->group($teacher, 'Principal', 'REVUX001');
        $otherGroup = $this->group($teacher, 'Otro grupo', 'REVUX002');
        $exam = $this->exam($teacher, 'Examen con revisión', 'REVUXEX1');
        $exam->forceFill(['created_at' => now()->subDay()])->save();
        $exam->groups()->attach([$group->id, $otherGroup->id]);
        $emptyExam = $this->exam($teacher, 'Examen nuevo sin intentos', 'REVUXEX2');
        $question = $this->openQuestion($teacher, $exam);

        $pending = $this->attempt($exam, $group, 'Coincidencia Pendiente', [
            'pending_ai_count' => 1,
            'submitted_at' => now()->subHour(),
        ]);
        $this->answer($pending, $question, ['answer_payload' => ['text' => 'Requiere revisión.']]);
        for ($index = 1; $index <= 14; $index++) {
            $this->attempt($exam, $group, 'Coincidencia '.$index, ['final_score' => 7]);
        }

        $blank = $this->attempt($exam, $group, 'Coincidencia Blanco', ['final_score' => 0]);
        $this->answer($blank, $question, [
            'answer_payload' => ['text' => "  \n  "],
            'ai_model_used' => 'none',
            'ai_similarity_score' => 0,
            'ai_evaluated_at' => now(),
        ]);

        $this->attempt($exam, $otherGroup, 'Coincidencia Otro Grupo', ['pending_ai_count' => 1]);
        $this->attempt($exam, $group, 'Fuera del filtro', ['pending_ai_count' => 1]);

        $this->actingAs($teacher)->get(route('teacher.reviews.index'))
            ->assertOk()
            ->assertViewHas('exams', static fn ($exams): bool => $exams->getCollection()->modelKeys() === [$exam->id, $emptyExam->id])
            ->assertViewHas('stats.pending_recoteje_exams', 1);

        $centerFilters = ['search' => 'revisión', 'status' => 'pending_review'];
        $centerContext = ['center_search' => 'revisión', 'center_status' => 'pending_review'];
        $center = $this->get(route('teacher.reviews.index', $centerFilters), [
            'X-Catalog-Fragment' => 'teacher-review-exam-results',
        ])->assertOk()
            ->assertSee('data-search-status="1 examen encontrado"', false)
            ->assertDontSee('id="search"', false)
            ->assertDontSeeText($emptyExam->title);
        $this->assertLinked($center, route('exams.revision.index', [$exam, ...$centerContext]));
        $this->assertLinked($this->get(route('exams.revision.index', [$exam, ...$centerContext]))->assertOk(), route('teacher.reviews.index', $centerFilters));

        $this->get(route('teacher.reviews.index', ['search' => 'Sin coincidencia']), [
            'X-Catalog-Fragment' => 'teacher-review-exam-results',
        ])->assertOk()
            ->assertSeeText('Sin exámenes para esos filtros')
            ->assertSeeText('Limpiar filtros')
            ->assertDontSeeText('Crear examen');

        $listContext = ['group_id' => $group->id, 'search' => 'Coincidencia'];
        $this->get(route('exams.revision.index', [$exam, ...$listContext]))
            ->assertOk()
            ->assertViewHas('attempts', static fn ($attempts): bool => $attempts->first()->is($pending) && $attempts->count() === 15)
            ->assertViewHas('reviewStats.registered', 16)
            ->assertViewHas('reviewStats.pending', 1)
            ->assertViewHas('reviewStats.final', 15)
            ->assertSeeText($blank->student->full_name)
            ->assertDontSeeText('Coincidencia Otro Grupo');

        $secondPage = $this->get(route('exams.revision.index', [$exam, ...$listContext, 'page' => 2]))
            ->assertOk()
            ->assertViewHas('attempts', static fn ($attempts): bool => $attempts->count() === 1)
            ->assertViewHas('reviewStats.registered', 16)
            ->assertViewHas('reviewStats.pending', 1)
            ->assertViewHas('reviewStats.final', 15);
        $secondPageAttempt = $secondPage->viewData('attempts')->first();
        $this->assertLinked($secondPage, route('exams.revision.show', [$exam, $secondPageAttempt, ...$listContext, 'page' => 2]));

        $this->get(route('exams.revision.index', [$exam, ...$listContext, 'status' => 'pending_recoteje']))
            ->assertOk()
            ->assertViewHas('attempts', static fn ($attempts): bool => $attempts->getCollection()->modelKeys() === [$pending->id])
            ->assertViewHas('reviewStats.registered', 1)
            ->assertViewHas('reviewStats.pending', 1)
            ->assertViewHas('reviewStats.final', 0);

        $this->get(route('exams.revision.index', [$exam, ...$listContext, 'status' => 'pending_recoteje']), [
            'X-Catalog-Fragment' => 'exam-review-attempt-results',
        ])->assertOk()
            ->assertSee('data-search-status="1 intento encontrado"', false)
            ->assertSee('aria-label="Intentos del examen con desplazamiento horizontal"', false)
            ->assertDontSeeText($blank->student->full_name);

        $this->get(route('exams.revision.show', [$exam, $blank, ...$listContext]))
            ->assertOk()
            ->assertSeeText('Respuesta en blanco (0 pts)')
            ->assertDontSeeText('Similitud')
            ->assertDontSee('reviews[0][score]', false);
    }

    public function test_review_keeps_the_list_context_and_uses_ai_points_independently_of_teacher_scores(): void
    {
        $this->seed(SubscriptionPackageSeeder::class);
        $this->app->instance(AiEvaluatorContract::class, new class implements AiEvaluatorContract
        {
            public function evaluate(string $studentAnswer, string $referenceAnswer, array $context = []): array
            {
                return [
                    'similarity_score' => 75.0,
                    'meets_goal' => true,
                    'feedback' => 'Sugerencia de prueba.',
                    'is_fallback' => false,
                    'model' => 'gemini-2.5-flash',
                ];
            }
        });

        $teacher = User::factory()->teacher()->create();
        $group = $this->group($teacher, 'Grupo de revisión', 'REVUX003');
        $exam = $this->exam($teacher, 'Examen con estimación', 'REVUXEX3');
        $exam->groups()->attach($group->id);
        $question = $this->openQuestion($teacher, $exam);
        $attempt = $this->attempt($exam, $group, 'Persona Revisada', [
            'pending_ai_count' => 1,
            'ai_estimated_score' => 5.0,
        ]);
        $answer = $this->answer($attempt, $question, [
            'answer_payload' => ['text' => 'La respuesta que se calificará.'],
            'points_awarded' => 5.0,
            'ai_model_used' => 'gemini-2.5-flash',
            'ai_similarity_score' => 50.0,
            'ai_evaluated_at' => now(),
        ]);

        $context = ['group_id' => $group->id, 'search' => 'Persona', 'status' => 'pending_recoteje', 'page' => 2];
        $listUrl = route('exams.revision.index', [$exam, ...$context]);
        $detailUrl = route('exams.revision.show', [$exam, $attempt, ...$context]);
        $this->assertLinked($this->actingAs($teacher)->get($detailUrl)->assertOk(), $listUrl);

        $saved = $this->post(route('exams.revision.update', [$exam, $attempt, ...$context]), [
            'reviews' => [['answer_id' => $answer->id, 'score' => '9,50', 'notes' => 'Nota guardada.']],
        ])->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSameRoute($detailUrl, $saved->headers->get('Location'));
        $this->assertSame(9.5, (float) $attempt->fresh()->final_score);

        $edited = [
            'review_draft' => [
                $answer->id => ['score' => '6,25', 'notes' => 'Nota todavía en edición.'],
            ],
        ];
        $reevaluateUrl = route('exams.revision.evaluate-ai', [$exam, $attempt, $answer, ...$context]);
        $reevaluated = $this->post($reevaluateUrl, ['is_reevaluation' => true, ...$edited])
            ->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHasInput('review_draft.'.$answer->id.'.notes', 'Nota todavía en edición.');
        $this->assertSameRoute($detailUrl, $reevaluated->headers->get('Location'));

        $detail = $this->followWithSession($reevaluated)
            ->assertOk()
            ->assertSee('Nota todavía en edición.');
        $this->assertLinked($detail, $listUrl);

        $answer->refresh();
        $attempt->refresh();
        $this->assertSame(7.5, (float) $attempt->ai_estimated_score);
        $this->assertSame(9.5, (float) $attempt->final_score);
        $this->assertSame(9.5, (float) $answer->points_awarded);
        $this->assertSame('Nota guardada.', $answer->teacher_recoteje_notes);

        $evaluated = $this->post(route('exams.revision.evaluate-ai-batch', [$exam, $attempt, ...$context]), $edited)
            ->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHasInput('review_draft.'.$answer->id.'.notes', 'Nota todavía en edición.');
        $this->assertSameRoute($detailUrl, $evaluated->headers->get('Location'));
    }

    public function test_review_shows_available_ai_credits_and_explains_the_manual_path_at_the_limit(): void
    {
        $teacher = User::factory()->teacher()->create();
        $group = $this->group($teacher, 'Grupo de revisión', 'REVUX006');
        $exam = $this->exam($teacher, 'Examen con créditos', 'REVUXEX6');
        $exam->groups()->attach($group->id);
        $question = $this->openQuestion($teacher, $exam);
        $attempt = $this->attempt($exam, $group, 'Persona Revisada', ['pending_ai_count' => 1]);
        $this->answer($attempt, $question, ['answer_payload' => ['text' => 'Respuesta por revisar.']]);
        $limit = SubscriptionLimitService::FREEMIUM_MAX_AI_EVALUATIONS;
        $detailUrl = route('exams.revision.show', [$exam, $attempt]);

        AiCreditUsage::create([
            'teacher_id' => $teacher->id,
            'credits_consumed' => $limit - 1,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $this->actingAs($teacher)->get($detailUrl)
            ->assertOk()
            ->assertSeeText('1 crédito disponible')
            ->assertSeeText('Cerca del límite.')
            ->assertDontSeeText("1 / {$limit}");

        AiCreditUsage::create([
            'teacher_id' => $teacher->id,
            'credits_consumed' => 1,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $this->get($detailUrl)->assertOk()
            ->assertSeeText('0 créditos disponibles')
            ->assertSeeText('Has alcanzado el límite. Puedes calificar manualmente.')
            ->assertSeeText('Guardar revisión')
            ->assertDontSeeText('Próxima reposición');

        $this->seed(SubscriptionPackageSeeder::class);
        $basic = SubscriptionPackage::where('tier', 'basic')->firstOrFail();
        $activatedAt = now()->subDay()->setTime(9, 15);
        $nextResetAt = $activatedAt->copy()->addMonthNoOverflow();
        TeacherSubscription::create([
            'teacher_id' => $teacher->id,
            'subscription_package_id' => $basic->id,
            'payment_status' => 'paid',
            'starts_at' => $activatedAt,
            'expires_at' => $activatedAt->copy()->addYear(),
        ]);
        AiCreditUsage::create([
            'teacher_id' => $teacher->id,
            'credits_consumed' => $basic->ai_requests_limit - $limit,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $this->actingAs($teacher->fresh())->get($detailUrl)
            ->assertOk()
            ->assertSeeText('0 créditos disponibles')
            ->assertSeeText('Próxima reposición si tu plan sigue activo: '.$nextResetAt->format('d/m/Y').' a las 09:15.');
    }

    public function test_invalid_teacher_score_is_associated_with_its_labeled_field(): void
    {
        $teacher = User::factory()->teacher()->create();
        $group = $this->group($teacher, 'Grupo de validación', 'REVUX004');
        $exam = $this->exam($teacher, 'Examen de validación', 'REVUXEX4');
        $exam->groups()->attach($group->id);
        $question = $this->openQuestion($teacher, $exam);
        $attempt = $this->attempt($exam, $group, 'Persona Revisada', ['pending_ai_count' => 1]);
        $answer = $this->answer($attempt, $question, ['answer_payload' => ['text' => 'Respuesta por revisar.']]);
        $detailUrl = route('exams.revision.show', [$exam, $attempt]);

        $invalid = $this->actingAs($teacher)->from($detailUrl)->post($detailUrl, [
            'reviews' => [['answer_id' => $answer->id, 'score' => '10,01']],
        ])->assertRedirect($detailUrl)->assertSessionHasErrors('reviews.0.score');
        $response = $this->followWithSession($invalid)->assertOk();

        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $field = $xpath->query('//input[@name="reviews[0][score]"]')->item(0);
        $this->assertNotNull($field);
        $this->assertSame('true', $field->getAttribute('aria-invalid'));
        $fieldId = $field->getAttribute('id');
        $this->assertNotSame('', $fieldId);
        $this->assertSame(1, $xpath->query('//label[@for="'.$fieldId.'"]')->length);

        $descriptionIds = array_filter(explode(' ', $field->getAttribute('aria-describedby')));
        $description = '';
        foreach ($descriptionIds as $id) {
            $description .= $document->getElementById($id)?->textContent ?? '';
        }

        $this->assertStringContainsString('entre 0 y 10', $description);
        $this->assertNull($answer->fresh()->teacher_recoteje_score);
    }

    public function test_replaced_attempts_stay_in_history_without_entering_the_review_queue(): void
    {
        $teacher = User::factory()->teacher()->create();
        $group = $this->group($teacher, 'Grupo de reaplicaciones', 'REVUX005');
        $exam = $this->exam($teacher, 'Examen con reaplicación', 'REVUXEX5');
        $exam->groups()->attach($group->id);
        $replaced = $this->attempt($exam, $group, 'Persona Revisada', [
            'pending_ai_count' => 1,
            'final_score' => 2,
            'submitted_at' => now()->subHour(),
        ]);
        $current = $replaced->replicate()->forceFill([
            'attempt_number' => 2,
            'pending_ai_count' => 0,
            'final_score' => 7,
            'submitted_at' => now(),
        ]);
        $current->save();

        $this->actingAs($teacher)->get(route('teacher.reviews.index'))
            ->assertOk()
            ->assertViewHas('stats.pending_recoteje_exams', 0);
        $this->get(route('teacher.reviews.index', ['status' => 'pending_review']))
            ->assertOk()
            ->assertViewHas('exams', static fn ($exams): bool => $exams->isEmpty());

        $history = $this->get(route('exams.revision.index', $exam))
            ->assertOk()
            ->assertViewHas('attempts', static fn ($attempts): bool => $attempts->getCollection()->modelKeys() === [$current->id, $replaced->id])
            ->assertViewHas('reviewStats.registered', 2)
            ->assertViewHas('reviewStats.pending', 0)
            ->assertViewHas('reviewStats.final', 1)
            ->assertSeeText('Intento 1 (reemplazado)');
        $this->assertSame(0, (int) $history->viewData('attempts')->last()->review_pending);

        $this->get(route('exams.revision.index', [$exam, 'status' => 'pending_recoteje']))
            ->assertOk()
            ->assertViewHas('attempts', static fn ($attempts): bool => $attempts->isEmpty());
        $this->get(route('exams.revision.index', [$exam, 'status' => 'graded']))
            ->assertOk()
            ->assertViewHas('attempts', static fn ($attempts): bool => $attempts->getCollection()->modelKeys() === [$current->id]);
    }

    private function group(User $teacher, string $name, string $folio): Group
    {
        return Group::create([
            'teacher_id' => $teacher->id,
            'name' => $name,
            'subject' => 'Programación',
            'access_folio' => $folio,
        ]);
    }

    private function exam(User $teacher, string $title, string $token): Exam
    {
        return Exam::create([
            'teacher_id' => $teacher->id,
            'title' => $title,
            'share_token' => $token,
            'grading_scale' => '0-10',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->addHour(),
            'is_published' => true,
        ]);
    }

    private function openQuestion(User $teacher, Exam $exam): ExamQuestion
    {
        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Sección abierta',
            'question_type' => 'open',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);
        $item = OpenItem::create([
            'reference_answer' => 'Respuesta de referencia.',
            'evaluation_rubric' => 'Describe el concepto.',
            'allow_file_upload' => false,
        ]);
        $question = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'Pregunta abierta',
            'stem' => 'Explica el concepto.',
            'question_type' => 'open',
            'default_points' => 10,
            'default_time_seconds' => 60,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $item->id,
        ]);

        return ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'sort_order' => 0,
            'points_weight' => 10,
            'time_limit_seconds' => 60,
        ]);
    }

    private function attempt(Exam $exam, Group $group, string $name, array $attributes): StudentAttempt
    {
        $student = User::factory()->student()->create(['first_name' => $name, 'last_name' => 'Prueba']);
        $group->students()->attach($student->id, ['joined_at' => now()]);

        return StudentAttempt::create(array_replace([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'pending_ai_count' => 0,
            'started_at' => now()->subHours(2),
            'submitted_at' => now(),
            'question_sequence_payload' => [],
        ], $attributes));
    }

    private function answer(StudentAttempt $attempt, ExamQuestion $question, array $attributes): StudentAnswer
    {
        return StudentAnswer::create(array_replace([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $question->id,
            'points_awarded' => 0,
            'answered_at' => now(),
        ], $attributes));
    }

    private function assertLinked(TestResponse $response, string $target): void
    {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $linkedRoutes = [];

        foreach ($xpath->query('//a[@href]') as $link) {
            $linkedRoutes[] = $this->routeSignature($link->getAttribute('href'));
        }

        $this->assertContains($this->routeSignature($target), $linkedRoutes, 'No se encontró un enlace a '.$target);
    }

    private function assertSameRoute(string $target, string $actual): void
    {
        $this->assertSame($this->routeSignature($target), $this->routeSignature($actual));
    }

    private function followWithSession(TestResponse $redirect): TestResponse
    {
        $cookieName = config('session.cookie');
        $sessionCookie = $redirect->getCookie($cookieName);
        $this->assertNotNull($sessionCookie);

        return $this->withCookie($cookieName, $sessionCookie->getValue())->get($redirect->headers->get('Location'));
    }

    private function routeSignature(string $url): array
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        ksort($query);

        return [parse_url($url, PHP_URL_PATH), $query];
    }
}
