<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamQuestionTimingTest.php
 * Ubicación:      tests/Feature/Api/ExamQuestionTimingTest.php
 * Requerimiento:  RF-EXA-032 (Vencimiento del tiempo por pregunta) y
 *                 RF-EXA-042 (Visualización del tiempo restante)
 * Autor:          Calvo Sandoval Miguel Alexander (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Verifica la doble validación de tiempo de MPW-V3 §5.6.1 y
 *                 MPM-V3 §5.4.1: el servidor es la autoridad. El tiempo
 *                 restante de la pregunta activa sale del reloj del
 *                 servidor (no se reinicia al reabrir la app), una respuesta
 *                 dentro del límite más 3 segundos se califica normal y una
 *                 posterior se guarda con cero puntos y se marca como
 *                 extemporánea (late_answer).
 * ============================================================================
 */

namespace Tests\Feature\Api;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MultipleChoiceItem;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExamQuestionTimingTest extends TestCase
{
    use RefreshDatabase;

    private const LIMIT_SECONDS = 60;

    private Exam $exam;

    private string $studentToken;

    /**
     * @var list<Question>
     */
    private array $questions = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Reloj congelado en un segundo entero: las columnas timestamp de
        // PostgreSQL guardan segundos (sin fracciones), así que un instante con
        // microsegundos se redondearía al guardarse y movería el límite medido.
        $this->freezeSecond();

        $teacher = $this->createUser('teacher');
        $student = $this->createUser('student');
        $this->studentToken = $student->createToken('test')->plainTextToken;

        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo cronómetro',
            'access_folio' => 'TIEMPO01',
            'is_closed' => false,
        ]);
        $group->students()->attach($student->id, ['joined_at' => now()]);

        $this->exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen contrarreloj',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 3,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $this->exam->groups()->attach($group->id);

        $section = ExamSection::create([
            'exam_id' => $this->exam->id,
            'title' => 'Única',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 2,
            'sort_order' => 0,
        ]);

        foreach ([0, 1] as $order) {
            $this->questions[] = $this->attachQuestion($teacher, $section, $order);
        }
    }

    /**
     * RF-EXA-026 y RF-EXA-028: el tiempo máximo mostrado al alumno es la suma de
     * los tiempos por pregunta (2 × 60 s = 2 min), no un valor fijo.
     */
    public function test_the_max_duration_is_the_sum_of_the_question_limits(): void
    {
        $this->assertSame(2, $this->exam->maxDurationMinutes());
    }

    /**
     * RF-EXA-031: avanzar sin responder vale cero puntos, aunque la respuesta
     * correcta sea la primera opción (el formulario web envía la selección vacía).
     */
    public function test_advancing_without_answer_scores_zero_even_if_the_first_option_is_correct(): void
    {
        $this->take()->assertOk();
        $this->questions[0]->questionable->update(['correct_option_index' => 0]);

        $attempt = \App\Models\StudentAttempt::query()->where('exam_id', $this->exam->id)->firstOrFail();
        $examQuestionId = (int) $attempt->question_sequence_payload[0];

        app(AttemptService::class)->submitAnswer($attempt, $examQuestionId, '');

        $this->assertDatabaseHas('acs_student_answers', [
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $examQuestionId,
            'points_awarded' => 0,
        ]);
    }

    /**
     * RF-EXA-040 y RF-EXA-048: el borrador de la pregunta activa se guarda en el
     * servidor, se recupera al volver y se descarta al registrar la respuesta;
     * no se acepta para una pregunta que no es la activa.
     */
    public function test_the_draft_of_the_active_question_is_saved_recovered_and_cleared(): void
    {
        $student = User::query()->where('role', 'student')->firstOrFail();
        $this->startFromWeb($student);
        $attempt = \App\Models\StudentAttempt::query()->where('exam_id', $this->exam->id)->firstOrFail();
        $sequence = $attempt->question_sequence_payload;

        $this->actingAs($student)
            ->postJson(route('attempts.draft', $attempt), ['exam_question_id' => $sequence[0], 'selected_option_index' => 1])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $this->assertSame(['selected_option_index' => 1], app(AttemptService::class)->draftFor($attempt, (int) $sequence[0]));
        $this->actingAs($student)->get(route('attempts.show', $attempt))->assertOk()->assertViewHas('draft', ['selected_option_index' => 1]);

        $this->actingAs($student)
            ->postJson(route('attempts.draft', $attempt), ['exam_question_id' => $sequence[1], 'selected_option_index' => 0])
            ->assertStatus(409);

        $this->actingAs($student)
            ->post(route('attempts.answer', $attempt), ['exam_question_id' => $sequence[0], 'selected_option_index' => 1])
            ->assertRedirect();
        $this->assertNull(app(AttemptService::class)->draftFor($attempt, (int) $sequence[0]));
    }

    /**
     * Integra la última respuesta confirmada aunque su pregunta haya vencido al entregar.
     *
     * @return void
     */
    public function test_voluntary_finish_keeps_the_last_confirmed_answer_and_its_time(): void
    {
        $student = User::where('role', 'student')->firstOrFail();
        $this->startFromWeb($student);
        $attempt = StudentAttempt::where('exam_id', $this->exam->id)->sole();
        $questionId = (int) $attempt->question_sequence_payload[0];

        $this->postJson(route('attempts.draft', $attempt), ['exam_question_id' => $questionId, 'selected_option_index' => 0])
            ->assertOk()->assertJsonPath('saved', true);
        $this->travel(10)->seconds();
        $confirmedAt = now()->copy();
        $this->postJson(route('attempts.draft', $attempt), ['exam_question_id' => $questionId, 'selected_option_index' => 1])
            ->assertOk()->assertJsonPath('saved', true);
        $this->travel(60)->seconds();

        $this->post(route('attempts.finish', $attempt))->assertRedirect(route('attempts.result', $attempt));
        $answer = $attempt->answers()->sole();
        $this->assertSame(['selected_option_index' => 1], $answer->answer_payload);
        $this->assertTrue($answer->is_correct);
        $this->assertSame($confirmedAt->getTimestamp(), $answer->answered_at->getTimestamp());
        $this->assertSame('submitted', $attempt->refresh()->status);
        $this->assertDatabaseMissing('acs_attempt_drafts', ['student_attempt_id' => $attempt->id]);
    }

    /**
     * Conserva el borrador confirmado al detener la aplicación en su ruta docente.
     *
     * @return void
     */
    public function test_teacher_stop_preserves_the_confirmed_draft_and_existing_close_state(): void
    {
        $student = User::where('role', 'student')->firstOrFail();
        $this->startFromWeb($student);
        $attempt = StudentAttempt::where('exam_id', $this->exam->id)->sole();
        $questionId = (int) $attempt->question_sequence_payload[0];
        $this->postJson(route('attempts.draft', $attempt), ['exam_question_id' => $questionId, 'selected_option_index' => 1])
            ->assertOk()->assertJsonPath('saved', true);

        $this->actingAs($this->exam->teacher)->post(route('exams.close', $this->exam))->assertRedirect(route('exams.index'));
        $answer = $attempt->answers()->sole();
        $this->assertSame(['selected_option_index' => 1], $answer->answer_payload);
        $this->assertNull($answer->points_awarded);
        $this->assertSame('expired', $attempt->refresh()->status);
        $this->assertNull($attempt->final_score);
        $this->assertDatabaseMissing('acs_attempt_drafts', ['student_attempt_id' => $attempt->id]);
        $this->assertDatabaseHas('acs_audit_logs', [
            'user_id' => $this->exam->teacher_id,
            'auditable_id' => $attempt->id,
            'action' => StudentAttempt::ACTION_STOPPED_BY_TEACHER,
        ]);
    }

    /**
     * Dos solicitudes del reactivo consultado previamente dejan un solo avance.
     *
     * @return void
     */
    public function test_a_retried_answer_does_not_advance_twice_or_change_the_saved_answer(): void
    {
        $this->take()->assertOk();
        $attempt = StudentAttempt::where('exam_id', $this->exam->id)->sole();
        $service = app(AttemptService::class);
        $questionId = (int) $attempt->question_sequence_payload[0];
        $service->submitAnswer($attempt, $questionId, ['selected_option_index' => 1]);

        try {
            $service->submitAnswer($attempt, $questionId, ['selected_option_index' => 1]);
            $this->fail('La respuesta anterior ya había avanzado la secuencia.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('reactivo activo', $e->getMessage());
        }

        $this->assertSame(1, $attempt->refresh()->current_question_index);
        $this->assertSame(['selected_option_index' => 1], $attempt->answers()->sole()->answer_payload);
    }

    /**
     * La sesión que presenta el examen también protege la entrega voluntaria.
     *
     * @return void
     */
    public function test_the_session_presenting_an_attempt_owns_the_voluntary_finish(): void
    {
        $this->take()->assertOk();
        $attempt = StudentAttempt::where('exam_id', $this->exam->id)->sole();
        $this->actingAs(User::findOrFail($attempt->student_id))->post(route('attempts.finish', $attempt))
            ->assertRedirect(route('student.exams.index'))->assertSessionHasErrors('attempt');
        $this->assertSame('in_progress', $attempt->refresh()->status);

        $this->travel(AttemptService::SESSION_IDLE_SECONDS)->seconds();
        $opened = $this->get(route('attempts.show', $attempt))->assertOk();
        $cookie = config('session.cookie');
        $this->withCredentials()->withCookie($cookie, $opened->getCookie($cookie)->getValue());
        $this->post(route('attempts.finish', $attempt))->assertRedirect(route('attempts.result', $attempt));
        $this->assertSame('submitted', $attempt->refresh()->status);
    }

    /**
     * Bloquea edición general y variantes de una aplicación iniciada.
     *
     * @return void
     */
    public function test_an_exam_in_application_and_its_paraphrases_are_protected_from_edits(): void
    {
        $this->take()->assertOk();
        $this->actingAs($this->exam->teacher)->putJson(route('exams.update', $this->exam), [
            'title' => 'Otro título durante la aplicación',
            'grading_scale' => $this->exam->grading_scale,
            'result_visibility_mode' => $this->exam->result_visibility_mode,
            'max_allowed_incidents' => 5,
            'starts_at' => $this->exam->starts_at->toDateTimeString(),
            'ends_at' => now()->addHours(4)->toDateTimeString(),
            'group_ids' => $this->exam->groups()->pluck('acs_groups.id')->all(),
        ])->assertUnprocessable()->assertJsonValidationErrors('exam');
        $this->assertSame('Examen contrarreloj', $this->exam->refresh()->title);
        $this->assertSame(3, $this->exam->max_allowed_incidents);

        $question = $this->questions[0];
        $this->postJson(route('questions.paraphrase.store', $question), ['stem' => 'Variante añadida durante una aplicación'])
            ->assertUnprocessable()->assertJsonValidationErrors('stem');
        $this->assertSame(0, $question->paraphrases()->count());
    }

    /**
     * RF-EXA-049: una sola sesión activa por intento. Mientras una sesión da señal
     * de presencia, otra sesión o dispositivo no puede abrir ni responder el
     * intento; pasado el tiempo sin señal, sí puede retomarlo.
     */
    public function test_only_one_session_can_use_an_attempt_at_a_time(): void
    {
        $this->take()->assertOk();
        $attempt = \App\Models\StudentAttempt::query()->where('exam_id', $this->exam->id)->firstOrFail();
        $service = app(AttemptService::class);

        $this->assertFalse($service->acquireSession($attempt, 'web:otra-sesion'));
        $this->assertFalse($service->sessionAvailable($attempt, 'web:otra-sesion'));

        $this->travel(AttemptService::SESSION_IDLE_SECONDS)->seconds();
        $this->assertTrue($service->acquireSession($attempt, 'web:otra-sesion'));

        $this->assertSame(409, $this->take()->status());
    }

    /**
     * Hallazgo del 22/11/2026: si el servidor de tiempo real (Reverb) no responde,
     * el examen se inicia igual.
     */
    public function test_the_exam_starts_even_if_realtime_broadcasting_fails(): void
    {
        $this->mock(\Illuminate\Contracts\Broadcasting\Factory::class, function ($mock): void {
            $mock->shouldReceive('queue')->andThrow(new \RuntimeException('Reverb no disponible'));
        });

        $this->take()->assertOk()->assertJsonPath('data.remaining_seconds', self::LIMIT_SECONDS);
    }

    public function test_the_tolerance_is_three_seconds_as_the_manuals_define(): void
    {
        $this->assertSame(3, AttemptService::QUESTION_TIME_TOLERANCE_SECONDS);
    }

    public function test_the_first_question_starts_with_its_full_limit(): void
    {
        $this->take()
            ->assertOk()
            ->assertJsonPath('data.remaining_seconds', self::LIMIT_SECONDS);
    }

    public function test_reopening_the_exam_does_not_give_the_full_time_again(): void
    {
        $this->take()->assertJsonPath('data.remaining_seconds', self::LIMIT_SECONDS);

        $this->travel(20)->seconds();

        $this->take()
            ->assertOk()
            ->assertJsonPath('data.remaining_seconds', 40)
            ->assertJsonPath('data.question.id', (string) $this->questions[0]->id);
    }

    public function test_remaining_seconds_never_goes_below_zero(): void
    {
        $this->take();

        $this->travel(500)->seconds();

        $this->take()
            ->assertOk()
            ->assertJsonPath('data.remaining_seconds', 0);
    }

    public function test_the_next_question_timer_starts_when_the_previous_answer_is_received(): void
    {
        $this->take();
        $this->travel(10)->seconds();

        $this->answer($this->questions[0], 1)
            ->assertOk()
            ->assertJsonPath('data.late_answer', false)
            ->assertJsonPath('data.question.id', (string) $this->questions[1]->id)
            ->assertJsonPath('data.remaining_seconds', self::LIMIT_SECONDS);

        $this->travel(15)->seconds();

        $this->take()->assertJsonPath('data.remaining_seconds', 45);
    }

    public function test_an_answer_within_the_three_second_tolerance_is_graded_normally(): void
    {
        $this->take();
        $this->travel(self::LIMIT_SECONDS + AttemptService::QUESTION_TIME_TOLERANCE_SECONDS)->seconds();

        $this->answer($this->questions[0], 1)
            ->assertOk()
            ->assertJsonPath('data.late_answer', false);

        $stored = StudentAnswer::firstOrFail();
        $this->assertTrue((bool) $stored->is_correct);
        $this->assertEquals(10.0, (float) $stored->points_awarded);
    }

    public function test_a_late_answer_is_saved_with_zero_points_and_flagged_and_the_exam_continues(): void
    {
        $this->take();
        $this->travel(self::LIMIT_SECONDS + AttemptService::QUESTION_TIME_TOLERANCE_SECONDS + 1)->seconds();

        $this->answer($this->questions[0], 1)
            ->assertOk()
            ->assertJsonPath('data.late_answer', true)
            ->assertJsonPath('data.question.id', (string) $this->questions[1]->id)
            ->assertJsonPath('data.remaining_seconds', self::LIMIT_SECONDS);

        $stored = StudentAnswer::firstOrFail();
        $this->assertFalse((bool) $stored->is_correct);
        $this->assertEquals(0.0, (float) $stored->points_awarded);
        $this->assertNotNull($stored->answer_payload, 'La respuesta tardía se conserva para la revisión docente.');
    }

    public function test_a_late_answer_does_not_let_the_student_go_back_to_the_previous_question(): void
    {
        $this->take();
        $this->travel(120)->seconds();
        $this->answer($this->questions[0], 1)->assertOk();

        $this->answer($this->questions[0], 1)->assertStatus(409);
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /**
     * RF-EXA-050: la hora límite solo impide nuevos inicios; un intento iniciado
     * antes sigue recibiendo respuestas dentro del tiempo de sus preguntas.
     */
    public function test_an_attempt_started_before_the_close_continues_after_it(): void
    {
        $this->take()->assertOk();
        $this->travel(10)->seconds();
        $this->answer($this->questions[0], 0)->assertOk();

        $this->exam->update(['ends_at' => now()->subMinutes(5)]);
        $this->travel(10)->seconds();

        $this->answer($this->questions[1], 0)->assertOk();
    }

    /**
     * RF-EXA-024: si el docente detiene el examen, los intentos en curso terminan
     * y ya no aceptan respuestas.
     */
    public function test_stopping_the_exam_finalizes_attempts_in_progress(): void
    {
        $this->take()->assertOk();

        app(\App\Services\ExamAssemblyService::class)->closeExamEarly($this->exam);

        $this->assertDatabaseHas('acs_student_attempts', ['exam_id' => $this->exam->id, 'status' => 'expired']);
        $this->assertSame(409, $this->answer($this->questions[0], 0)->status());

        // RF-EXA-024: la causa de finalización queda identificada
        $attempt = \App\Models\StudentAttempt::query()->where('exam_id', $this->exam->id)->firstOrFail();
        $this->assertDatabaseHas('acs_audit_logs', [
            'action' => \App\Models\StudentAttempt::ACTION_STOPPED_BY_TEACHER,
            'auditable_id' => $attempt->id,
        ]);
        $this->assertTrue($attempt->wasStoppedByTeacher());
    }

    private function take(): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->studentToken)
            ->postJson('/api/v1/exams/take', ['exam_id' => $this->exam->id]);
    }

    private function answer(Question $question, int $selectedIndex): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->studentToken)
            ->postJson("/api/v1/exams/{$this->exam->id}/answers", [
                'question_id' => (string) $question->id,
                'selected_option_ids' => [(string) $selectedIndex],
            ]);
    }

    /**
     * Inicia el flujo web y conserva la sesión del navegador simulado.
     *
     * @param User $student Estudiante inscrito en el examen de prueba.
     * @return void
     */
    private function startFromWeb(User $student): void
    {
        $start = $this->actingAs($student)->post(route('attempts.start', $this->exam))->assertRedirect();
        $cookie = config('session.cookie');
        $this->withCredentials()->withCookie($cookie, $start->getCookie($cookie)->getValue());
    }

    private function createUser(string $role): User
    {
        return User::create([
            'role' => $role,
            'first_name' => $role === 'teacher' ? 'Laura' : 'Luis',
            'last_name' => $role === 'teacher' ? 'Martínez' : 'Gómez',
            'email' => uniqid("{$role}-", true).'@example.test',
            'password' => 'Demo1234',
            'is_active' => true,
        ]);
    }

    private function attachQuestion(User $teacher, ExamSection $section, int $sortOrder): Question
    {
        $item = MultipleChoiceItem::create([
            'options_payload' => [['index' => 0, 'text' => 'A'], ['index' => 1, 'text' => 'B']],
            'correct_option_index' => 1,
        ]);

        $question = Question::create([
            'teacher_id' => $teacher->id,
            'title' => "Reactivo {$sortOrder}",
            'stem' => '¿Cuál es correcta?',
            'question_type' => 'multiple_choice',
            'default_points' => 10,
            'default_time_seconds' => self::LIMIT_SECONDS,
            'questionable_type' => MultipleChoiceItem::class,
            'questionable_id' => $item->id,
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => self::LIMIT_SECONDS,
            'points_weight' => 10,
            'sort_order' => $sortOrder,
        ]);

        return $question;
    }
}
