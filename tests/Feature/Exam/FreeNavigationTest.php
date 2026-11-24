<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        FreeNavigationTest.php
 * Ubicación:      tests/Feature/Exam/FreeNavigationTest.php
 * Requerimiento:  RF-EXA-012, 014, 033 y 043
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Contrasta navegación, edición, entrega y vencimiento bajo políticas de prueba.
 * ============================================================================
 */

namespace Tests\Feature\Exam;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MultipleChoiceItem;
use App\Models\Question;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FreeNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Exam $exam;
    private array $configData;
    private array $questions;

    /**
     * Prepara usuarios y datos sintéticos del escenario.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $group = Group::create(['teacher_id' => $this->teacher->id, 'name' => 'Grupo de prueba', 'access_folio' => 'LIBRE001']);
        $group->students()->attach($this->student, ['joined_at' => now()]);
        $this->configData = ['title' => 'Navegación de prueba', 'grading_scale' => '0-100',
            'result_visibility_mode' => 'final_score', 'randomize_question_order' => false, 'max_allowed_incidents' => 2];
        $this->exam = Exam::create($this->configData + ['teacher_id' => $this->teacher->id, 'share_token' => 'libre',
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_published' => false]);
        $this->exam->groups()->attach($group);
        $section = ExamSection::create(['exam_id' => $this->exam->id, 'title' => 'Sección de prueba',
            'question_type' => 'multiple_choice', 'requested_quantity' => 2, 'sort_order' => 0]);
        foreach ([0, 1] as $i) {
            $item = MultipleChoiceItem::create(['options_payload' => [['index'=>0,'text'=>'A'],['index'=>1,'text'=>'B']],
                'correct_option_index' => 1]);
            $question = Question::create(['teacher_id' => $this->teacher->id, 'title' => 'Pregunta '.$i,
                'stem' => 'Enunciado '.$i, 'question_type' => 'multiple_choice', 'default_points' => 50,
                'default_time_seconds' => 10, 'questionable_type' => MultipleChoiceItem::class, 'questionable_id' => $item->id]);
            $eq = ExamQuestion::create(['exam_id' => $this->exam->id, 'exam_section_id' => $section->id,
                'question_id' => $question->id, 'time_limit_seconds' => 10, 'points_weight' => 50, 'sort_order' => $i]);
            $this->questions[] = $eq->id;
            $this->configData['questions'][$eq->id] = ['points_weight' => 50, 'time_limit_seconds' => 10];
        }
        $this->configData += ['navigation_mode' => 'free', 'free_duration_minutes' => 2];
    }

    /**
     * Guarda la configuración del examen por la ruta del docente.
     *
     * @param array $overrides Campos alternativos del escenario.
     * @return TestResponse Respuesta HTTP de la ruta probada.
     */
    private function configure(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->teacher)->put(route('exams.configuration.update', $this->exam), array_replace($this->configData, $overrides));
    }

    /**
     * Inicia el examen con el margen de transporte indicado para la prueba.
     *
     * @param int $grace Segundos de margen exclusivos de la prueba.
     * @return StudentAttempt Intento iniciado por el estudiante.
     */
    private function start(int $grace): StudentAttempt
    {
        // Valores exclusivos de la prueba, no representan una asignación comercial.
        config(['exams.free_navigation_tiers' => ['freemium'], 'exams.free_submission_grace_seconds' => $grace]);
        $this->configure()->assertSessionHasNoErrors();
        $this->assertEquals(2, $this->exam->fresh()->free_duration_minutes);
        $this->exam->update(['is_published' => true]);
        $start = $this->actingAs($this->student)->post(route('attempts.start', $this->exam))->assertRedirect();
        $cookie = config('session.cookie');
        $this->withCredentials()->withCookie($cookie, $start->getCookie($cookie)->getValue());
        return StudentAttempt::sole();
    }

    /**
     * Envía una respuesta con su revisión a la ruta de autoguardado.
     *
     * @param StudentAttempt $attempt Intento sintético del estudiante.
     * @param int $index Índice de la pregunta en la secuencia.
     * @param int $option Opción seleccionada.
     * @param int $revision Revisión que confirmó el servidor.
     * @return TestResponse Respuesta HTTP de la ruta probada.
     */
    private function save(StudentAttempt $attempt, int $index, int $option, int $revision): TestResponse
    {
        return $this->postJson(route('attempts.draft', $attempt), ['exam_question_id' => $this->questions[$index],
            'selected_option_index' => $option, 'revision' => $revision]);
    }

    /**
     * Comprueba el cierre por defecto de la función y la validación de su duración.
     *
     * @return void
     */
    public function test_no_package_or_grace_is_assigned_by_default_and_duration_is_required(): void
    {
        $this->configure()->assertSessionHasErrors('navigation_mode');
        config(['exams.free_navigation_tiers' => ['freemium']]);
        $this->configure()->assertSessionHasErrors('navigation_mode');
        config(['exams.free_submission_grace_seconds' => 0]);
        $this->configure(['free_duration_minutes' => 0])->assertSessionHasErrors('free_duration_minutes');
        $this->configure(['free_duration_minutes' => null])->assertSessionHasErrors('free_duration_minutes');
        $this->configure(['navigation_mode' => 'sequential'])->assertSessionHasNoErrors();
        $this->assertNull($this->exam->fresh()->free_duration_minutes);
    }

    /**
     * Verifica la reanudación, los reintentos de guardado y la entrega automática única.
     *
     * @return void
     */
    public function test_free_navigation_edits_persist_on_reconnect_and_expiry_only_finishes_once(): void
    {
        $attempt = $this->start(0);
        $this->get(route('attempts.show', ['attempt' => $attempt, 'pregunta'=>1]))->assertOk()->assertSee('Enunciado 1');
        $this->save($attempt, 1, 0, 0)->assertOk()->assertJsonPath('revision', 1);
        $this->travel(30)->seconds();
        $this->save($attempt, 0, 1, 0)->assertOk();
        $this->save($attempt, 1, 1, 1)->assertOk()->assertJsonPath('revision', 2);
        $this->save($attempt, 1, 1, 1)->assertOk()->assertJsonPath('revision', 2);
        $this->save($attempt, 1, 0, 1)->assertConflict();
        $this->get(route('attempts.show', ['attempt' => $attempt, 'pregunta'=>1]))->assertOk()
            ->assertViewHas('draft', ['selected_option_index'=>1])->assertViewHas('current', fn ($q) => $q['remaining_seconds'] === 90);
        $this->travel(90)->seconds();
        $this->artisan('exams:close-due-free')->assertSuccessful();
        $closed = $attempt->fresh();
        $this->assertSame('submitted', $closed->status);
        $this->assertEquals(100, $closed->final_score);
        $this->assertEquals($closed->free_close_at, $closed->submitted_at);
        $this->save($attempt, 1, 0, 2)->assertConflict();
        $this->artisan('exams:close-due-free')->assertSuccessful();
        $this->assertEquals($closed->submitted_at, $attempt->fresh()->submitted_at);
        $this->assertCount(2, $attempt->answers);
    }

    /**
     * Contrasta el límite exacto por petición y el rechazo de un incidente posterior.
     *
     * @return void
     */
    public function test_late_request_closes_without_scheduler_and_preserves_only_saved_answers(): void
    {
        $attempt = $this->start(0);
        $this->save($attempt, 0, 1, 0)->assertOk();
        $this->travel(120)->seconds();
        $this->save($attempt, 1, 1, 0)->assertConflict();
        $this->assertEquals(50, $attempt->fresh()->final_score);
        $this->assertCount(1, $attempt->answers);
        $this->postJson(route('attempts.incident', $attempt), ['incident_type' => 'tab_switch'])
            ->assertOk()->assertJsonPath('status', 'submitted');
        $this->assertEquals(0, $attempt->fresh()->incidents_count);
        $this->post(route('attempts.finish', $attempt))->assertRedirect(route('attempts.result', $attempt));
        $this->assertEquals($attempt->free_close_at, $attempt->fresh()->submitted_at);
    }

    /**
     * Comprueba el margen sintético y la inmutabilidad del intento entregado.
     *
     * @return void
     */
    public function test_explicit_transport_margin_is_snapshotted_and_delivery_freezes_answers(): void
    {
        $attempt = $this->start(60);
        config(['exams.free_submission_grace_seconds' => null]);
        $this->travel(121)->seconds();
        $this->assertTrue($attempt->free_close_at->isFuture());
        $this->save($attempt, 1, 1, 0)->assertOk();
        $this->post(route('attempts.finish', $attempt))->assertRedirect(route('attempts.result', $attempt));
        $this->save($attempt, 1, 0, 1)->assertConflict();
        $this->assertEquals(50, $attempt->fresh()->final_score);
    }
}
