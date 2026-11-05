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
 * Descripción:    Verifica la doble validación de tiempo de MPW-V2 §5.6.1 y
 *                 MPM-V4 §5.4.1: el servidor es la autoridad. El tiempo
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
