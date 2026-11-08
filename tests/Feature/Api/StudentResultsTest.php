<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentResultsTest.php
 * Ubicación:   tests/Feature/Api/StudentResultsTest.php
 * Descripción: Consultas del estudiante sobre sus resultados (ADR-037):
 *              «Pendiente de evaluación» sin calificación parcial
 *              (RF-REP-002), puntos por apartado y pregunta (RF-REP-004),
 *              respuestas y soluciones solo con calificación publicada y
 *              acceso cerrado (RF-REP-005) y comentarios del docente sin
 *              justificaciones de IA (RF-REP-006).
 * ============================================================================
 */

namespace Tests\Feature\Api;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MatchingItem;
use App\Models\MultipleChoiceItem;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StudentResultsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Group $group;

    private Exam $exam;

    private ExamQuestion $mcQuestion;

    private ExamQuestion $openQuestion;

    private ExamQuestion $matchingQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();

        $this->teacher = $this->createUser('teacher');
        $this->student = $this->createUser('student');
        $this->group = Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Grupo resultados',
            'access_folio' => 'RESUL001',
            'is_closed' => false,
        ]);
        $this->group->students()->attach($this->student->id, ['joined_at' => now()]);

        $this->exam = Exam::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Examen de resultados',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 3,
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $this->exam->groups()->attach($this->group->id);

        $first = ExamSection::create([
            'exam_id' => $this->exam->id, 'title' => 'Apartado A', 'question_type' => 'multiple_choice',
            'requested_quantity' => 1, 'sort_order' => 0,
        ]);
        $second = ExamSection::create([
            'exam_id' => $this->exam->id, 'title' => 'Apartado B', 'question_type' => 'open',
            'requested_quantity' => 2, 'sort_order' => 1,
        ]);

        $this->mcQuestion = $this->attachMultipleChoice($first, 0);
        $this->openQuestion = $this->attachOpen($second, 1);
        $this->matchingQuestion = $this->attachMatching($second, 2);
    }

    // ------------------------------------------------------------------
    // RF-REP-002 · Pendiente de evaluación
    // ------------------------------------------------------------------

    public function test_a_pending_attempt_is_listed_without_any_grade(): void
    {
        $attempt = $this->createAttempt(['final_score' => null, 'pending_ai_count' => 1, 'objective_score' => 4]);

        $response = $this->fetch('/api/v1/grades')->assertOk();

        $row = collect($response->json('data.data'))->firstWhere('id', (string) $attempt->id);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['score']);
        $this->assertNull($row['is_passing']);
    }

    public function test_the_pending_detail_shows_only_the_pending_message(): void
    {
        $attempt = $this->createAttempt(['final_score' => null, 'pending_ai_count' => 1, 'objective_score' => 4]);
        $this->answer($attempt, $this->mcQuestion, ['selected_option_index' => 1], 5.0);

        $this->fetch("/api/v1/grades/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.message', 'Pendiente de evaluación')
            ->assertJsonMissingPath('data.score')
            ->assertJsonMissingPath('data.sections')
            ->assertJsonMissingPath('data.teacher_comments');
    }

    public function test_pending_attempts_do_not_enter_the_statistics(): void
    {
        $this->createAttempt(['final_score' => null, 'pending_ai_count' => 1]);

        $this->fetch('/api/v1/grades/statistics')
            ->assertOk()
            ->assertJsonCount(0, 'data.grades');
    }

    public function test_a_pending_attempt_cannot_open_the_review(): void
    {
        $attempt = $this->createAttempt(['final_score' => null, 'pending_ai_count' => 1]);

        $this->fetch("/api/v1/grades/{$attempt->id}/review")->assertStatus(403);
    }

    public function test_an_attempt_cancelled_by_incident_is_not_a_result(): void
    {
        $attempt = $this->createAttempt(['status' => 'cancelled_by_incident', 'final_score' => null]);

        $this->fetch("/api/v1/grades/{$attempt->id}")->assertStatus(404);
        $this->assertCount(0, $this->fetch('/api/v1/grades')->json('data.data'));
    }

    // ------------------------------------------------------------------
    // RF-REP-004 · Puntos por pregunta y por apartado
    // ------------------------------------------------------------------

    public function test_the_detail_shows_points_per_question_and_per_section(): void
    {
        $attempt = $this->createAttempt(['final_score' => 7.5]);
        $this->answer($attempt, $this->mcQuestion, ['selected_option_index' => 1], 5.0);
        $this->answer($attempt, $this->openQuestion, 'Respuesta', 2.5);
        $this->answer($attempt, $this->matchingQuestion, [], 0.0);

        $response = $this->fetch("/api/v1/grades/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.score', 7.5)
            ->assertJsonPath('data.is_passing', true)
            ->assertJsonCount(2, 'data.sections')
            ->assertJsonPath('data.sections.0.title', 'Apartado A')
            ->assertJsonPath('data.sections.0.points_earned', 5.0)
            ->assertJsonPath('data.sections.0.points_possible', 5.0)
            ->assertJsonPath('data.sections.1.title', 'Apartado B')
            ->assertJsonPath('data.sections.1.points_earned', 2.5)
            ->assertJsonPath('data.sections.1.points_possible', 10.0)
            ->assertJsonCount(2, 'data.sections.1.questions');

        $this->assertSame(2.5, $response->json('data.sections.1.questions.0.points_earned'));
        $this->assertSame(0.0, $response->json('data.sections.1.questions.1.points_earned'));
    }

    public function test_an_unanswered_question_counts_as_zero_points(): void
    {
        $attempt = $this->createAttempt(['final_score' => 5.0]);
        $this->answer($attempt, $this->mcQuestion, ['selected_option_index' => 1], 5.0);

        $this->fetch("/api/v1/grades/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('data.sections.1.points_earned', 0.0);
    }

    public function test_only_the_owner_can_open_a_result(): void
    {
        $attempt = $this->createAttempt(['final_score' => 7.5]);
        $classmate = $this->createUser('student');
        $this->group->students()->attach($classmate->id, ['joined_at' => now()]);

        $this->actingAsUser($classmate);
        $this->fetch("/api/v1/grades/{$attempt->id}")->assertStatus(404);
        $this->fetch("/api/v1/grades/{$attempt->id}/review")->assertStatus(404);
    }

    public function test_a_teacher_cannot_use_the_student_consultations(): void
    {
        $attempt = $this->createAttempt(['final_score' => 7.5]);

        $this->actingAsUser($this->teacher);
        $this->fetch("/api/v1/grades/{$attempt->id}")->assertStatus(403);
    }

    public function test_results_of_a_group_the_student_left_are_not_available(): void
    {
        $attempt = $this->createAttempt(['final_score' => 7.5]);
        $this->group->students()->detach($this->student->id);

        $this->fetch("/api/v1/grades/{$attempt->id}")->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // RF-REP-006 · Comentarios del docente
    // ------------------------------------------------------------------

    public function test_teacher_comments_are_shown_and_ai_feedback_never(): void
    {
        $attempt = $this->createAttempt(['final_score' => 7.5]);
        $this->answer($attempt, $this->openQuestion, 'Respuesta', 2.5, [
            'ai_feedback' => 'JUSTIFICACION-PROVISIONAL-DE-IA',
            'teacher_recoteje_notes' => 'Buen razonamiento, revisa la ortografía.',
        ]);

        $response = $this->fetch("/api/v1/grades/{$attempt->id}")->assertOk();

        $response->assertJsonPath('data.teacher_comments', ['Buen razonamiento, revisa la ortografía.']);
        $this->assertStringNotContainsString('JUSTIFICACION-PROVISIONAL-DE-IA', $response->getContent());

        $this->fetch("/api/v1/grades/{$attempt->id}/review");
        $this->assertStringNotContainsString(
            'JUSTIFICACION-PROVISIONAL-DE-IA',
            $this->fetch("/api/v1/grades/{$attempt->id}/review")->getContent(),
        );
    }

    public function test_comments_are_optional(): void
    {
        $attempt = $this->createAttempt(['final_score' => 7.5]);
        $this->answer($attempt, $this->openQuestion, 'Respuesta', 2.5);

        $this->fetch("/api/v1/grades/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('data.teacher_comments', []);
    }

    // ------------------------------------------------------------------
    // RF-REP-005 · Respuestas y soluciones
    // ------------------------------------------------------------------

    public function test_the_review_shows_answers_and_reference_solutions_when_published_and_closed(): void
    {
        $attempt = $this->createAttempt(['final_score' => 7.5]);
        $this->answer($attempt, $this->mcQuestion, ['selected_option_index' => 0], 0.0);
        $this->answer($attempt, $this->openQuestion, 'Mi respuesta abierta', 2.5);
        $this->answer($attempt, $this->matchingQuestion, [['left_id' => 'L1', 'right_id' => 'R2']], 0.0);

        $this->fetch("/api/v1/grades/{$attempt->id}")->assertJsonPath('data.review_available', true);

        $questions = $this->fetch("/api/v1/grades/{$attempt->id}/review")
            ->assertOk()
            ->assertJsonCount(3, 'data.questions')
            ->json('data.questions');

        $this->assertSame('Opción A', $questions[0]['student_answer']);
        $this->assertSame('Opción B', $questions[0]['reference_solution']);
        $this->assertSame('Mi respuesta abierta', $questions[1]['student_answer']);
        $this->assertSame('Respuesta de referencia', $questions[1]['reference_solution']);
        $this->assertSame('Uno → Dos', $questions[2]['student_answer']);
        $this->assertSame("Uno → Uno\nDos → Dos", $questions[2]['reference_solution']);
        $this->assertSame(5.0, $questions[0]['points_possible']);
    }

    public function test_an_unanswered_question_has_no_student_answer_in_the_review(): void
    {
        $attempt = $this->createAttempt(['final_score' => 0.0]);

        $questions = $this->fetch("/api/v1/grades/{$attempt->id}/review")->assertOk()->json('data.questions');

        $this->assertNull($questions[0]['student_answer']);
        $this->assertNotNull($questions[0]['reference_solution']);
    }

    public function test_the_review_is_locked_while_the_access_window_is_open(): void
    {
        $this->exam->update(['ends_at' => now()->addHour()]);
        $attempt = $this->createAttempt(['final_score' => 7.5]);

        $this->fetch("/api/v1/grades/{$attempt->id}")->assertJsonPath('data.review_available', false);
        $this->fetch("/api/v1/grades/{$attempt->id}/review")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Podrás consultar tus respuestas y las soluciones cuando tu calificación esté publicada y haya terminado el plazo de acceso al examen.');
    }

    public function test_the_review_stays_locked_during_the_network_tolerance_after_the_close(): void
    {
        $this->exam->update(['ends_at' => now()->subSeconds(30)]);
        $attempt = $this->createAttempt(['final_score' => 7.5]);

        $this->fetch("/api/v1/grades/{$attempt->id}/review")->assertStatus(403);

        $this->travel(31)->seconds();
        $this->fetch("/api/v1/grades/{$attempt->id}/review")->assertOk();
    }

    public function test_the_review_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken('')->getJson('/api/v1/grades/1/review')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    private function fetch(string $uri): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->tokenFor($this->currentUser ?? $this->student))->getJson($uri);
    }

    private ?User $currentUser = null;

    private function actingAsUser(User $user): void
    {
        $this->currentUser = $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createAttempt(array $overrides = []): StudentAttempt
    {
        return StudentAttempt::create(array_merge([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'group_id' => $this->group->id,
            'question_sequence_payload' => [
                $this->mcQuestion->id,
                $this->openQuestion->id,
                $this->matchingQuestion->id,
            ],
            'current_question_index' => 3,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subMinutes(90),
            'status' => 'submitted',
            'objective_score' => 0,
            'ai_estimated_score' => 0,
            'pending_ai_count' => 0,
            'incidents_count' => 0,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function answer(StudentAttempt $attempt, ExamQuestion $examQuestion, mixed $payload, float $points, array $extra = []): void
    {
        StudentAnswer::create(array_merge([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $examQuestion->id,
            'answer_payload' => $payload,
            'points_awarded' => $points,
            'is_correct' => $points > 0,
            'answered_at' => now()->subHours(2),
        ], $extra));
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

    private function attachMultipleChoice(ExamSection $section, int $order): ExamQuestion
    {
        $item = MultipleChoiceItem::create([
            'options_payload' => [['index' => 0, 'text' => 'Opción A'], ['index' => 1, 'text' => 'Opción B']],
            'correct_option_index' => 1,
        ]);

        return $this->attach($section, $order, 'multiple_choice', $item, 'Reactivo opción múltiple');
    }

    private function attachOpen(ExamSection $section, int $order): ExamQuestion
    {
        $item = OpenItem::create(['reference_answer' => 'Respuesta de referencia', 'evaluation_rubric' => null]);

        return $this->attach($section, $order, 'open', $item, 'Reactivo abierto');
    }

    private function attachMatching(ExamSection $section, int $order): ExamQuestion
    {
        $item = MatchingItem::create([
            'left_column_payload' => [['id' => 'L1', 'text' => 'Uno'], ['id' => 'L2', 'text' => 'Dos']],
            'right_column_payload' => [['id' => 'R1', 'text' => 'Uno'], ['id' => 'R2', 'text' => 'Dos']],
            'pairs_payload' => [['left_id' => 'L1', 'right_id' => 'R1'], ['left_id' => 'L2', 'right_id' => 'R2']],
        ]);

        return $this->attach($section, $order, 'matching', $item, 'Reactivo de relación');
    }

    private function attach(ExamSection $section, int $order, string $type, object $item, string $title): ExamQuestion
    {
        $question = Question::create([
            'teacher_id' => $this->teacher->id,
            'title' => $title,
            'stem' => "Enunciado de {$title}",
            'question_type' => $type,
            'default_points' => 5,
            'default_time_seconds' => 60,
            'questionable_type' => $item::class,
            'questionable_id' => $item->getKey(),
        ]);

        return ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 60,
            'points_weight' => 5,
            'sort_order' => $order,
        ]);
    }
}
