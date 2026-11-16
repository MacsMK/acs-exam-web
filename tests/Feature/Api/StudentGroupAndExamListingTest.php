<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentGroupAndExamListingTest.php
 * Ubicación:   tests/Feature/Api/StudentGroupAndExamListingTest.php
 * Descripción: Datos de las tarjetas del alumno en la API móvil (ADR-056):
 *              materia, docente y fecha de ingreso en api.v1.groups.index y
 *              api.v1.groups.join; docente, grupo con materia, número de
 *              preguntas e intento del alumno en api.v1.exams.index. Incluye
 *              el vencimiento de un intento en curso, que los grupos
 *              cerrados no muestren sus exámenes (RF-GRU-019) y que el
 *              docente conserve su respuesta anterior.
 * ============================================================================
 */

namespace Tests\Feature\Api;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentGroupAndExamListingTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-16 10:00:00');

        $this->teacher = $this->createUser('teacher', 'Laura', 'Martínez');
        $this->student = $this->createUser('student', 'Luis', 'Gómez');

        $this->group = $this->createGroup('DATOS001', 'Programación Móvil');
        $this->group->students()->attach($this->student->id, ['joined_at' => now()->subDays(3)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_student_group_list_includes_subject_teacher_and_join_date(): void
    {
        $response = $this->as($this->student)->getJson('/api/v1/groups');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', (string) $this->group->id);
        $response->assertJsonPath('data.0.subject', 'Programación Móvil');
        $response->assertJsonPath('data.0.teacher_name', 'Laura Martínez');
        $response->assertJsonPath('data.0.students_count', 1);
        $this->assertTrue(Carbon::parse($response->json('data.0.joined_at'))->equalTo(now()->subDays(3)));
    }

    public function test_a_group_without_subject_returns_null_subject(): void
    {
        $this->group->update(['subject' => null]);

        $response = $this->as($this->student)->getJson('/api/v1/groups');

        $response->assertOk();
        $response->assertJsonPath('data.0.subject', null);
    }

    public function test_joining_a_group_returns_the_same_fields_with_the_new_join_date(): void
    {
        $newGroup = $this->createGroup('DATOS002', 'Bases de Datos');

        $response = $this->as($this->student)->postJson('/api/v1/groups/join', ['folio' => 'datos002']);

        $response->assertOk();
        $response->assertJsonPath('data.id', (string) $newGroup->id);
        $response->assertJsonPath('data.subject', 'Bases de Datos');
        $response->assertJsonPath('data.teacher_name', 'Laura Martínez');
        $response->assertJsonPath('data.students_count', 1);
        $this->assertTrue(Carbon::parse($response->json('data.joined_at'))->equalTo(now()));
    }

    public function test_the_teacher_group_list_has_no_join_date(): void
    {
        $response = $this->as($this->teacher)->getJson('/api/v1/groups');

        $response->assertOk();
        $response->assertJsonPath('data.0.subject', 'Programación Móvil');
        $response->assertJsonPath('data.0.teacher_name', 'Laura Martínez');
        $response->assertJsonPath('data.0.joined_at', null);
    }

    public function test_an_exam_not_started_shows_teacher_group_question_count_and_one_available_attempt(): void
    {
        $exam = $this->createExam($this->group, questions: 2);

        $response = $this->as($this->student)->getJson('/api/v1/exams');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', (string) $exam->id);
        $response->assertJsonPath('data.0.title', $exam->title);
        $response->assertJsonPath('data.0.teacher_name', 'Laura Martínez');
        $response->assertJsonPath('data.0.group.id', (string) $this->group->id);
        $response->assertJsonPath('data.0.group.name', 'Grupo DATOS001');
        $response->assertJsonPath('data.0.group.subject', 'Programación Móvil');
        $response->assertJsonPath('data.0.questions_count', 2);
        $response->assertJsonPath('data.0.attempts_allowed', 1);
        $response->assertJsonPath('data.0.attempts_used', 0);
        $response->assertJsonPath('data.0.attempt_status', null);
    }

    public function test_a_submitted_attempt_counts_as_used(): void
    {
        $exam = $this->createExam($this->group);
        $this->createAttempt($exam, 'submitted');

        $response = $this->as($this->student)->getJson('/api/v1/exams');

        $response->assertJsonPath('data.0.attempts_used', 1);
        $response->assertJsonPath('data.0.attempt_status', 'submitted');
    }

    public function test_an_attempt_cancelled_by_incidents_reports_its_reason(): void
    {
        $exam = $this->createExam($this->group);
        $this->createAttempt($exam, 'cancelled_by_incident');

        $response = $this->as($this->student)->getJson('/api/v1/exams');

        $response->assertJsonPath('data.0.attempts_used', 1);
        $response->assertJsonPath('data.0.attempt_status', 'cancelled_by_incident');
    }

    public function test_an_attempt_in_progress_within_the_exam_window_stays_in_progress(): void
    {
        $exam = $this->createExam($this->group);
        $this->createAttempt($exam, 'in_progress');

        $response = $this->as($this->student)->getJson('/api/v1/exams');

        $response->assertJsonPath('data.0.attempt_status', 'in_progress');
    }

    public function test_an_attempt_in_progress_after_the_exam_closed_is_reported_as_expired(): void
    {
        $exam = $this->createExam($this->group);
        $attempt = $this->createAttempt($exam, 'in_progress');

        Carbon::setTestNow(now()->addHours(3));

        $response = $this->as($this->student)->getJson('/api/v1/exams');

        $response->assertJsonPath('data.0.attempt_status', 'expired');
        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    public function test_the_exam_shows_the_student_group_when_assigned_to_several_groups(): void
    {
        $otherGroup = $this->createGroup('DATOS003', 'Redes');
        $exam = $this->createExam($otherGroup);
        $exam->groups()->attach($this->group->id);

        $response = $this->as($this->student)->getJson('/api/v1/exams');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.group.id', (string) $this->group->id);
        $response->assertJsonPath('data.0.group.subject', 'Programación Móvil');
    }

    public function test_an_exam_only_assigned_to_a_closed_group_is_not_listed(): void
    {
        $closedGroup = $this->createGroup('DATOS004', 'Cerrada');
        $closedGroup->students()->attach($this->student->id, ['joined_at' => now()]);
        $this->createExam($closedGroup);
        $closedGroup->update(['is_closed' => true, 'closed_at' => now()]);

        $openExam = $this->createExam($this->group);

        $response = $this->as($this->student)->getJson('/api/v1/exams');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', (string) $openExam->id);
    }

    public function test_another_student_attempt_does_not_affect_the_listing(): void
    {
        $exam = $this->createExam($this->group);
        $classmate = $this->createUser('student', 'Ana', 'Ruiz');
        $this->group->students()->attach($classmate->id, ['joined_at' => now()]);
        $this->createAttempt($exam, 'submitted', $classmate);

        $response = $this->as($this->student)->getJson('/api/v1/exams');

        $response->assertJsonPath('data.0.attempts_used', 0);
        $response->assertJsonPath('data.0.attempt_status', null);
    }

    public function test_the_teacher_exam_list_keeps_its_previous_fields(): void
    {
        $exam = $this->createExam($this->group);

        $response = $this->as($this->teacher)->getJson('/api/v1/exams');

        $response->assertOk();
        $response->assertExactJson([
            'status' => 'success',
            'code' => 200,
            'message' => 'OK',
            'data' => [['id' => (string) $exam->id, 'title' => $exam->title]],
            'errors' => null,
        ]);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('test')->plainTextToken);
    }

    private function createUser(string $role, string $firstName, string $lastName): User
    {
        return User::create([
            'role' => $role,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => uniqid("{$role}-", true).'@example.test',
            'password' => 'Demo1234',
            'is_active' => true,
        ]);
    }

    private function createGroup(string $folio, ?string $subject): Group
    {
        return Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => "Grupo {$folio}",
            'subject' => $subject,
            'access_folio' => $folio,
            'is_closed' => false,
        ]);
    }

    private function createExam(Group $group, int $questions = 0): Exam
    {
        $exam = Exam::create([
            'teacher_id' => $this->teacher->id,
            'title' => "Examen {$group->access_folio}",
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
        $exam->groups()->attach($group->id);

        if ($questions > 0) {
            $section = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => 'Única',
                'question_type' => 'open',
                'requested_quantity' => $questions,
                'sort_order' => 0,
            ]);

            for ($order = 0; $order < $questions; $order++) {
                $this->attachOpenQuestion($exam, $section, $order);
            }
        }

        return $exam;
    }

    private function attachOpenQuestion(Exam $exam, ExamSection $section, int $sortOrder): void
    {
        $item = OpenItem::create(['reference_answer' => 'respuesta de referencia']);

        $question = Question::create([
            'teacher_id' => $this->teacher->id,
            'title' => "Pregunta {$sortOrder}",
            'stem' => 'Explica',
            'question_type' => 'open',
            'default_points' => 1,
            'default_time_seconds' => 60,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $item->id,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 60,
            'points_weight' => 1,
            'sort_order' => $sortOrder,
        ]);
    }

    private function createAttempt(Exam $exam, string $status, ?User $student = null): StudentAttempt
    {
        return StudentAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => ($student ?? $this->student)->id,
            'group_id' => $this->group->id,
            'question_sequence_payload' => [],
            'started_at' => now(),
            'submitted_at' => $status === 'in_progress' ? null : now(),
            'status' => $status,
        ]);
    }
}
