<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupStudentRemovalTest.php
 * Ubicación:   tests/Feature/GroupStudentRemovalTest.php
 * Requerimiento: RF-GRU-011, RF-GRU-016 (ACT-WEB-021, CAM-008)
 * Descripción: El docente retira a un estudiante de su grupo con
 *              confirmación; se elimina la pertenencia, se conservan los
 *              registros académicos y no procede con un intento en curso.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\StudentPeriodGrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupStudentRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_removes_student_and_academic_records_are_kept(): void
    {
        [$teacher, $student, $group, $exam] = $this->scenario();
        $attempt = $this->attempt($exam, $student, $group, 'submitted');
        $attempt->update(['final_score' => 8]);
        $exam->update(['ends_at' => now()->subMinutes(5)]);

        $this->actingAs($student)->get(route('attempts.result', $attempt))->assertOk();
        $this->get(route('attempts.review', $attempt))->assertOk();
        $this->get(route('student.grades.index'))->assertViewHas('recentAttempts', fn ($attempts) => $attempts->contains('id', $attempt->id));
        $this->get(route('dashboard'))->assertViewHas('attemptRows', fn ($rows) => $rows->contains('attempt.id', $attempt->id));

        $this->actingAs($teacher)
            ->delete(route('groups.students.remove', [$group, $student]))
            ->assertRedirect(route('groups.show', $group))
            ->assertSessionHas('status');

        $this->assertFalse($group->students()->where('acs_users.id', $student->id)->exists());
        $this->assertNotNull(StudentAttempt::find($attempt->id));
        $this->assertTrue(AuditLog::where('action', 'group.student_removed')->where('auditable_id', $group->id)->exists());

        $this->get(route('groups.show', $group))
            ->assertOk()
            ->assertViewHas('group', static fn (Group $shownGroup): bool => $shownGroup->students->isEmpty())
            ->assertViewHas('attempts', static fn ($attempts): bool => $attempts->contains('id', $attempt->id))
            ->assertViewHas('currentEnrolledAttempts', static fn ($attempts): bool => $attempts->isEmpty())
            ->assertSee('Retirado')
            ->assertSee('8.00');

        $this->actingAs($student)->get(route('attempts.result', $attempt))->assertForbidden();
        $this->get(route('attempts.review', $attempt))->assertForbidden();
        $this->get(route('student.grades.index'))->assertViewHas('recentAttempts', fn ($attempts) => $attempts->isEmpty());
        $this->get(route('dashboard'))->assertViewHas('attemptRows', fn ($rows) => $rows->isEmpty());
    }

    public function test_a_closed_group_cannot_start_an_attempt_and_an_active_assignment_can(): void
    {
        [$teacher, $student, $closed, $exam] = $this->scenario();
        $this->actingAs($teacher)->post(route('groups.close', $closed))->assertRedirect();
        $classmate = $this->user('student');
        $this->actingAs($classmate)->post(route('student.groups.join'), ['folio' => $closed->access_folio])
            ->assertSessionHasErrors('access_folio');
        $this->assertFalse($closed->students()->whereKey($classmate->id)->exists());

        $this->actingAs($student)->post(route('attempts.start', $exam))->assertForbidden();
        $this->assertDatabaseMissing('acs_student_attempts', ['exam_id' => $exam->id, 'student_id' => $student->id]);

        $active = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo activo de control',
            'access_folio' => 'ACTIVO01',
        ]);
        $exam->groups()->attach($active->id);

        $this->post(route('student.groups.join'), ['folio' => $active->access_folio])
            ->assertRedirect(route('student.groups.index'))
            ->assertSessionHasNoErrors();
        $this->post(route('attempts.start', $exam))->assertRedirect();
        $this->assertDatabaseHas('acs_student_attempts', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'group_id' => $active->id,
        ]);
    }

    public function test_removal_is_rejected_while_the_student_has_an_attempt_in_progress(): void
    {
        [$teacher, $student, $group, $exam] = $this->scenario();
        $this->attempt($exam, $student, $group, 'in_progress');

        $this->actingAs($teacher)
            ->delete(route('groups.students.remove', [$group, $student]))
            ->assertSessionHasErrors('student');

        $this->assertTrue($group->students()->where('acs_users.id', $student->id)->exists());
    }

    public function test_closing_checks_only_live_attempts_in_its_own_group_and_preserves_the_close_date(): void
    {
        $this->freezeSecond();

        [$teacher, $student, $group, $exam] = $this->scenario();
        $otherGroup = Group::create(['teacher_id' => $teacher->id, 'name' => 'Otro grupo', 'access_folio' => 'OTRO0001']);
        $otherStudent = $this->user('student');
        $otherGroup->students()->attach($otherStudent->id, ['joined_at' => now()]);
        $exam->groups()->attach($otherGroup->id);
        $this->attempt($exam, $otherStudent, $otherGroup, 'in_progress');
        $ownAttempt = $this->attempt($exam, $student, $group, 'in_progress');

        $this->actingAs($teacher)->post(route('groups.close', $group))->assertSessionHasErrors('group');
        $this->assertFalse($group->fresh()->is_closed);

        $ownAttempt->update([
            'free_edit_until' => now()->subMinutes(3),
            'free_close_at' => now()->subMinute(),
        ]);
        $this->post(route('groups.close', $group))->assertSessionHasNoErrors();
        $this->assertTrue($group->fresh()->is_closed);
        $this->assertFalse($otherGroup->fresh()->is_closed);
        $closedAt = $group->fresh()->closed_at;

        $this->travel(5)->minutes();
        $this->post(route('groups.close', $group))->assertSessionHasNoErrors();
        $this->assertEquals($closedAt, $group->fresh()->closed_at);
    }

    public function test_group_history_distinguishes_replaced_attempts_and_retains_withdrawn_period_grades(): void
    {
        [$teacher, $student, $group, $exam] = $this->scenario();
        $replaced = $this->attempt($exam, $student, $group, 'submitted');
        $current = $this->attempt($exam, $student, $group, 'submitted', 2);
        $withdrawn = $this->user('student');
        $group->students()->attach($withdrawn->id, ['joined_at' => now()]);
        $period = $group->evaluationPeriods()->create([
            'name' => 'Periodo de prueba',
            'formula' => 'homework*1',
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->addWeek(),
        ]);
        $grade = StudentPeriodGrade::create([
            'evaluation_period_id' => $period->id,
            'student_id' => $withdrawn->id,
            'homework_component' => 90,
            'final_grade' => 90,
        ]);

        $this->actingAs($teacher)
            ->delete(route('groups.students.remove', [$group, $withdrawn]))
            ->assertSessionHasNoErrors();
        $this->get(route('groups.show', $group))
            ->assertOk()
            ->assertViewHas('attempts', static fn ($attempts): bool => $attempts->count() === 2)
            ->assertViewHas('currentEnrolledAttempts', static fn ($attempts): bool => $attempts->sole()->is($current))
            ->assertViewHas('currentAttemptIds', static fn ($ids): bool => ! $ids->has($replaced->id))
            ->assertViewHas('withdrawnPeriodGrades', static fn ($grades): bool => $grades->sole()->is($grade))
            ->assertSee('Reemplazado')
            ->assertSee('Retirado')
            ->assertSee('90.00');
    }

    public function test_another_teacher_cannot_remove_students_from_the_group(): void
    {
        [, $student, $group] = $this->scenario();
        $intruder = $this->user('teacher');

        $status = $this->actingAs($intruder)
            ->delete(route('groups.students.remove', [$group, $student]))
            ->getStatusCode();

        // 403 por la verificación de propiedad o 404 si el ámbito global oculta el grupo
        $this->assertContains($status, [403, 404]);

        $this->assertTrue($group->students()->where('acs_users.id', $student->id)->exists());
    }

    public function test_group_detail_shows_the_remove_action_with_confirmation(): void
    {
        [$teacher, , $group] = $this->scenario();

        $this->actingAs($teacher)
            ->get(route('groups.show', $group))
            ->assertOk()
            ->assertSee('¿Retirar a este estudiante?')
            ->assertSee('Sus registros académicos se conservarán', false);
    }

    private function scenario(): array
    {
        $teacher = $this->user('teacher');
        $student = $this->user('student');

        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Auditoría 9A',
            'access_folio' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'is_closed' => false,
        ]);
        $group->students()->attach($student->id, ['joined_at' => now()]);

        $exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Parcial 1',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 2,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $exam->groups()->attach($group->id);

        return [$teacher, $student, $group, $exam];
    }

    private function attempt(Exam $exam, User $student, Group $group, string $status, int $attemptNumber = 1): StudentAttempt
    {
        return StudentAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'attempt_number' => $attemptNumber,
            'question_sequence_payload' => [],
            'current_question_index' => 0,
            'started_at' => now()->subMinutes(5),
            'submitted_at' => $status === 'submitted' ? now() : null,
            'status' => $status,
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'role' => $role,
            'first_name' => $role === 'teacher' ? 'Ana' : 'Luis',
            'last_name' => 'Pérez',
            'email' => uniqid($role.'-', true).'@example.test',
            'phone' => '5512345678',
            'password' => 'Demo1234',
            'is_active' => true,
            'two_factor_verified_at' => now(),
        ]);
    }
}
