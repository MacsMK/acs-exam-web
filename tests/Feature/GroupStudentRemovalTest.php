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

        $this->actingAs($teacher)
            ->delete(route('groups.students.remove', [$group, $student]))
            ->assertRedirect(route('groups.show', $group))
            ->assertSessionHas('status');

        $this->assertFalse($group->students()->where('acs_users.id', $student->id)->exists());
        $this->assertNotNull(StudentAttempt::find($attempt->id));
        $this->assertTrue(AuditLog::where('action', 'group.student_removed')->where('auditable_id', $group->id)->exists());
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

    private function attempt(Exam $exam, User $student, Group $group, string $status): StudentAttempt
    {
        return StudentAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'group_id' => $group->id,
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
