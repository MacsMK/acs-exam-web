<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupRetentionTest.php
 * Ubicación:   tests/Feature/GroupRetentionTest.php
 * Requerimiento: RF-GRU-015 (CAM-009, ACT-WEB-022, BD01-2026-033), RF-SUS-010
 * Descripción: Aviso de 30 días y eliminación de grupos cerrados hace 12
 *              meses; se borra la información académica, se conservan los
 *              exámenes y el registro lógico del grupo, y la eliminación no
 *              restablece la prueba gratuita.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Exceptions\SubscriptionLimitExceededException;
use App\Models\AuditLog;
use App\Models\EvaluationPeriod;
use App\Models\Exam;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Services\GroupRetentionService;
use App\Services\SubscriptionLimitService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_warning_is_sent_once_thirty_days_before_deletion(): void
    {
        [, , $group] = $this->scenario(now()->subMonths(12)->addDays(10));
        $retention = app(GroupRetentionService::class);

        $this->assertSame(1, $retention->sendWarnings());
        $this->assertSame(0, $retention->sendWarnings());
        $this->assertTrue(AuditLog::where('action', GroupRetentionService::ACTION_WARNING)->where('auditable_id', $group->id)->exists());
    }

    public function test_recently_closed_and_active_groups_are_not_warned_nor_deleted(): void
    {
        [, , $recent] = $this->scenario(now()->subMonth());
        [, , $active] = $this->scenario(null);
        $retention = app(GroupRetentionService::class);

        $this->assertSame(0, $retention->sendWarnings());
        $this->assertSame(0, $retention->purgeExpired());
        $this->assertNotNull(Group::withoutGlobalScopes()->find($recent->id));
        $this->assertNotNull(Group::withoutGlobalScopes()->find($active->id));
    }

    public function test_expired_group_is_purged_keeping_exams_and_logical_record(): void
    {
        [$teacher, $student, $group, $exam] = $this->scenario(now()->subMonths(13));
        $attempt = $this->attempt($exam, $student, $group);
        $period = EvaluationPeriod::create(['group_id' => $group->id, 'name' => 'Parcial 1', 'formula' => 'exams*0.6+homework*0.2+project*0.1+other*0.1', 'starts_at' => now()->subYears(2), 'ends_at' => now()->subYears(2)->addMonths(2)]);

        $this->artisan('groups:purge-expired')->assertSuccessful();

        $this->assertSoftDeleted('acs_groups', ['id' => $group->id]);
        $this->assertNull(StudentAttempt::find($attempt->id));
        $this->assertNull(EvaluationPeriod::find($period->id));
        $this->assertDatabaseMissing('acs_group_student', ['group_id' => $group->id]);
        $this->assertDatabaseMissing('acs_exam_group', ['group_id' => $group->id]);
        $this->assertNotNull(Exam::withoutGlobalScopes()->find($exam->id));
        $this->assertTrue(AuditLog::where('action', GroupRetentionService::ACTION_DELETED)->where('auditable_id', $group->id)->exists());

        $this->actingAs($teacher)->get(route('groups.show', $group->id))->assertNotFound();
    }

    public function test_deleting_the_trial_group_does_not_restore_the_free_benefit(): void
    {
        $this->seed(SubscriptionPackageSeeder::class);
        [$teacher, , $group] = $this->scenario(now()->subMonths(13));

        app(GroupRetentionService::class)->purge($group);

        $this->expectException(SubscriptionLimitExceededException::class);
        app(SubscriptionLimitService::class)->ensureCanCreateGroup($teacher);
    }

    public function test_closed_group_detail_shows_the_deletion_date(): void
    {
        [$teacher, , $group] = $this->scenario(now()->subMonths(2));

        $this->actingAs($teacher)
            ->get(route('groups.show', $group))
            ->assertOk()
            ->assertSee('se eliminará automáticamente el', false)
            ->assertSee(now()->subMonths(2)->addMonths(12)->format('d/m/Y'));
    }

    /**
     * @return array{0: User, 1: User, 2: Group, 3: Exam}
     */
    private function scenario(?\DateTimeInterface $closedAt): array
    {
        $teacher = $this->user('teacher');
        $student = $this->user('student');

        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Auditoría 9A',
            'access_folio' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'is_closed' => $closedAt !== null,
            'closed_at' => $closedAt,
        ]);
        $group->students()->attach($student->id, ['joined_at' => now()->subYears(2)]);

        $exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Parcial 1',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 2,
            'starts_at' => now()->subYears(2),
            'ends_at' => now()->subYears(2)->addHours(2),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $exam->groups()->attach($group->id);

        return [$teacher, $student, $group, $exam];
    }

    private function attempt(Exam $exam, User $student, Group $group): StudentAttempt
    {
        return StudentAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'question_sequence_payload' => [],
            'current_question_index' => 0,
            'started_at' => now()->subYears(2),
            'submitted_at' => now()->subYears(2),
            'status' => 'submitted',
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
