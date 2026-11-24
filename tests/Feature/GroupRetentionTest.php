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
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MultipleChoiceItem;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\StudentPeriodGrade;
use App\Models\User;
use App\Services\GroupRetentionService;
use App\Services\SubscriptionLimitService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $answer = $this->recordAnswer($attempt);
        $period = EvaluationPeriod::create(['group_id' => $group->id, 'name' => 'Parcial 1', 'formula' => 'exams*0.6+homework*0.2+project*0.1+other*0.1', 'starts_at' => now()->subYears(2), 'ends_at' => now()->subYears(2)->addMonths(2)]);
        $nextPeriod = $period->replicate();
        $nextPeriod->name = 'Parcial 2';
        $nextPeriod->save();
        DB::table('acs_period_dependencies')->insert(['period_id' => $nextPeriod->id, 'source_id' => $period->id, 'weight' => 100]);
        DB::table('acs_period_exam_weights')->insert([
            'application_id' => DB::table('acs_exam_group')->where('exam_id', $exam->id)->where('group_id', $group->id)->value('id'),
            'period_id' => $period->id,
            'weight' => 100,
        ]);
        $grade = StudentPeriodGrade::create(['evaluation_period_id' => $period->id, 'student_id' => $student->id]);
        $individualLogs = [];
        foreach ([$answer, $attempt, $grade] as $record) {
            $individualLogs[] = AuditLog::create([
                'user_id' => $teacher->id,
                'action' => 'retention.test_evidence',
                'auditable_type' => $record::class,
                'auditable_id' => $record->id,
            ]);
        }

        $this->artisan('groups:purge-expired')->assertSuccessful();

        $this->assertSoftDeleted('acs_groups', ['id' => $group->id]);
        $this->assertNull(StudentAttempt::find($attempt->id));
        $this->assertNull(StudentAnswer::find($answer->id));
        $this->assertNull(EvaluationPeriod::find($period->id));
        $this->assertNull(EvaluationPeriod::find($nextPeriod->id));
        foreach ($individualLogs as $log) {
            $this->assertDatabaseMissing('acs_audit_logs', ['id' => $log->id]);
        }
        $this->assertDatabaseMissing('acs_group_student', ['group_id' => $group->id]);
        $this->assertDatabaseMissing('acs_exam_group', ['group_id' => $group->id]);
        $this->assertNotNull(Exam::withoutGlobalScopes()->find($exam->id));
        $this->assertTrue(AuditLog::where('action', GroupRetentionService::ACTION_DELETED)->where('auditable_id', $group->id)->exists());

        $this->actingAs($teacher)->get(route('groups.show', $group->id))->assertNotFound();
    }

    public function test_reopened_or_newly_closed_group_is_not_purged_from_a_previous_selection(): void
    {
        [$teacher, $student, $group, $exam] = $this->scenario(now()->subMonths(13));
        $attempt = $this->attempt($exam, $student, $group);
        $candidate = $group->fresh();
        $retention = app(GroupRetentionService::class);

        $this->actingAs($teacher)->post(route('groups.reopen', $group))->assertRedirect();
        $this->assertFalse($retention->purge($candidate));
        $this->post(route('groups.close', $group))->assertRedirect();
        $this->assertFalse($retention->purge($candidate));
        $this->assertSame(0, $retention->purgeExpired());
        $this->assertDatabaseHas('acs_student_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseMissing('acs_audit_logs', [
            'action' => GroupRetentionService::ACTION_DELETED, 'auditable_id' => $group->id,
        ]);
    }

    public function test_foreign_keys_preserve_evidence_when_deleting_a_parent(): void
    {
        [, $student, $group, $exam] = $this->scenario(null);
        $attempt = $this->attempt($exam, $student, $group);
        $answer = $this->recordAnswer($attempt);

        foreach ([$student, $attempt] as $parent) {
            try {
                DB::transaction(fn () => $parent->delete());
                $this->fail('La base debe impedir el borrado indirecto de evidencia académica.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->getCode(), ['23001', '23503']);
            }
        }

        $this->assertDatabaseHas('acs_student_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseHas('acs_student_answers', ['id' => $answer->id]);

        $migration = require database_path('migrations/2026_11_23_000005_bd01_2026_042_restrict_academic_evidence_deletion.php');
        $rules = DB::table('information_schema.referential_constraints')->whereIn('constraint_name', [
            'acs_student_attempts_student_id_foreign',
            'acs_student_answers_student_attempt_id_foreign',
        ]);
        $migration->down();
        $this->assertSame(['CASCADE', 'CASCADE'], (clone $rules)->pluck('delete_rule')->all());
        $migration->up();
        $this->assertSame(['RESTRICT', 'RESTRICT'], $rules->pluck('delete_rule')->all());
        $this->assertDatabaseHas('acs_student_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseHas('acs_student_answers', ['id' => $answer->id]);
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

    private function recordAnswer(StudentAttempt $attempt): StudentAnswer
    {
        $item = MultipleChoiceItem::create([
            'options_payload' => ['Una opción', 'Otra opción'],
            'correct_option_index' => 0,
        ]);
        $question = Question::create([
            'teacher_id' => $attempt->exam->teacher_id,
            'title' => 'Registro sintético',
            'stem' => 'Elige una opción.',
            'question_type' => 'multiple_choice',
            'default_points' => 10,
            'default_time_seconds' => 60,
            'questionable_type' => MultipleChoiceItem::class,
            'questionable_id' => $item->id,
        ]);
        $section = ExamSection::create([
            'exam_id' => $attempt->exam_id, 'title' => 'Apartado', 'question_type' => 'multiple_choice',
            'requested_quantity' => 1, 'sort_order' => 0,
        ]);
        $examQuestion = ExamQuestion::create([
            'exam_id' => $attempt->exam_id, 'exam_section_id' => $section->id, 'question_id' => $question->id,
            'points_weight' => 10, 'time_limit_seconds' => 60, 'sort_order' => 0,
        ]);

        return $attempt->answers()->create([
            'exam_question_id' => $examQuestion->id,
            'answer_payload' => ['selected_option_index' => 0],
            'points_awarded' => 10,
            'answered_at' => $attempt->submitted_at,
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
