<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     EvaluationPeriodGradebookTest.php
 * Ubicación:   tests/Feature/EvaluationPeriodGradebookTest.php
 * Descripción: Pruebas automatizadas de periodos de evaluación y sábana de
 *              calificaciones (RF-CAL-001..005, RF-CAL-009, RF-CAL-012,
 *              RF-CAL-017, RF-CAL-021, RF-CAL-027, RNF-SEG-004).
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\EvaluationPeriod;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\StudentPeriodGrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EvaluationPeriodGradebookTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $otherTeacher;
    private Group $group;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create([
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->otherTeacher = User::factory()->create([
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->group = Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Matemáticas Avanzadas',
            'subject' => 'Álgebra Lineal',
            'access_folio' => 'GRP-MAT-01',
            'is_closed' => false,
        ]);

        $this->student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->group->students()->attach($this->student->id, [
            'joined_at' => now(),
        ]);
    }

    public function test_teacher_can_view_evaluation_periods_index(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('groups.periods.index', $this->group));

        $response->assertOk();
        $response->assertViewIs('evaluation-periods.index');
        $response->assertSee('Periodos de Evaluación');
        $response->assertSee('Matemáticas Avanzadas');
    }

    public function test_other_teacher_cannot_access_periods_due_to_idor_protection(): void
    {
        $response = $this->actingAs($this->otherTeacher)
            ->get(route('groups.periods.index', $this->group));

        $response->assertForbidden();
    }

    public function test_cannot_create_period_if_weights_do_not_sum_to_100_percent(): void
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('groups.periods.store', $this->group), [
                'name' => 'Primer Parcial',
                'starts_at' => now()->format('Y-m-d'),
                'ends_at' => now()->addMonth()->format('Y-m-d'),
                'exams_weight' => 50,
                'homework_weight' => 20,
                'project_weight' => 10,
                'other_weight' => 10, // Suma = 90% (debe fallar)
            ]);

        $response->assertSessionHasErrors(['exams_weight']);
        $this->assertDatabaseCount('acs_evaluation_periods', 0);
    }

    public function test_teacher_can_create_period_with_valid_weights(): void
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('groups.periods.store', $this->group), [
                'name' => 'Primer Parcial',
                'starts_at' => now()->format('Y-m-d'),
                'ends_at' => now()->addMonth()->format('Y-m-d'),
                'exams_weight' => 60,
                'homework_weight' => 20,
                'project_weight' => 10,
                'other_weight' => 10, // Suma = 100%
            ]);

        $response->assertRedirect(route('groups.periods.index', $this->group));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('acs_evaluation_periods', [
            'group_id' => $this->group->id,
            'name' => 'Primer Parcial',
            'formula' => 'exams*0.6+homework*0.2+project*0.1+other*0.1',
        ]);
    }

    public function test_period_dates_enforce_six_month_minimum_and_allow_same_day_closure(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 31)->startOfDay());

        $this->actingAs($this->teacher)
            ->get(route('groups.periods.index', $this->group))
            ->assertOk()
            ->assertSee('min="2026-02-28"', false);

        $route = route('groups.periods.store', $this->group);
        $period = [
            'name' => 'Periodo de recuperación',
            'starts_at' => '2026-02-28',
            'ends_at' => '2026-02-28',
            'exams_weight' => 100,
            'homework_weight' => 0,
            'project_weight' => 0,
            'other_weight' => 0,
        ];

        $this->post($route, array_replace($period, ['starts_at' => '2026-02-27']))
            ->assertSessionHasErrors('starts_at');
        $this->post($route, array_replace($period, ['starts_at' => '2026-02-30']))
            ->assertSessionHasErrors('starts_at');
        $this->post($route, array_replace($period, ['starts_at' => '2026-03-01']))
            ->assertSessionHasErrors('ends_at');
        $this->assertDatabaseCount('acs_evaluation_periods', 0);

        $this->post($route, $period)
            ->assertRedirect(route('groups.periods.index', $this->group))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('acs_evaluation_periods', [
            'group_id' => $this->group->id,
            'name' => $period['name'],
            'starts_at' => $period['starts_at'],
            'ends_at' => $period['ends_at'],
        ]);
    }

    public function test_period_details_can_be_edited_without_rewriting_grades_and_links(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(12, 0));
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Periodo histórico',
            'formula' => 'homework*1',
            'starts_at' => '2026-01-01 18:00:00',
            'ends_at' => '2026-01-20 20:00:00',
        ]);
        $grade = $period->studentGrades()->create([
            'student_id' => $this->student->id,
            'homework_component' => 70,
            'final_grade' => 70,
            'manual_comments' => ['homework' => 'Evidencia revisada'],
        ]);
        $dependent = $this->group->evaluationPeriods()->create([
            'name' => 'Periodo siguiente', 'formula' => 'homework*1',
            'starts_at' => '2026-03-01', 'ends_at' => '2026-03-30',
        ]);
        $dependent->previousPeriods()->attach($period, ['weight' => 100]);
        $exam = Exam::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Aplicación vinculada',
            'share_token' => 'PERIOD-EDIT-TEST',
            'starts_at' => '2026-01-04 10:00:00', 'ends_at' => '2026-01-04 11:00:00',
        ]);
        $exam->groups()->attach($this->group);
        $applicationId = DB::table('acs_exam_group')->where('exam_id', $exam->id)->value('id');
        DB::table('acs_period_exam_weights')->insert([
            'period_id' => $period->id, 'application_id' => $applicationId, 'weight' => 100,
        ]);
        $edit = route('groups.periods.edit', [$this->group, $period]);
        $update = route('groups.periods.update', [$this->group, $period]);
        $details = ['name' => 'Periodo corregido', 'starts_at' => '2026-01-01', 'ends_at' => '2026-01-02'];

        $this->actingAs($this->teacher)->get($edit)->assertOk()->assertViewIs('evaluation-periods.edit');
        $this->from($edit)->patch($update, array_replace($details, ['starts_at' => '2025-12-31']))
            ->assertSessionHasErrors('starts_at');
        $this->patch($update, $details)->assertRedirect(route('groups.periods.index', $this->group))
            ->assertSessionHasNoErrors();

        $period->refresh();
        $this->assertSame('Periodo corregido', $period->name);
        $this->assertSame('2026-01-01 18:00:00', $period->starts_at->toDateTimeString());
        $this->assertSame('2026-01-02', $period->ends_at->toDateString());
        $this->assertSame('homework*1', $period->formula);
        $this->assertSame('70.00', $grade->fresh()->final_grade);
        $this->assertSame(['homework' => 'Evidencia revisada'], $grade->fresh()->manual_comments);
        $this->assertDatabaseHas('acs_period_exam_weights', ['period_id' => $period->id, 'application_id' => $applicationId]);
        $this->assertDatabaseHas('acs_period_dependencies', ['period_id' => $dependent->id, 'source_id' => $period->id]);
    }

    public function test_period_details_edit_preserves_dependency_order_in_both_directions(): void
    {
        $makePeriod = fn (string $name, string $start, string $end): EvaluationPeriod =>
            $this->group->evaluationPeriods()->create([
                'name' => $name, 'starts_at' => $start, 'ends_at' => $end, 'formula' => 'homework*1',
            ]);
        $source = $makePeriod('Primero', today()->subMonth()->toDateString(), today()->addDay()->toDateString());
        $period = $makePeriod('Segundo', today()->toDateString(), today()->addWeek()->toDateString());
        $dependent = $makePeriod('Tercero', today()->addWeek()->toDateString(), today()->addMonth()->toDateString());
        $period->previousPeriods()->attach($source, ['weight' => 100]);
        $dependent->previousPeriods()->attach($period, ['weight' => 100]);
        $edit = route('groups.periods.edit', [$this->group, $period]);
        $update = route('groups.periods.update', [$this->group, $period]);
        $details = [
            'name' => $period->name,
            'starts_at' => today()->subDay()->toDateString(),
            'ends_at' => today()->addMonth()->toDateString(),
        ];

        $this->actingAs($this->teacher)->from($edit)
            ->patch($update, array_replace($details, ['starts_at' => $source->starts_at->toDateString()]))
            ->assertSessionHasErrors('starts_at');
        $this->patch($update, array_replace($details, ['starts_at' => $dependent->starts_at->toDateString()]))
            ->assertSessionHasErrors('starts_at');
        $this->assertSame(today()->toDateString(), $period->fresh()->starts_at->toDateString());

        $this->patch($update, $details)->assertSessionHasNoErrors();
        $this->assertSame($details['starts_at'], $period->fresh()->starts_at->toDateString());

        // Un periodo histórico puede conservar una hora de inicio posterior en el mismo día.
        $dependent->update(['starts_at' => $dependent->starts_at->setTime(18, 0)]);
        $sameDayStart = $dependent->starts_at->toDateString();
        $this->patch($update, array_replace($details, ['starts_at' => $sameDayStart]))->assertSessionHasNoErrors();
        $this->assertSame($sameDayStart . ' 00:00:00', $period->fresh()->starts_at->toDateTimeString());
    }

    public function test_period_details_cannot_be_edited_outside_an_active_owned_group(): void
    {
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Periodo vigente', 'formula' => 'homework*1',
            'starts_at' => today(), 'ends_at' => today()->addWeek(),
        ]);
        $details = [
            'name' => 'Nombre nuevo',
            'starts_at' => today()->toDateString(),
            'ends_at' => today()->addWeek()->toDateString(),
        ];
        $edit = route('groups.periods.edit', [$this->group, $period]);
        $update = route('groups.periods.update', [$this->group, $period]);
        $this->actingAs($this->otherTeacher)->get($edit)->assertForbidden();
        $this->patch($update, $details)->assertForbidden();

        $otherGroup = Group::create([
            'teacher_id' => $this->teacher->id, 'name' => 'Otro grupo', 'access_folio' => 'OTHERGRP',
        ]);
        $this->actingAs($this->teacher)->get(route('groups.periods.edit', [$otherGroup, $period]))->assertNotFound();
        $this->patch(route('groups.periods.update', [$otherGroup, $period]), $details)->assertNotFound();

        $this->group->update(['is_closed' => true]);
        $this->get($edit)->assertForbidden();
        $this->patch($update, $details)->assertForbidden();
        $this->assertSame('Periodo vigente', $period->fresh()->name);
    }

    public function test_period_weights_accept_exact_hundredths_and_reject_other_precision(): void
    {
        $route = route('groups.periods.store', $this->group);
        $payload = [
            'name' => 'Parcial con centésimas',
            'starts_at' => today()->toDateString(),
            'ends_at' => today()->addMonth()->toDateString(),
            'exams_weight' => '33.33',
            'homework_weight' => '33.33',
            'project_weight' => '33.34',
            'other_weight' => '0',
        ];

        $this->actingAs($this->teacher)->post($route, array_replace($payload, ['project_weight' => '33.33']))
            ->assertSessionHasErrors('exams_weight');
        $this->post($route, array_replace($payload, ['exams_weight' => '33.333']))
            ->assertSessionHasErrors('exams_weight');
        $this->assertDatabaseCount('acs_evaluation_periods', 0);

        $this->post($route, $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('acs_evaluation_periods', [
            'group_id' => $this->group->id,
            'formula' => 'exams*0.3333+homework*0.3333+project*0.3334+other*0',
        ]);
    }

    public function test_teacher_can_view_gradebook(): void
    {
        $period = EvaluationPeriod::create([
            'group_id' => $this->group->id,
            'name' => 'Primer Parcial',
            'formula' => 'exams*0.6+homework*0.2+project*0.1+other*0.1',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('groups.periods.gradebook', [$this->group, $period]));

        $response->assertOk();
        $response->assertViewIs('evaluation-periods.gradebook');
        $response->assertSee('Control de Calificaciones');
        $response->assertSee($this->student->full_name);
        $response->assertSee('60 %'); // peso exámenes
    }

    public function test_other_teacher_cannot_access_gradebook(): void
    {
        $period = EvaluationPeriod::create([
            'group_id' => $this->group->id,
            'name' => 'Primer Parcial',
            'formula' => 'exams*0.6+homework*0.2+project*0.1+other*0.1',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->otherTeacher)
            ->get(route('groups.periods.gradebook', [$this->group, $period]));

        $response->assertForbidden();
    }

    public function test_teacher_can_save_grades_and_system_calculates_final_grade_accurately(): void
    {
        // Fórmula: 50% exámenes + 30% tareas + 20% proyectos
        $period = EvaluationPeriod::create([
            'group_id' => $this->group->id,
            'name' => 'Segundo Parcial',
            'formula' => 'exams*0.5+homework*0.3+project*0.2+other*0',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
        $exam = $this->createAssignedExam('PARCIAL-ENTREGADO');
        StudentAttempt::create([
            'group_id' => $this->group->id,
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'status' => 'submitted',
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'final_score' => 80,
            'question_sequence_payload' => [],
        ]);

        // Alumno obtiene: exámenes 80, tareas 90, proyectos 100
        // Cálculo esperado: (80 * 0.5) + (90 * 0.3) + (100 * 0.2) = 40 + 27 + 20 = 87.0
        $response = $this->actingAs($this->teacher)
            ->post(route('groups.periods.gradebook.save', [$this->group, $period]), [
                'grades' => [
                    [
                        'student_id' => $this->student->id,
                        'exams_component' => 80.0,
                        'homework_component' => 90.0,
                        'project_component' => 100.0,
                        'other_component' => null,
                    ],
                ],
            ]);

        $response->assertRedirect(route('groups.periods.gradebook', [$this->group, $period]));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('acs_student_period_grades', [
            'evaluation_period_id' => $period->id,
            'student_id' => $this->student->id,
            'exams_component' => 80.00,
            'homework_component' => 90.00,
            'project_component' => 100.00,
            'final_grade' => 87.00,
        ]);
    }

    public function test_gradebook_blocks_capture_before_start_and_allows_corrections_after_close(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(12, 0));
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Parcial programado',
            'formula' => 'homework*1',
            'starts_at' => today()->addDay()->setTime(18, 0),
            'ends_at' => today()->addDay()->setTime(20, 0),
        ]);
        $grade = $period->studentGrades()->create([
            'student_id' => $this->student->id,
            'homework_component' => 70,
            'final_grade' => 70,
        ]);
        $gradebook = route('groups.periods.gradebook', [$this->group, $period]);
        $save = route('groups.periods.gradebook.save', [$this->group, $period]);
        $capture = [
            'student_id' => $this->student->id,
            'homework_component' => 80,
            'reason' => 'Corrección de captura',
        ];

        $this->actingAs($this->teacher)->from($gradebook)->post($save, ['grades' => [$capture]])
            ->assertRedirect($gradebook)
            ->assertSessionHasErrors(['grades' => 'Podrás guardar calificaciones a partir del 24/11/2026']);
        $this->assertSame('70.00', $grade->fresh()->final_grade);
        $this->assertDatabaseMissing('acs_audit_logs', [
            'action' => 'grades.manual_corrected',
            'auditable_id' => $grade->id,
        ]);

        $this->travelTo($period->starts_at->startOfDay());
        $this->post($save, ['grades' => [$capture]])->assertSessionHasNoErrors();
        $this->assertSame('80.00', $grade->fresh()->final_grade);

        $this->travelTo($period->ends_at->startOfDay()->addDay());
        $this->post($save, ['grades' => [array_replace($capture, ['homework_component' => 90])]])
            ->assertSessionHasNoErrors();
        $this->assertSame('90.00', $grade->fresh()->final_grade);
    }

    public function test_incomplete_gradebook_pages_do_not_save_partial_grades(): void
    {
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Primer Parcial',
            'formula' => 'homework*1',
            'starts_at' => today(),
            'ends_at' => today()->addMonth(),
        ]);
        $second = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->group->students()->attach($second->id, ['joined_at' => now()]);
        $gradebook = route('groups.periods.gradebook', [$this->group, $period]);
        $save = route('groups.periods.gradebook.save', [$this->group, $period]);
        $payload = [
            'gradebook_page' => 1,
            'grades' => [$this->student->id => ['student_id' => $this->student->id, 'homework_component' => 80]],
        ];

        $this->actingAs($this->teacher)->from($gradebook)
            ->post($save, $payload)->assertSessionHasErrors('gradebook_complete');
        $this->from($gradebook)->post($save, $payload + ['gradebook_complete' => 1])->assertSessionHasErrors('grades');
        $this->assertDatabaseCount('acs_student_period_grades', 0);

        $students = app(\App\Services\GradebookEntryService::class)->students($this->group)->get();
        $payload['grades'] = $students->mapWithKeys(fn (User $student) => [
            $student->id => ['student_id' => $student->id, 'homework_component' => 80],
        ])->all();
        $this->from($gradebook)->post($save, $payload + ['gradebook_complete' => 1])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('groups.periods.gradebook', [$this->group, $period, 'page' => 1]));
        $this->assertDatabaseCount('acs_student_period_grades', 2);
    }

    public function test_gradebook_preserves_historical_zero_weight_grades_and_comments(): void
    {
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Parcial con tareas',
            'formula' => 'homework*1',
            'starts_at' => today(),
            'ends_at' => today()->addMonth(),
        ]);
        $grade = $period->studentGrades()->create([
            'student_id' => $this->student->id,
            'exams_component' => '33.50',
            'manual_comments' => ['exams' => 'Comentario histórico'],
        ]);
        $gradebook = route('groups.periods.gradebook', [$this->group, $period]);
        $save = route('groups.periods.gradebook.save', [$this->group, $period]);
        $fieldPrefix = 'grades['.$this->student->id.']';
        $this->actingAs($this->teacher)->get($gradebook)->assertOk()
            ->assertSee('No pondera')
            ->assertSee('Comentario histórico')
            ->assertDontSee('name="'.$fieldPrefix.'[exams_component]"', false)
            ->assertDontSee('name="'.$fieldPrefix.'[manual_comments][exams]"', false)
            ->assertSee('name="'.$fieldPrefix.'[homework_component]"', false);

        $row = ['student_id' => $this->student->id, 'homework_component' => '0,00'];
        $this->from($gradebook)->post($save, ['grades' => [$row + ['exams_component' => null]]])
            ->assertSessionHasErrors('grades.0.exams_component');
        $this->from($gradebook)->post($save, ['grades' => [$row + ['manual_comments' => ['exams' => 'Otro comentario']]]])
            ->assertSessionHasErrors('grades.0.manual_comments.exams');
        $this->assertNull($grade->fresh()->homework_component);

        $this->from($gradebook)->post($save, ['grades' => [$row + [
            'exams_component' => '33.5', 'manual_comments' => ['exams' => 'Comentario histórico'],
        ]]])->assertSessionHasNoErrors();
        $grade->refresh();
        $this->assertSame('33.50', $grade->exams_component);
        $this->assertSame(['exams' => 'Comentario histórico'], $grade->manual_comments);
        $this->assertSame('0.00', $grade->final_grade);
    }

    public function test_gradebook_does_not_offer_capture_for_a_period_weighted_only_by_prior_periods(): void
    {
        $source = $this->group->evaluationPeriods()->create([
            'name' => 'Primer parcial',
            'formula' => 'homework*1',
            'starts_at' => today()->subMonth(),
            'ends_at' => today()->subDay(),
        ]);
        $source->studentGrades()->create([
            'student_id' => $this->student->id, 'homework_component' => 80, 'final_grade' => 80,
        ]);
        $derived = $this->group->evaluationPeriods()->create([
            'name' => 'Segundo parcial',
            'formula' => 'exams*0+homework*0+project*0+other*0',
            'starts_at' => today(),
            'ends_at' => today()->addMonth(),
        ]);
        $derived->forceFill(['uses_grade_plan' => true])->save();
        $derived->previousPeriods()->attach($source, ['weight' => 100]);
        $gradebook = route('groups.periods.gradebook', [$this->group, $derived]);
        $save = route('groups.periods.gradebook.save', [$this->group, $derived]);
        $this->actingAs($this->teacher)->get($gradebook)->assertOk()
            ->assertSee('Periodo Primer parcial')
            ->assertSee('80.00')
            ->assertDontSee('id="gradebook-form"', false)
            ->assertDontSee('Guardar calificaciones');
        $this->from($gradebook)->post($save, ['grades' => [[
            'student_id' => $this->student->id, 'homework_component' => 99,
        ]]])->assertSessionHasErrors('grades.0.homework_component');
        $this->assertDatabaseMissing('acs_student_period_grades', ['evaluation_period_id' => $derived->id]);
    }

    public function test_periods_count_definitive_grades_once_per_student_and_include_the_closing_day(): void
    {
        $this->travelTo(today()->addHours(12));
        $first = $this->group->evaluationPeriods()->create([
            'name' => 'Primer Parcial',
            'formula' => 'homework*1',
            'starts_at' => today()->subMonth(),
            'ends_at' => today(),
        ]);
        $second = $this->group->evaluationPeriods()->create([
            'name' => 'Segundo Parcial',
            'formula' => 'homework*1',
            'starts_at' => today()->addDay(),
            'ends_at' => today()->addMonth(),
        ]);
        $ungraded = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->group->students()->attach($ungraded->id, ['joined_at' => now()]);
        foreach ([$first, $second] as $period) {
            $period->studentGrades()->create(['student_id' => $this->student->id, 'homework_component' => 0, 'final_grade' => 0]);
            $period->studentGrades()->create(['student_id' => $ungraded->id]);
        }

        $this->assertTrue($first->is_active_today);
        $this->actingAs($this->teacher)->get(route('groups.periods.index', $this->group))
            ->assertOk()
            ->assertViewHas('evaluatedStudentsTotal', 1)
            ->assertViewHas('gradedByPeriod', fn ($counts) => $counts->get($first->id) === 1 && $counts->get($second->id) === 1);
    }

    public function test_teacher_can_export_gradebook_to_excel_with_valid_metadata(): void
    {
        $period = EvaluationPeriod::create([
            'group_id' => $this->group->id,
            'name' => 'Primer Parcial',
            'formula' => 'exams*0.6+homework*0.2+project*0.1+other*0.1',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        StudentPeriodGrade::create([
            'evaluation_period_id' => $period->id,
            'student_id' => $this->student->id,
            'exams_component' => 85.00,
            'homework_component' => null, // Pendiente (RF-CAL-021)
            'project_component' => 90.00,
            'other_component' => 100.00,
            'final_grade' => null,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('groups.periods.gradebook.export', [$this->group, $period]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('attachment; filename=Calificaciones_', (string) $response->headers->get('content-disposition'));
    }

    /**
     * Verifica que las notas históricas incompletas queden pendientes en las tres consultas.
     * @return void
     */
    public function test_legacy_missing_component_stays_pending_until_actual_zero_is_captured(): void
    {
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Primer trimestre', 'formula' => 'exams*0.5+homework*0.5',
            'starts_at' => today(), 'ends_at' => today()->addMonth(),
        ]);
        $grade = $period->studentGrades()->create([
            'student_id' => $this->student->id, 'exams_component' => 80, 'final_grade' => 40,
        ]);
        $route = route('groups.periods.gradebook', [$this->group, $period]);
        $this->actingAs($this->teacher)->get($route)->assertOk()
            ->assertViewHas('savedGrades', fn ($rows) => $rows[$this->student->id]->final_grade === null);

        $export = $this->get(route('groups.periods.gradebook.export', [$this->group, $period]))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'acs_legacy_grade_');
        try {
            file_put_contents($file, $export->streamedContent());
            $sheet = IOFactory::load($file)->getActiveSheet();
            $this->assertSame('A-CODE SOLUTIONS — REPORTE OFICIAL DE CALIFICACIONES', $sheet->getCell('A1')->getValue());
            $this->assertSame('Pendiente', $sheet->getCell('H10')->getValue());
        } finally {
            unlink($file);
        }

        $this->actingAs($this->student)->get(route('student.grades.show', $this->group))->assertOk()
            ->assertViewHas('group', fn ($group) => $group->evaluationPeriods->first()->studentGrades->first()->final_grade === null);
        $this->assertSame('40.00', $grade->fresh()->final_grade);

        $save = route('groups.periods.gradebook.save', [$this->group, $period]);
        $this->actingAs($this->teacher)->post($save, ['grades' => [['student_id' => $this->student->id, 'exams_component' => 80]]])
            ->assertSessionHasNoErrors();
        $this->assertNull($grade->fresh()->final_grade);
        $this->post($save, ['grades' => [['student_id' => $this->student->id, 'homework_component' => 0]]])
            ->assertSessionHasNoErrors();
        $this->assertSame('40.00', $grade->fresh()->final_grade);
    }

    /**
     * Verifica que una sugerencia histórica solo combine resultados expresados en 0–100.
     * @return void
     */
    public function test_legacy_suggestion_is_withheld_when_results_have_incompatible_scales(): void
    {
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Primer trimestre', 'formula' => 'exams*1',
            'starts_at' => today(), 'ends_at' => today()->addMonth(),
        ]);
        $route = route('groups.periods.gradebook', [$this->group, $period]);

        foreach (['0-100' => 80, '0-10' => 8] as $scale => $score) {
            $exam = Exam::create(['teacher_id' => $this->teacher->id, 'title' => 'Examen '.$scale,
                'share_token' => 'LEGACY'.$scale, 'grading_scale' => $scale,
                'starts_at' => today(), 'ends_at' => now()->addHour(), 'is_published' => true]);
            $exam->groups()->attach($this->group);
            StudentAttempt::create(['group_id' => $this->group->id, 'exam_id' => $exam->id, 'student_id' => $this->student->id,
                'status' => 'submitted', 'started_at' => now()->subHour(), 'submitted_at' => now(),
                'final_score' => $score, 'question_sequence_payload' => []]);

            $expected = $scale === '0-100' ? 80.0 : null;
            $this->actingAs($this->teacher)->get($route)->assertOk()
                ->assertViewHas('examAverages', fn ($averages) => $averages->get($this->student->id) === $expected);
        }
    }

    public function test_manual_exam_grade_requires_a_current_delivery_of_a_scheduled_period_exam(): void
    {
        $this->travelTo(today()->addHours(18));
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Parcial', 'formula' => 'exams*0.5+homework*0.5',
            'starts_at' => today()->subDays(2), 'ends_at' => today(),
        ]);
        $gradebook = route('groups.periods.gradebook', [$this->group, $period]);
        $save = route('groups.periods.gradebook.save', [$this->group, $period]);
        $row = ['student_id' => $this->student->id, 'exams_component' => 75];

        $this->actingAs($this->teacher)->get($gradebook)->assertOk()
            ->assertViewHas('examDelivery', fn (array $delivery): bool => $delivery['requiredCount'] === 0);
        $this->from($gradebook)->post($save, ['grades' => [$row]])
            ->assertSessionHasErrors('grades.0.exams_component');

        $exam = $this->createAssignedExam('CAPTURA-PARCIAL', [
            'starts_at' => $period->ends_at->copy()->addHours(12),
        ]);
        $otherGroup = Group::create([
            'teacher_id' => $this->teacher->id, 'name' => 'Otro grupo', 'access_folio' => 'OTROGRP01',
        ]);
        $exam->groups()->attach($otherGroup);
        $otherGroup->students()->attach($this->student, ['joined_at' => now()]);
        $attemptFields = [
            'group_id' => $this->group->id,
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => $exam->starts_at,
            'question_sequence_payload' => [],
        ];

        $attempt = StudentAttempt::create($attemptFields);
        $this->from($gradebook)->post($save, ['grades' => [$row]])
            ->assertSessionHasErrors('grades.0.exams_component');

        $attempt->update(['status' => 'submitted']);
        $this->from($gradebook)->post($save, ['grades' => [$row]])
            ->assertSessionHasErrors('grades.0.exams_component');

        $afterPeriod = $period->ends_at->copy()->addDay()->addHour();
        $this->travelTo($afterPeriod->copy()->addHours(2));
        $attempt->update(['group_id' => $otherGroup->id, 'submitted_at' => $afterPeriod]);
        $this->from($gradebook)->post($save, ['grades' => [$row]])
            ->assertSessionHasErrors('grades.0.exams_component');

        $anotherStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->group->students()->attach($anotherStudent, ['joined_at' => now()]);
        StudentAttempt::create(array_replace($attemptFields, [
            'student_id' => $anotherStudent->id, 'status' => 'submitted', 'submitted_at' => $afterPeriod,
        ]));
        $this->from($gradebook)->post($save, ['grades' => [$row]])
            ->assertSessionHasErrors('grades.0.exams_component');
        $this->assertDatabaseMissing('acs_student_period_grades', ['evaluation_period_id' => $period->id]);

        $attempt->update(['group_id' => $this->group->id]);
        $this->from($gradebook)->post($save, ['grades' => [$row]])->assertSessionHasNoErrors();

        StudentAttempt::create(array_replace($attemptFields, ['attempt_number' => 2, 'started_at' => now()]));
        $this->from($gradebook)->post($save, ['grades' => [array_replace($row, ['exams_component' => 76, 'reason' => 'Corrección'])]])
            ->assertSessionHasErrors('grades.0.exams_component');
        $this->from($gradebook)->post($save, ['grades' => [$row + ['homework_component' => 70]]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('acs_student_period_grades', [
            'evaluation_period_id' => $period->id,
            'student_id' => $this->student->id,
            'exams_component' => 75,
            'homework_component' => 70,
        ]);
    }

    public function test_manual_exam_grade_requires_all_scheduled_deliveries_and_names_what_each_student_is_missing(): void
    {
        $this->travelTo(today()->addHours(18));
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Parcial con dos exámenes', 'formula' => 'exams*0.5+homework*0.5',
            'starts_at' => today()->subDays(2), 'ends_at' => today(),
        ]);
        $firstExam = $this->createAssignedExam('PARCIAL-ALGEBRA', [
            'title' => 'Parcial de álgebra',
            'starts_at' => $period->starts_at->copy()->addHours(10),
        ]);
        $secondExam = $this->createAssignedExam('PARCIAL-FUNCIONES', [
            'title' => 'Parcial de funciones',
            'starts_at' => $period->ends_at->copy()->addHours(10),
        ]);

        $otherGroup = Group::create([
            'teacher_id' => $this->teacher->id, 'name' => 'Otro grupo', 'access_folio' => 'OTROGRP01',
        ]);
        $otherApplication = $this->createAssignedExam('AJENO-AL-GRUPO', ['title' => 'Examen de otro grupo'], $otherGroup);
        $draft = $this->createAssignedExam('BORRADOR-NO-EXIGIBLE', ['title' => 'Examen sin publicar', 'is_published' => false]);
        $earlier = $this->createAssignedExam('ANTES-DEL-PERIODO', [
            'title' => 'Examen de fecha anterior', 'starts_at' => $period->starts_at->copy()->subDay(),
        ]);
        $later = $this->createAssignedExam('DESPUES-DEL-PERIODO', [
            'title' => 'Examen de fecha posterior', 'starts_at' => $period->ends_at->copy()->addDay(),
        ]);

        $secondStudent = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->group->students()->attach($secondStudent, ['joined_at' => now()]);
        $this->createExamAttempt($firstExam, $this->student);
        $this->createExamAttempt($secondExam, $secondStudent);

        $gradebook = route('groups.periods.gradebook', [$this->group, $period]);
        $save = route('groups.periods.gradebook.save', [$this->group, $period]);
        $studentExamField = 'name="grades['.$this->student->id.'][exams_component]"';
        $secondStudentExamField = 'name="grades['.$secondStudent->id.'][exams_component]"';
        $response = $this->actingAs($this->teacher)->get($gradebook)->assertOk()
            ->assertViewHas('examDelivery', fn (array $delivery): bool =>
                $delivery['requiredCount'] === 2
                && $delivery['missingByStudent'][$this->student->id] === [$secondExam->title]
                && $delivery['missingByStudent'][$secondStudent->id] === [$firstExam->title])
            ->assertSeeText($firstExam->title)
            ->assertSeeText($secondExam->title)
            ->assertDontSee($studentExamField, false)
            ->assertDontSee($secondStudentExamField, false);
        foreach ([$otherApplication, $draft, $earlier, $later] as $excluded) {
            $response->assertDontSeeText($excluded->title);
        }

        $row = ['student_id' => $this->student->id, 'exams_component' => 88];
        $this->from($gradebook)->post($save, ['grades' => [$row]])
            ->assertSessionHasErrors('grades.0.exams_component');
        $this->assertDatabaseMissing('acs_student_period_grades', ['evaluation_period_id' => $period->id]);

        $this->createExamAttempt($secondExam, $this->student);
        $this->get($gradebook)->assertOk()
            ->assertViewHas('examDelivery', fn (array $delivery): bool =>
                $delivery['requiredCount'] === 2
                && ($delivery['missingByStudent'][$this->student->id] ?? []) === []
                && $delivery['missingByStudent'][$secondStudent->id] === [$firstExam->title])
            ->assertSee($studentExamField, false)
            ->assertDontSee($secondStudentExamField, false);
        $this->from($gradebook)->post($save, ['grades' => [$row]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('acs_student_period_grades', [
            'evaluation_period_id' => $period->id, 'student_id' => $this->student->id, 'exams_component' => 88,
        ]);
    }

    public function test_weighted_applications_define_the_required_exams_and_keep_the_grade_pending_until_complete(): void
    {
        $this->group->forceFill(['grading_scale' => '0-100', 'passing_score' => 60])->save();
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Parcial con aplicaciones vinculadas', 'formula' => 'exams*1',
            'starts_at' => today()->subDay(), 'ends_at' => today()->addDay(),
        ]);
        $period->forceFill(['uses_grade_plan' => true])->save();
        $firstExam = $this->createAssignedExam('APLICACION-VINCULADA-A', [
            'title' => 'Aplicación vinculada de álgebra',
            'starts_at' => $period->starts_at->copy()->subDay(),
        ]);
        $secondExam = $this->createAssignedExam('APLICACION-VINCULADA-B', ['title' => 'Aplicación vinculada de aritmética']);
        $unlinked = $this->createAssignedExam('PROGRAMADO-SIN-VINCULO', ['title' => 'Examen programado sin vínculo']);
        foreach ([$firstExam, $secondExam] as $exam) {
            $applicationId = DB::table('acs_exam_group')->where('exam_id', $exam->id)
                ->where('group_id', $this->group->id)->value('id');
            DB::table('acs_period_exam_weights')->insert([
                'application_id' => $applicationId, 'period_id' => $period->id, 'weight' => 50,
            ]);
        }

        $this->createExamAttempt($firstExam, $this->student, ['final_score' => 80]);
        $gradebook = route('groups.periods.gradebook', [$this->group, $period]);
        $this->actingAs($this->teacher)->get($gradebook)->assertOk()
            ->assertViewHas('examDelivery', fn (array $delivery): bool =>
                $delivery['requiredCount'] === 2 && $delivery['missingByStudent'][$this->student->id] === [$secondExam->title])
            ->assertViewHas('savedGrades', fn ($grades) => $grades[$this->student->id]->final_grade === null)
            ->assertSeeText($secondExam->title)
            ->assertDontSeeText($unlinked->title);

        $this->createExamAttempt($secondExam, $this->student, ['final_score' => 100]);
        $this->get($gradebook)->assertOk()
            ->assertViewHas('examDelivery', fn (array $delivery): bool =>
                $delivery['requiredCount'] === 2 && ($delivery['missingByStudent'][$this->student->id] ?? []) === [])
            ->assertViewHas('savedGrades', fn ($grades) => (float) $grades[$this->student->id]->final_grade === 90.0)
            ->assertDontSeeText($secondExam->title)
            ->assertDontSeeText($unlinked->title);
    }

    public function test_manual_exam_grade_excludes_an_application_weighted_into_another_period(): void
    {
        $period = $this->group->evaluationPeriods()->create([
            'name' => 'Periodo de captura', 'formula' => 'exams*1',
            'starts_at' => today()->subDays(2), 'ends_at' => today()->addDay(),
        ]);
        $otherPeriod = $this->group->evaluationPeriods()->create([
            'name' => 'Periodo de la aplicación', 'formula' => 'exams*1',
            'starts_at' => today()->subDay(), 'ends_at' => today()->addDays(2),
        ]);
        $exam = $this->createAssignedExam('APLICACION-OTRO-PERIODO');
        $applicationId = DB::table('acs_exam_group')->where('exam_id', $exam->id)
            ->where('group_id', $this->group->id)->value('id');
        DB::table('acs_period_exam_weights')->insert([
            'application_id' => $applicationId, 'period_id' => $otherPeriod->id, 'weight' => 100,
        ]);
        StudentAttempt::create([
            'group_id' => $this->group->id,
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'status' => 'submitted',
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'final_score' => 80,
            'question_sequence_payload' => [],
        ]);

        $save = route('groups.periods.gradebook.save', [$this->group, $period]);
        $this->actingAs($this->teacher)->post($save, ['grades' => [[
            'student_id' => $this->student->id, 'exams_component' => 80,
        ]]])->assertSessionHasErrors('grades.0.exams_component');
        $this->assertDatabaseMissing('acs_student_period_grades', ['evaluation_period_id' => $period->id]);
    }

    public function test_other_teacher_cannot_export_gradebook_due_to_idor_protection(): void
    {
        $period = EvaluationPeriod::create([
            'group_id' => $this->group->id,
            'name' => 'Primer Parcial',
            'formula' => 'exams*0.6+homework*0.2+project*0.1+other*0.1',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->otherTeacher)
            ->get(route('groups.periods.gradebook.export', [$this->group, $period]));

        $response->assertForbidden();
    }

    public function test_cannot_create_period_if_group_is_closed(): void
    {
        $this->group->update(['is_closed' => true]);

        $payload = [
            'name' => 'Periodo en Grupo Cerrado',
            'starts_at' => now()->format('Y-m-d'),
            'ends_at' => now()->addMonth()->format('Y-m-d'),
            'exams_weight' => 50,
            'homework_weight' => 20,
            'project_weight' => 20,
            'other_weight' => 10,
        ];

        $response = $this->actingAs($this->teacher)
            ->post(route('groups.periods.store', $this->group), $payload);

        $response->assertForbidden();
    }

    private function createAssignedExam(string $shareToken, array $attributes = [], ?Group $group = null): Exam
    {
        $exam = Exam::create($attributes + [
            'teacher_id' => $this->teacher->id,
            'title' => 'Examen de prueba',
            'share_token' => $shareToken,
            'starts_at' => now(),
            'ends_at' => today()->addMonth(),
            'grading_scale' => '0-100',
            'is_published' => true,
        ]);
        $exam->groups()->attach($group ?? $this->group);

        return $exam;
    }

    private function createExamAttempt(Exam $exam, User $student, array $attributes = []): StudentAttempt
    {
        return StudentAttempt::create($attributes + [
            'group_id' => $this->group->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'started_at' => $exam->starts_at,
            'submitted_at' => now(),
            'question_sequence_payload' => [],
        ]);
    }
}
