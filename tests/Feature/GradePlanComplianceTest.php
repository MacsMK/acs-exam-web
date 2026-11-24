<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GradePlanComplianceTest.php
 * Ubicación:      tests/Feature/GradePlanComplianceTest.php
 * Requerimiento:  RF-CAL-006 a 008, 010 y 011, 013, 015, 016 y 018 a 020, 023 a 025 y 028, RF-REP-008 y 017
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Verifica configuración, correcciones, resultados y avisos con datos sintéticos.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EvaluationPeriod;
use App\Models\Exam;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Services\GroupGradeResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GradePlanComplianceTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private User $teacher;
    private User $student;
    private EvaluationPeriod $first;
    private EvaluationPeriod $second;

    /**
     * Prepara usuarios y datos sintéticos del escenario.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->group = Group::create(['teacher_id' => $this->teacher->id, 'name' => 'Grupo sintético', 'access_folio' => 'GRADE001']);
        $this->group->students()->attach($this->student, ['joined_at' => now()]);
        $this->first = $this->period('Primero', 0);
        $this->second = $this->period('Segundo', 1);
        $this->actingAs($this->teacher);
    }

    /**
     * Crea un periodo sintético en el mes indicado.
     *
     * @param string $name Nombre del registro sintético.
     * @param int $month Mes del periodo de prueba.
     * @return EvaluationPeriod Periodo creado.
     */
    private function period(string $name, int $month): EvaluationPeriod
    {
        return $this->group->evaluationPeriods()->create(['name' => $name, 'starts_at' => today()->addMonths($month),
            'ends_at' => today()->addMonths($month + 1), 'formula' => 'homework*1']);
    }

    /**
     * Envía la configuración final por la ruta web del docente.
     *
     * @param array $data Datos del formulario de prueba.
     * @return TestResponse Respuesta HTTP de la ruta probada.
     */
    private function postFinal(array $data): TestResponse
    {
        return $this->post(route('groups.final-plan.update', $this->group), $data);
    }

    /**
     * Envía las ponderaciones del periodo por su ruta web.
     *
     * @param EvaluationPeriod $period Periodo de evaluación, opcional en el plan del grupo.
     * @param array $data Datos del formulario de prueba.
     * @return TestResponse Respuesta HTTP de la ruta probada.
     */
    private function plan(EvaluationPeriod $period, array $data): TestResponse
    {
        return $this->post(route('groups.periods.plan.update', [$this->group, $period]), $data);
    }

    /**
     * Envía los rubros del estudiante a la sábana del periodo.
     *
     * @param EvaluationPeriod $period Periodo de evaluación, opcional en el plan del grupo.
     * @param array $fields Rubros y comentarios a guardar.
     * @return TestResponse Respuesta HTTP de la ruta probada.
     */
    private function capture(EvaluationPeriod $period, array $fields): TestResponse
    {
        return $this->post(route('groups.periods.gradebook.save', [$this->group, $period]),
            ['grades' => [$fields + ['student_id' => $this->student->id]]]);
    }

    /**
     * Crea una aplicación y un intento entregado en la escala indicada.
     *
     * @param string $name Nombre del registro sintético.
     * @param string $scale Escala del examen de prueba.
     * @param int $score Calificación que se contrasta con la escala.
     * @return int Código de salida o identificador de la operación.
     */
    private function application(string $name, string $scale, int $score): int
    {
        $exam = Exam::create(['teacher_id' => $this->teacher->id, 'title' => $name, 'grading_scale' => $scale,
            'starts_at' => now(), 'ends_at' => now()->addHour(), 'is_published' => true,
            'share_token' => $name, 'result_visibility_mode' => 'final_score']);
        $this->group->exams()->attach($exam);
        StudentAttempt::create(['group_id' => $this->group->id, 'exam_id' => $exam->id, 'student_id' => $this->student->id,
            'started_at' => now(), 'submitted_at' => now(), 'status' => 'submitted', 'question_sequence_payload' => [], 'final_score' => $score]);
        return DB::table('acs_exam_group')->where('exam_id', $exam->id)->value('id');
    }

    /**
     * Compara la sábana, el Excel y el resultado estudiantil de aplicaciones ponderadas.
     *
     * @return void
     */
    public function test_weighted_applications_use_their_group_period_scale_and_exact_total(): void
    {
        $a = $this->application('Examen-A', '0-10', 8);
        $b = $this->application('Examen-B', '0-100', 60);
        $this->application('Excluido', '0-100', 10);
        $this->postFinal(['scale' => '0-100', 'minimum' => '75', 'periods' => [$this->first->id => '100']])->assertSessionHasNoErrors();
        $cfg = ['components' => ['exams' => 100, 'homework' => 0, 'project' => 0, 'other' => 0]];
        $this->plan($this->first, $cfg + ['exams' => [$a => '75', $b => '24.99']])->assertSessionHasErrors('exams');
        $this->plan($this->first, $cfg + ['exams' => [$a => '75', $b => '25.01']])->assertSessionHasErrors('exams');
        $allocation = $cfg + ['exams' => [$a => '75', $b => '25']];
        $preview = $this->plan($this->first, $allocation)->assertOk()->assertSee('Confirmar cambio');
        $this->plan($this->first, $allocation + ['confirmation' => $preview->viewData('confirmation')])->assertSessionHasNoErrors()->assertRedirect();
        $this->plan($this->second, $cfg + ['exams' => [$a => '100']])->assertSessionHasErrors('exams');
        $this->get(route('groups.periods.gradebook', [$this->group, $this->first]))->assertOk()->assertSee('Final del grupo · 75');
        $export = $this->get(route('groups.periods.gradebook.export', [$this->group, $this->first]))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'acs_export_');
        try {
            file_put_contents($file, $export->streamedContent());
            $sheet = IOFactory::load($file)->getActiveSheet();
            $this->assertEquals(75, $sheet->getCell('D10')->getValue());
            $this->assertSame('75', $sheet->getCell('D10')->getFormattedValue());
            $this->assertSame('Aprobado', $sheet->getCell('I10')->getValue());
        } finally {
            unlink($file);
        }
        $this->actingAs($this->student)->get(route('student.grades.show', $this->group))->assertOk()
            ->assertSee('Definitiva · 75 · Aprobada');
    }

    /**
     * Verifica el orden de las dependencias, el cero real y el resultado incompleto.
     *
     * @return void
     */
    public function test_dependencies_require_earlier_periods_and_final_grade_requires_complete_results(): void
    {
        $weights = ['scale' => '0-10', 'minimum' => '8',
            'periods' => [$this->first->id => '40.00', $this->second->id => '60.00']];
        $this->postFinal(array_replace($weights, ['periods' => [$this->first->id => '99.99']]))->assertSessionHasErrors('periods');
        $this->postFinal($weights)->assertSessionHasNoErrors();
        $cfg = ['components' => ['exams' => 0, 'homework' => 50, 'project' => 0, 'other' => 0]];
        $this->plan($this->first, $cfg + ['previous' => [$this->first->id => 50]])->assertSessionHasErrors('previous');
        $this->plan($this->first, $cfg + ['previous' => [$this->second->id => 50]])->assertSessionHasErrors('previous');
        $this->plan($this->second, $cfg + ['previous' => [$this->first->id => 50]])->assertSessionHasNoErrors();
        $this->capture($this->first, ['homework_component' => 8])->assertSessionHasErrors('grades.0.homework_component');
        $this->assertDatabaseCount('acs_student_period_grades', 0);
        // La unidad solo se selecciona en este escenario sintético.
        config(['grading.manual_input_for_ten' => 'group_scale']);
        $this->capture($this->first, ['homework_component' => 8])->assertSessionHasNoErrors();
        $route = route('student.grades.show', $this->group);
        $this->actingAs($this->student)->get($route)->assertSee('Pendiente')->assertDontSee('Definitiva ·');
        $this->actingAs($this->teacher);
        $this->travelTo($this->second->starts_at);
        $this->capture($this->second, ['homework_component' => 0])->assertSessionHasNoErrors();
        $this->actingAs($this->student)->get($route)->assertSee('Definitiva · 5.60 · No aprobada');
    }

    /**
     * Verifica motivos, versiones, cancelación, pertenencia y avisos personales.
     *
     * @return void
     */
    public function test_comments_corrections_notices_and_confirmation_are_atomic_and_scoped(): void
    {
        $this->capture($this->first, ['homework_component' => 80, 'manual_comments' => ['homework' => 'Revisado en clase']])
            ->assertSessionHasNoErrors();
        $this->capture($this->first, ['homework_component' => 70])->assertSessionHasErrors('grades.0.reason');
        $this->assertDatabaseCount('acs_grade_notices', 0);
        $this->capture($this->first, ['homework_component' => 70, 'reason' => 'Corrección de captura'])->assertSessionHasNoErrors();
        $history = AuditLog::where('action', 'grades.manual_corrected')->sole();
        $this->assertEquals(80, $history->old_values['score']);
        $this->assertEquals(70, $history->new_values['score']);
        $this->assertSame('Corrección de captura', $history->new_values['reason']);
        $this->assertSame($this->teacher->id, $history->user_id);
        $this->assertNotNull($history->created_at);
        $this->get(route('groups.periods.gradebook', [$this->group, $this->first]))->assertSee('Revisado en clase');
        $cfg = ['components' => ['exams' => '0', 'homework' => '50', 'project' => '50', 'other' => '0']];
        $review = $this->plan($this->first, $cfg)->assertOk()->assertSee('Confirmar cambios')->assertSee('Volver a editar');
        $this->plan($this->first, $cfg + ['confirmation' => $review->viewData('confirmation'), 'edit_plan' => 1])
            ->assertRedirect(route('groups.periods.plan', [$this->group, $this->first]))
            ->assertSessionHasInput('components.homework', '50');
        $this->get(route('groups.periods.index', $this->group))->assertOk();
        $this->assertSame('homework*1', $this->first->fresh()->formula);
        $this->plan($this->first, $cfg + ['confirmation' => $review->viewData('confirmation')])->assertSessionHasNoErrors()->assertRedirect();
        $version = AuditLog::where('action', 'grades.configuration_changed')->sole();
        $this->assertEquals(100, $version->old_values['components']['homework']);
        $this->assertEquals(50, $version->new_values['components']['homework']);
        $this->assertSame($this->teacher->id, $version->user_id);
        $this->assertNotNull($version->created_at);
        $this->actingAs($this->student)->get(route('student.grades.index'))->assertOk()->assertSee('Nueva calificación · 70.00')
            ->assertDontSee('Corrección de captura');
        $other = User::factory()->create(['role' => 'student']);
        $this->actingAs($other)->get(route('student.grades.index'))->assertDontSee('Nueva calificación · 70.00');
        $this->actingAs($this->teacher);
        $this->capture($this->first, ['student_id' => $other->id, 'homework_component' => 100])->assertSessionHasErrors('grades');
    }

    /**
     * Comprueba dos fuentes, separación entre periodos y rechazo de una confirmación obsoleta.
     *
     * @return void
     */
    public function test_multiple_sources_and_stale_confirmation_preserve_the_current_plan(): void
    {
        $third = $this->period('Síntesis', 4);
        $this->postFinal(['scale' => '0-100', 'minimum' => 60, 'periods' => [$third->id => 100]])->assertRedirect();
        $this->capture($this->first, ['homework_component' => 80])->assertSessionHasNoErrors();
        $separate = ['components' => ['exams' => 0, 'homework' => 0, 'project' => 100, 'other' => 0]];
        $this->plan($this->second, $separate)->assertRedirect();
        $this->travelTo($this->second->starts_at);
        $this->capture($this->second, ['project_component' => 100])->assertSessionHasNoErrors();
        $plan = ['components' => array_fill_keys(['exams', 'homework', 'project', 'other'], 0),
            'previous' => [$this->first->id => '50', $this->second->id => '50']];
        $firstPreview = $this->plan($third, $plan)->assertOk()->viewData('confirmation');
        $this->capture($this->first, ['homework_component' => 60, 'reason' => 'Captura corregida'])->assertSessionHasNoErrors();
        $secondPreview = $this->plan($third, $plan + ['confirmation' => $firstPreview])->assertOk()->viewData('confirmation');
        $this->assertNotSame($firstPreview, $secondPreview);
        $this->assertCount(0, $third->previousPeriods);
        $this->plan($third, $plan + ['confirmation' => $secondPreview])->assertRedirect();
        $this->assertCount(2, $third->fresh()->previousPeriods);
        $this->actingAs($this->student)->get(route('student.grades.show', $this->group))
            ->assertSee('Definitiva · 80 · Aprobada');
    }

    /**
     * Contrasta la conversión inversa exacta y la protección de capturas históricas fraccionarias.
     *
     * @return void
     */
    public function test_inverse_conversion_is_exact_and_legacy_fractions_require_a_policy(): void
    {
        $this->capture($this->first, ['homework_component' => '85.25'])->assertSessionHasNoErrors();
        $this->postFinal(['scale' => '0-100', 'minimum' => 60])->assertSessionHasErrors('scale');
        $this->postFinal(['scale' => '0-10', 'minimum' => 6])->assertSessionHasErrors('scale');
        $this->assertEquals('85.25', $this->first->studentGrades()->sole()->final_grade);

        $this->group = Group::create(['teacher_id' => $this->teacher->id, 'name' => 'Conversión exacta', 'access_folio' => 'DECIMAL2']);
        $this->group->students()->attach($this->student, ['joined_at' => now()]);
        $period = $this->period('Periodo exacto', 4);
        $this->postFinal(['scale' => '0-10', 'minimum' => '8.5', 'periods' => [$period->id => 100]])->assertRedirect();
        $application = $this->application('Ochenta y cinco', '0-100', 85);
        $plan = ['components' => ['exams' => 100, 'homework' => 0, 'project' => 0, 'other' => 0],
            'exams' => [$application => 100]];
        $review = $this->plan($period, $plan)->assertOk()->viewData('confirmation');
        $this->plan($period, $plan + ['confirmation' => $review])->assertRedirect();
        $this->actingAs($this->student)->get(route('student.grades.show', $this->group))
            ->assertSee('Definitiva · 8.50 · Aprobada');
    }

    /**
     * Comprueba los límites de la escala y una media que requiere decidir el redondeo.
     *
     * @return void
     */
    public function test_ambiguous_rounding_is_not_published_and_threshold_is_validated(): void
    {
        $this->postFinal(['scale' => '0-10', 'minimum' => 11])->assertSessionHasErrors('minimum');
        $this->postFinal(['scale' => '0-100', 'minimum' => '80.5'])->assertSessionHasErrors('minimum');
        $this->postFinal(['scale' => '0-100', 'minimum' => 80, 'periods' => [$this->first->id => 100]])->assertSessionHasNoErrors();
        $this->capture($this->first, ['homework_component' => '80.5'])->assertSessionHasErrors('grades.0.homework_component');
        $this->plan($this->first, ['components' => ['exams'=>0,'homework'=>50,'project'=>50,'other'=>0]])->assertSessionHasNoErrors();
        $this->capture($this->first, ['homework_component' => 80, 'project_component' => 81])->assertSessionHasNoErrors();
        $results = app(GroupGradeResults::class)->calculate($this->group->fresh());
        $this->assertNull($results['finals'][$this->student->id]);
        $this->actingAs($this->student)->get(route('student.grades.show', $this->group))->assertDontSee('Definitiva ·');
    }
}
