<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EvaluationPeriod;
use App\Models\Exam;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EvaluationPeriodExamSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(12, 0));
        $this->teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->group = Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Grupo de periodos',
            'access_folio' => 'GRP-PER-1',
        ]);
        $this->group->students()->attach($this->student, ['joined_at' => now()]);
        $this->actingAs($this->teacher);
    }

    public function test_creation_persists_the_selection_and_only_requires_the_selected_exam(): void
    {
        [$selected, $selectedId] = $this->application('Examen elegido de octubre', $this->group, [
            'starts_at' => '2026-10-27 09:00:00', 'ends_at' => '2026-10-27 12:00:00',
        ]);
        [$unselected] = $this->application('Examen de noviembre sin elegir', $this->group);
        $otherPeriod = $this->group->evaluationPeriods()->create([
            'name' => 'Periodo ponderado de octubre', 'formula' => 'exams*1',
            'starts_at' => '2026-10-20', 'ends_at' => '2026-10-31',
        ]);
        $this->weight($otherPeriod, $selectedId);

        $this->post(route('groups.periods.store', $this->group), $this->periodInput() + [
            'uses_exam_selection' => 1,
            'required_exam_ids' => [$selectedId],
        ])->assertSessionHasNoErrors()->assertRedirect(route('groups.periods.index', $this->group));

        $period = $this->group->evaluationPeriods()->where('name', 'Periodo de noviembre')->sole();
        $this->assertTrue($period->uses_exam_selection);
        $this->assertSame([$selectedId], $this->selection($period));
        $this->assertDatabaseHas('acs_period_exam_weights', ['period_id' => $otherPeriod->id, 'application_id' => $selectedId]);

        $gradebook = route('groups.periods.gradebook', [$this->group, $period]);
        $save = route('groups.periods.gradebook.save', [$this->group, $period]);
        $grade = ['grades' => [['student_id' => $this->student->id, 'exams_component' => 87]]];
        $this->get($gradebook)->assertOk()
            ->assertViewHas('examDelivery', fn (array $delivery): bool =>
                $delivery['requiredCount'] === 1 && $delivery['missingByStudent'][$this->student->id] === [$selected->title])
            ->assertSeeText($selected->title)
            ->assertDontSeeText($unselected->title);
        $this->from($gradebook)->post($save, $grade)->assertSessionHasErrors('grades.0.exams_component');

        StudentAttempt::create([
            'group_id' => $this->group->id,
            'student_id' => $this->student->id,
            'exam_id' => $selected->id,
            'status' => 'submitted',
            'started_at' => '2026-10-27 09:00:00',
            'submitted_at' => '2026-10-27 11:00:00',
            'question_sequence_payload' => [],
        ]);
        $this->from($gradebook)->post($save, $grade)->assertSessionHasNoErrors();
        $this->assertSame('87.00', $period->studentGrades()->sole()->exams_component);
    }

    public function test_date_only_updates_preserve_selected_ids_and_an_explicit_update_can_replace_them(): void
    {
        [$first, $firstId] = $this->application('Primera selección', $this->group);
        [, $secondId] = $this->application('Segunda selección', $this->group);
        $this->post(route('groups.periods.store', $this->group), $this->periodInput() + [
            'uses_exam_selection' => 1,
            'required_exam_ids' => [$firstId],
        ])->assertSessionHasNoErrors();

        $first->update(['is_published' => false]);
        $period = $this->group->evaluationPeriods()->sole();
        $update = route('groups.periods.update', [$this->group, $period]);
        $details = $this->periodDetails([
            'name' => 'Periodo reprogramado', 'starts_at' => '2026-11-17', 'ends_at' => '2026-11-27',
        ]);
        $this->patch($update, $details)->assertSessionHasNoErrors()
            ->assertRedirect(route('groups.periods.index', $this->group));
        $period->refresh();
        $this->assertTrue($period->uses_exam_selection);
        $this->assertSame('Periodo reprogramado', $period->name);
        $this->assertSame('2026-11-17', $period->starts_at->toDateString());
        $this->assertSame('2026-11-27', $period->ends_at->toDateString());
        $this->assertSame([$firstId], $this->selection($period));

        $this->patch($update, array_replace($details, ['ends_at' => '2026-11-28']) + [
            'uses_exam_selection' => 1,
            'required_exam_ids' => [$firstId],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$firstId], $this->selection($period));
        $this->get(route('groups.periods.edit', [$this->group, $period]))->assertOk()->assertSeeText($first->title);
        $this->get(route('groups.periods.gradebook', [$this->group, $period]))->assertOk()
            ->assertViewHas('examDelivery', fn (array $delivery): bool =>
                $delivery['requiredCount'] === 1 && $delivery['missingByStudent'][$this->student->id] === [$first->title]);

        $this->patch($update, $details + [
            'uses_exam_selection' => 1,
            'required_exam_ids' => [$secondId],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$secondId], $this->selection($period));
    }

    public function test_legacy_schedule_stays_active_until_the_teacher_sends_an_explicit_selection(): void
    {
        [$scheduled] = $this->application('Examen programado del esquema anterior', $this->group);
        $this->post(route('groups.periods.store', $this->group), $this->periodInput())->assertSessionHasNoErrors();
        $period = $this->group->evaluationPeriods()->sole();
        $gradebook = route('groups.periods.gradebook', [$this->group, $period]);
        $update = route('groups.periods.update', [$this->group, $period]);

        $this->assertFalse($period->uses_exam_selection);
        $this->assertSame([], $this->selection($period));
        $this->get($gradebook)->assertOk()->assertViewHas('examDelivery', fn (array $delivery): bool =>
            $delivery['requiredCount'] === 1 && $delivery['missingByStudent'][$this->student->id] === [$scheduled->title]);

        $details = $this->periodDetails(['ends_at' => '2026-11-27']);
        $this->patch($update, $details)->assertSessionHasNoErrors();
        $this->assertFalse($period->fresh()->uses_exam_selection);
        $this->get($gradebook)->assertOk()
            ->assertViewHas('examDelivery', fn (array $delivery): bool => $delivery['requiredCount'] === 1);

        $this->patch($update, $details + ['uses_exam_selection' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($period->fresh()->uses_exam_selection);
        $this->assertSame([], $this->selection($period));
        $this->get($gradebook)->assertOk()
            ->assertViewHas('examDelivery', fn (array $delivery): bool => $delivery['requiredCount'] === 0)
            ->assertDontSeeText($scheduled->title);
        $this->post(route('groups.periods.gradebook.save', [$this->group, $period]), [
            'grades' => [['student_id' => $this->student->id, 'exams_component' => 80]],
        ])->assertSessionHasErrors('grades.0.exams_component');
    }

    public function test_an_explicit_empty_selection_at_creation_does_not_infer_exams_by_date(): void
    {
        [$scheduled] = $this->application('Examen publicado en la fecha del periodo', $this->group);
        $this->post(route('groups.periods.store', $this->group), $this->periodInput() + ['uses_exam_selection' => 1])
            ->assertSessionHasNoErrors();
        $period = $this->group->evaluationPeriods()->sole();
        $this->assertTrue($period->uses_exam_selection);
        $this->assertSame([], $this->selection($period));
        $this->get(route('groups.periods.gradebook', [$this->group, $period]))->assertOk()
            ->assertViewHas('examDelivery', fn (array $delivery): bool => $delivery['requiredCount'] === 0)
            ->assertDontSeeText($scheduled->title);
    }

    public function test_foreign_and_unpublished_applications_are_rejected_atomically(): void
    {
        [, $validId] = $this->application('Examen seleccionable', $this->group);
        [, $draftId] = $this->application('Examen todavía sin publicar', $this->group, ['is_published' => false]);
        $otherGroup = Group::create([
            'teacher_id' => $this->teacher->id, 'name' => 'Otro grupo del docente', 'access_folio' => 'GRP-OTRO1',
        ]);
        [, $otherGroupId] = $this->application('Examen de otro grupo', $otherGroup);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        [, $otherTeacherId] = $this->application('Examen de otro docente', $this->group, ['teacher_id' => $otherTeacher->id]);

        $create = route('groups.periods.store', $this->group);
        foreach ([$draftId, $otherGroupId, $otherTeacherId] as $invalidId) {
            $this->post($create, $this->periodInput() + [
                'uses_exam_selection' => 1, 'required_exam_ids' => [$validId, $invalidId],
            ])->assertSessionHasErrors('required_exam_ids');
            $this->assertDatabaseCount('acs_evaluation_periods', 0);
            $this->assertDatabaseCount('acs_period_required_exams', 0);
        }

        $this->post($create, $this->periodInput() + [
            'uses_exam_selection' => 1, 'required_exam_ids' => [$validId],
        ])->assertSessionHasNoErrors();
        $period = $this->group->evaluationPeriods()->sole();
        $update = route('groups.periods.update', [$this->group, $period]);
        foreach ([$draftId, $otherGroupId, $otherTeacherId] as $invalidId) {
            $this->patch($update, $this->periodDetails(['name' => 'Edición inválida']) + [
                'uses_exam_selection' => 1, 'required_exam_ids' => [$invalidId],
            ])->assertSessionHasErrors('required_exam_ids');
            $this->assertSame('Periodo de noviembre', $period->fresh()->name);
            $this->assertSame([$validId], $this->selection($period));
        }
    }

    public function test_weighted_applications_in_the_current_period_stay_managed_by_the_grading_plan(): void
    {
        [, $weightedId] = $this->application('Examen ya ponderado del periodo', $this->group);
        [, $alternativeId] = $this->application('Examen alternativo', $this->group);
        $period = $this->group->evaluationPeriods()->create($this->periodDetails() + ['formula' => 'exams*1']);
        $this->weight($period, $weightedId);

        $update = route('groups.periods.update', [$this->group, $period]);
        $details = $this->periodDetails(['ends_at' => '2026-11-27']);
        $this->patch($update, $details + [
            'uses_exam_selection' => 1, 'required_exam_ids' => [$alternativeId],
        ])->assertSessionHasErrors('required_exam_ids');
        $this->assertSame('2026-11-25', $period->fresh()->ends_at->toDateString());
        $this->assertFalse($period->fresh()->uses_exam_selection);
        $this->assertSame([], $this->selection($period));
        $this->assertDatabaseHas('acs_period_exam_weights', ['period_id' => $period->id, 'application_id' => $weightedId]);

        $this->patch($update, $details)->assertSessionHasNoErrors();
        $this->assertSame('2026-11-27', $period->fresh()->ends_at->toDateString());
        $this->assertDatabaseHas('acs_period_exam_weights', ['period_id' => $period->id, 'application_id' => $weightedId]);
    }

    /**
     * @return array{Exam, int}
     */
    private function application(string $title, Group $group, array $attributes = []): array
    {
        $exam = Exam::create($attributes + [
            'teacher_id' => $this->teacher->id,
            'title' => $title,
            'share_token' => substr(fake()->unique()->sha1(), 0, 32),
            'starts_at' => '2026-11-19 10:00:00',
            'ends_at' => '2026-11-19 11:00:00',
            'is_published' => true,
            'grading_scale' => '0-100',
        ]);
        $exam->groups()->attach($group);
        $id = DB::table('acs_exam_group')->where('exam_id', $exam->id)->where('group_id', $group->id)->value('id');

        return [$exam, (int) $id];
    }

    private function periodDetails(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Periodo de noviembre',
            'starts_at' => '2026-11-15',
            'ends_at' => '2026-11-25',
        ];
    }

    private function periodInput(): array
    {
        return $this->periodDetails() + [
            'exams_weight' => 100,
            'homework_weight' => 0,
            'project_weight' => 0,
            'other_weight' => 0,
        ];
    }

    private function weight(EvaluationPeriod $period, int $applicationId): void
    {
        DB::table('acs_period_exam_weights')->insert([
            'period_id' => $period->id, 'application_id' => $applicationId, 'weight' => 100,
        ]);
    }

    /** @return list<int> */
    private function selection(EvaluationPeriod $period): array
    {
        return DB::table('acs_period_required_exams')->where('period_id', $period->id)
            ->orderBy('application_id')->pluck('application_id')->map(fn ($id) => (int) $id)->all();
    }
}
