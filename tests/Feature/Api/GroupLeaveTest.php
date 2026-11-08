<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupLeaveTest.php
 * Ubicación:   tests/Feature/Api/GroupLeaveTest.php
 * Descripción: Salida voluntaria de un grupo en la API móvil (RF-GRU-012,
 *              RF-GRU-017, ADR-036): rechazo mientras haya un intento en
 *              curso del grupo sin finalizarlo, conservación de la evidencia
 *              académica, ocultamiento de resultados al alumno y control de
 *              roles y pertenencia.
 * ============================================================================
 */

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GroupLeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private User $classmate;

    private Group $group;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');

        $this->teacher = $this->createUser('teacher');
        $this->student = $this->createUser('student');
        $this->classmate = $this->createUser('student');

        $this->group = $this->createGroup('SALIR001');
        $this->group->students()->attach([
            $this->student->id => ['joined_at' => now()],
            $this->classmate->id => ['joined_at' => now()],
        ]);

        $this->exam = $this->createExam($this->group);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_student_leaves_the_group_and_the_membership_is_removed(): void
    {
        $response = $this->leave($this->student, $this->group);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('message', 'Saliste del grupo.');

        $this->assertFalse($this->group->students()->where('acs_users.id', $this->student->id)->exists());
        $this->assertTrue($this->group->students()->where('acs_users.id', $this->classmate->id)->exists());
        $this->assertDatabaseHas('acs_users', ['id' => $this->student->id]);
    }

    public function test_leaving_is_recorded_in_the_audit_log(): void
    {
        $this->leave($this->student, $this->group)->assertOk();

        $log = AuditLog::where('action', 'group.left')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->student->id, $log->user_id);
        $this->assertSame($this->group->id, $log->auditable_id);
    }

    public function test_an_attempt_in_progress_blocks_the_exit_and_is_not_finalized(): void
    {
        $attempt = $this->createAttempt($this->student, $this->group, 'in_progress');

        $response = $this->leave($this->student, $this->group);

        $response->assertStatus(409);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath(
            'message',
            'No puedes abandonar el grupo mientras tengas un examen en curso. Podrás hacerlo cuando ese intento haya finalizado.',
        );
        $this->assertTrue($this->group->students()->where('acs_users.id', $this->student->id)->exists());
        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    public function test_a_submitted_attempt_does_not_block_the_exit_and_is_preserved(): void
    {
        $attempt = $this->createAttempt($this->student, $this->group, 'submitted', 8.5);

        $this->leave($this->student, $this->group)->assertOk();

        $this->assertDatabaseHas('acs_student_attempts', ['id' => $attempt->id, 'status' => 'submitted']);
        $this->assertSame('8.50', $attempt->fresh()->final_score);
    }

    public function test_an_abandoned_attempt_whose_window_already_closed_does_not_block_the_exit(): void
    {
        $this->createAttempt($this->student, $this->group, 'in_progress');

        // La ventana del examen cerró hace más de 60 s (tolerancia): el intento
        // quedó abierto sin enviarse, pero ya no está "en curso".
        Carbon::setTestNow('2026-11-09 12:03:00');

        $this->leave($this->student, $this->group)->assertOk();
    }

    public function test_an_attempt_in_another_group_does_not_block_leaving_this_one(): void
    {
        $other = $this->createGroup('SALIR002');
        $other->students()->attach($this->student->id, ['joined_at' => now()]);
        $otherExam = $this->createExam($other);
        $this->createAttempt($this->student, $other, 'in_progress', null, $otherExam);

        $this->leave($this->student, $this->group)->assertOk();

        $this->assertTrue($other->students()->where('acs_users.id', $this->student->id)->exists());
    }

    public function test_an_attempt_of_a_classmate_does_not_block_the_exit(): void
    {
        $this->createAttempt($this->classmate, $this->group, 'in_progress');

        $this->leave($this->student, $this->group)->assertOk();
    }

    public function test_a_teacher_cannot_use_the_leave_endpoint(): void
    {
        $this->leave($this->teacher, $this->group)->assertStatus(403);
    }

    public function test_a_student_who_is_not_a_member_gets_a_validation_error(): void
    {
        $stranger = $this->createUser('student');

        $response = $this->leave($stranger, $this->group);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.group.0', 'No perteneces a este grupo.');
    }

    public function test_leaving_requires_authentication(): void
    {
        $this->postJson("/api/v1/groups/{$this->group->id}/leave")->assertStatus(401);
    }

    public function test_leaving_an_unknown_group_returns_404(): void
    {
        $this->as($this->student)->postJson('/api/v1/groups/999999/leave')->assertStatus(404);
    }

    public function test_the_results_of_the_group_are_hidden_after_leaving_and_come_back_when_rejoining(): void
    {
        $this->createAttempt($this->student, $this->group, 'submitted', 9.0);
        $this->createAttempt($this->classmate, $this->group, 'submitted', 7.0);

        $this->assertSame(1, $this->gradeRowsCount($this->student));

        $this->leave($this->student, $this->group)->assertOk();

        $this->assertSame(0, $this->gradeRowsCount($this->student));
        $this->assertSame(1, $this->gradeRowsCount($this->classmate));
        $this->assertDatabaseCount('acs_student_attempts', 2);

        $this->as($this->student)
            ->postJson('/api/v1/groups/join', ['folio' => 'SALIR001'])
            ->assertOk();

        $this->assertSame(1, $this->gradeRowsCount($this->student));
    }

    private function gradeRowsCount(User $user): int
    {
        $response = $this->as($user)->getJson('/api/v1/grades/statistics');
        $response->assertOk();

        return count($response->json('data.grades'));
    }

    private function leave(User $user, Group $group): TestResponse
    {
        return $this->as($user)->postJson("/api/v1/groups/{$group->id}/leave");
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('test')->plainTextToken);
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

    private function createGroup(string $folio): Group
    {
        return Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => "Grupo {$folio}",
            'access_folio' => $folio,
            'is_closed' => false,
        ]);
    }

    private function createExam(Group $group): Exam
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

        return $exam;
    }

    private function createAttempt(
        User $student,
        Group $group,
        string $status,
        ?float $finalScore = null,
        ?Exam $exam = null,
    ): StudentAttempt {
        return StudentAttempt::create([
            'exam_id' => ($exam ?? $this->exam)->id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'question_sequence_payload' => [],
            'started_at' => now(),
            'submitted_at' => $status === 'in_progress' ? null : now(),
            'status' => $status,
            'final_score' => $finalScore,
        ]);
    }
}
