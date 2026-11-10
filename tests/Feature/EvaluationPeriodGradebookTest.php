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

use App\Models\EvaluationPeriod;
use App\Models\Group;
use App\Models\StudentPeriodGrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertStringContainsString('attachment; filename=Sabana_', (string) $response->headers->get('content-disposition'));
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
}
