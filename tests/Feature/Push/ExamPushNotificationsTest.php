<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamPushNotificationsTest.php
 * Ubicación:      tests/Feature/Push/ExamPushNotificationsTest.php
 * Requerimiento:  RF-EXA-054 (Notificación de programación o publicación
 *                 de exámenes); los avisos exam.opened, exam.started y
 *                 exam.finished aún no tienen código en DE-01
 *                 (MPM-V4 §6.8, Tabla 14)
 * Autor:          Calvo Sandoval Miguel Alexander (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Verifica quién recibe cada aviso del ciclo de vida de un
 *                 examen: exam.assigned y exam.opened a los alumnos del
 *                 grupo, exam.started y exam.finished al docente; que cada
 *                 aviso se envíe una sola vez y que un fallo de Firebase
 *                 nunca interrumpa el examen.
 * ============================================================================
 */

namespace Tests\Feature\Push;

use App\Contracts\PushNotifierContract;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MultipleChoiceItem;
use App\Models\Question;
use App\Models\User;
use App\Support\PushMessage;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

class ExamPushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private FakePushNotifier $push;

    private User $teacher;

    private User $studentOne;

    private User $studentTwo;

    private User $otherGroupStudent;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Segundo entero: PostgreSQL guarda timestamps sin fracciones.
        $this->freezeSecond();

        $this->push = new FakePushNotifier();
        $this->app->instance(PushNotifierContract::class, $this->push);

        $this->teacher = $this->createUser('teacher');
        $this->studentOne = $this->createUser('student');
        $this->studentTwo = $this->createUser('student');
        $this->otherGroupStudent = $this->createUser('student');

        $this->group = $this->createGroup('PUSH0001', [$this->studentOne, $this->studentTwo]);
        $this->createGroup('PUSH0002', [$this->otherGroupStudent]);
    }

    public function test_publishing_with_a_future_window_notifies_the_group_students_that_it_was_assigned(): void
    {
        $exam = $this->createExam(false, now()->addDay(), now()->addDays(2));

        $this->publish($exam)->assertRedirect(route('exams.index'));

        $this->assertSame(['exam.assigned'], $this->push->types());
        $sent = $this->push->sent[0];
        $this->assertSame([$this->studentOne->id, $this->studentTwo->id], $sent['users']);
        $this->assertSame(
            ['type' => 'exam.assigned', 'exam_id' => (string) $exam->id, 'group_id' => (string) $this->group->id],
            $sent['message']->data(),
        );
    }

    public function test_publishing_with_an_open_window_only_says_the_exam_is_available(): void
    {
        $exam = $this->createExam(false, now()->subHour(), now()->addHours(2));

        $this->publish($exam);

        $this->assertSame(['exam.opened'], $this->push->types());

        $this->artisan('exams:push-due')->assertSuccessful();
        $this->assertSame(['exam.opened'], $this->push->types());
    }

    public function test_publishing_twice_does_not_notify_twice(): void
    {
        $exam = $this->createExam(false, now()->addDay(), now()->addDays(2));

        $this->publish($exam);
        $this->publish($exam);

        $this->assertSame(['exam.assigned'], $this->push->types());
    }

    public function test_the_scheduled_command_announces_the_exam_when_its_window_opens_only_once(): void
    {
        $exam = $this->createExam(false, now()->addMinutes(10), now()->addHours(2));
        $this->publish($exam);

        $this->travel(11)->minutes();
        $this->artisan('exams:push-due')->assertSuccessful();
        $this->artisan('exams:push-due')->assertSuccessful();

        $this->assertSame(['exam.assigned', 'exam.opened'], $this->push->types());
        $this->assertSame([$this->studentOne->id, $this->studentTwo->id], $this->push->sent[1]['users']);
    }

    public function test_the_scheduled_command_tells_the_teacher_when_the_window_closes_only_once(): void
    {
        $exam = $this->createExam(true, now()->subHour(), now()->addHour());

        $this->travel(2)->hours();
        $this->artisan('exams:push-due')->assertSuccessful();
        $this->artisan('exams:push-due')->assertSuccessful();

        $this->assertSame(['exam.finished'], $this->push->types());
        $this->assertSame([$this->teacher->id], $this->push->sent[0]['users']);
        $this->assertSame(
            ['type' => 'exam.finished', 'exam_id' => (string) $exam->id, 'group_id' => (string) $this->group->id],
            $this->push->sent[0]['message']->data(),
        );
    }

    public function test_the_scheduled_command_ignores_windows_older_than_a_day(): void
    {
        $this->createExam(true, now()->subDays(5), now()->subDays(3));

        $this->artisan('exams:push-due')->assertSuccessful();

        $this->assertSame([], $this->push->sent);
    }

    public function test_the_first_student_to_start_notifies_the_teacher_once(): void
    {
        $exam = $this->createExam(true, now()->subHour(), now()->addHours(2));

        $this->take($exam, $this->studentOne);
        $this->take($exam, $this->studentOne); // reanudar no avisa
        $this->take($exam, $this->studentTwo); // el segundo alumno tampoco

        $this->assertSame(['exam.started'], $this->push->types());
        $this->assertSame([$this->teacher->id], $this->push->sent[0]['users']);
        $this->assertSame((string) $this->group->id, $this->push->sent[0]['message']->data()['group_id']);
    }

    public function test_the_teacher_is_told_the_exam_finished_when_the_last_student_submits(): void
    {
        $exam = $this->createExam(true, now()->subHour(), now()->addHours(2));
        $question = Question::query()->firstOrFail();

        foreach ([$this->studentOne, $this->studentTwo] as $student) {
            $this->take($exam, $student);
        }

        $this->answerLast($exam, $this->studentOne, $question);
        $this->assertNotContains('exam.finished', $this->push->types());

        $this->answerLast($exam, $this->studentTwo, $question);
        $this->assertSame(['exam.started', 'exam.finished'], $this->push->types());
        $this->assertSame([$this->teacher->id], $this->push->sent[1]['users']);
    }

    public function test_a_failing_push_service_never_breaks_publishing_or_starting_an_exam(): void
    {
        $this->app->instance(PushNotifierContract::class, new class implements PushNotifierContract {
            public function sendToUsers(array $userIds, PushMessage $message): int
            {
                throw new RuntimeException('Firebase no responde');
            }
        });

        $exam = $this->createExam(false, now()->subHour(), now()->addHours(2));

        $this->publish($exam)->assertRedirect(route('exams.index'));
        $this->assertTrue($exam->fresh()->is_published);

        $this->take($exam, $this->studentOne)->assertOk();
    }

    private function publish(Exam $exam): TestResponse
    {
        return $this->actingAs($this->teacher)->post(route('exams.publish', $exam));
    }

    private function take(Exam $exam, User $student): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($student->createToken('test')->plainTextToken)
            ->postJson('/api/v1/exams/take', ['exam_id' => $exam->id]);
    }

    private function answerLast(Exam $exam, User $student, Question $question): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken($student->createToken('test')->plainTextToken)
            ->postJson("/api/v1/exams/{$exam->id}/answers", [
                'question_id' => (string) $question->id,
                'selected_option_ids' => ['1'],
            ])
            ->assertOk();
    }

    /**
     * @param  list<User>  $students
     */
    private function createGroup(string $folio, array $students): Group
    {
        $group = Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => "Grupo {$folio}",
            'access_folio' => $folio,
            'is_closed' => false,
        ]);

        foreach ($students as $student) {
            $group->students()->attach($student->id, ['joined_at' => now()]);
        }

        return $group;
    }

    private function createExam(bool $published, ?CarbonInterface $startsAt, ?CarbonInterface $endsAt): Exam
    {
        $exam = Exam::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Examen con avisos',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 3,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => $published,
        ]);
        $exam->groups()->attach($this->group->id);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Única',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);

        $item = MultipleChoiceItem::create([
            'options_payload' => [['index' => 0, 'text' => 'A'], ['index' => 1, 'text' => 'B']],
            'correct_option_index' => 1,
        ]);

        $question = Question::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Reactivo único',
            'stem' => '¿Cuál es correcta?',
            'question_type' => 'multiple_choice',
            'default_points' => 10,
            'default_time_seconds' => 60,
            'questionable_type' => MultipleChoiceItem::class,
            'questionable_id' => $item->id,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 60,
            'points_weight' => 10,
            'sort_order' => 0,
        ]);

        return $exam;
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
}
