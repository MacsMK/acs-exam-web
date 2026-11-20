<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     WebExamIncidentAndErrorsTest.php
 * Ubicación:   tests/Feature/WebExamIncidentAndErrorsTest.php
 * Descripción: Certificación Feature de los ajustes normativos MPW-V2:
 *              reporte de incidentes anti-trampa desde navegador web
 *              (attempts.incident), protección IDOR multi-estudiante, vistas
 *              institucionales de error HTTP 404 con folio ACS- y limpieza de
 *              copy en el registro docente.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Events\ExamAttemptIncidentReported;
use App\Models\Exam;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebExamIncidentAndErrorsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $studentOwner;
    private User $foreignStudent;
    private Group $group;
    private Exam $exam;
    private StudentAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->studentOwner = User::factory()->create([
            'role' => 'student',
            'first_name' => 'Valeria',
            'last_name' => 'Navarro',
        ]);
        $this->foreignStudent = User::factory()->create([
            'role' => 'student',
            'first_name' => 'Diego',
            'last_name' => 'Paredes',
        ]);

        $this->group = Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Sistemas Operativos 6B',
            'subject' => 'Sistemas Operativos',
            'access_folio' => 'SO6B-INC1',
            'is_closed' => false,
        ]);
        $this->group->students()->attach($this->studentOwner->id, ['joined_at' => now()]);
        $this->group->students()->attach($this->foreignStudent->id, ['joined_at' => now()]);

        $this->exam = Exam::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Examen Parcial de Procesos',
            'description' => 'Evaluación supervisada en navegador web.',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 1,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $this->exam->groups()->attach($this->group->id);

        $this->attempt = StudentAttempt::create([
            'exam_id' => $this->exam->id,
            'group_id' => $this->group->id,
            'student_id' => $this->studentOwner->id,
            'question_sequence_payload' => [],
            'current_question_index' => 0,
            'started_at' => now(),
            'status' => 'in_progress',
            'incidents_count' => 0,
        ]);
    }

    public function test_student_owner_can_report_incident_and_attempt_is_cancelled_when_exceeding_max_allowed_incidents(): void
    {
        Event::fake([ExamAttemptIncidentReported::class]);

        $firstResponse = $this->actingAs($this->studentOwner)
            ->postJson(route('attempts.incident', $this->attempt), [
                'incident_type' => 'tab_switch',
            ]);

        $firstResponse->assertOk()
            ->assertExactJson([
                'incidents_count' => 1,
                'status' => 'in_progress',
                'cancelled' => false,
                'redirect_url' => route('attempts.result', $this->attempt),
            ]);

        $this->attempt->refresh();
        $this->assertSame(1, (int) $this->attempt->incidents_count);
        $this->assertSame('in_progress', $this->attempt->status);

        Event::assertDispatched(
            ExamAttemptIncidentReported::class,
            fn (ExamAttemptIncidentReported $event): bool => $event->examId === (int) $this->exam->id
                && $event->attemptId === (int) $this->attempt->id
                && $event->studentId === (int) $this->studentOwner->id
                && $event->incidentType === 'tab_switch'
                && $event->incidentCount === 1
                && $event->isCancelled === false,
        );

        $secondResponse = $this->actingAs($this->studentOwner)
            ->postJson(route('attempts.incident', $this->attempt), [
                'incident_type' => 'tab_switch',
            ]);

        $secondResponse->assertOk()
            ->assertExactJson([
                'incidents_count' => 2,
                'status' => 'cancelled_by_incident',
                'cancelled' => true,
                'redirect_url' => route('attempts.result', $this->attempt),
            ]);

        $this->attempt->refresh();
        $this->assertSame(2, (int) $this->attempt->incidents_count);
        $this->assertSame('cancelled_by_incident', $this->attempt->status);
        $this->assertNotNull($this->attempt->submitted_at);
        $this->assertSame(0.0, (float) $this->attempt->final_score);

        Event::assertDispatched(
            ExamAttemptIncidentReported::class,
            fn (ExamAttemptIncidentReported $event): bool => $event->examId === (int) $this->exam->id
                && $event->attemptId === (int) $this->attempt->id
                && $event->studentId === (int) $this->studentOwner->id
                && $event->incidentType === 'tab_switch'
                && $event->incidentCount === 2
                && $event->isCancelled === true,
        );
        Event::assertDispatchedTimes(ExamAttemptIncidentReported::class, 2);
    }

    public function test_foreign_student_receives_403_when_reporting_incident_on_another_students_attempt(): void
    {
        Event::fake([ExamAttemptIncidentReported::class]);

        $response = $this->actingAs($this->foreignStudent)
            ->postJson(route('attempts.incident', $this->attempt), [
                'incident_type' => 'tab_switch',
            ]);

        $response->assertForbidden();

        $this->attempt->refresh();
        $this->assertSame(0, (int) $this->attempt->incidents_count);
        $this->assertSame('in_progress', $this->attempt->status);

        Event::assertNotDispatched(ExamAttemptIncidentReported::class);
    }

    public function test_invalid_incident_type_returns_422_validation_error(): void
    {
        Event::fake([ExamAttemptIncidentReported::class]);

        $response = $this->actingAs($this->studentOwner)
            ->postJson(route('attempts.incident', $this->attempt), [
                'incident_type' => 'unsupported_type',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['incident_type']);

        $this->attempt->refresh();
        $this->assertSame(0, (int) $this->attempt->incidents_count);
        Event::assertNotDispatched(ExamAttemptIncidentReported::class);
    }

    public function test_nonexistent_web_route_returns_404_with_institutional_error_view_and_acs_folio(): void
    {
        $response = $this->get('/ruta-inexistente-verificacion-mpw-v2');

        $response->assertNotFound();
        $response->assertSee('A-Code Solutions');
        $response->assertSee('Página no encontrada');
        $response->assertSee('Folio de incidente');
        $response->assertSee('ACS-');
        $this->assertMatchesRegularExpression('/ACS-[A-F0-9]{8}/', (string) $response->getContent());
    }

    public function test_teacher_registration_view_no_longer_mentions_supervised_webcam(): void
    {
        $bladeContents = file_get_contents(resource_path('views/auth/register-teacher.blade.php'));

        $this->assertIsString($bladeContents);
        $this->assertStringNotContainsStringIgnoringCase('cámara web supervisada', $bladeContents);

        $response = $this->get(route('auth.register.teacher.show'));

        $response->assertOk();
        $response->assertDontSee('cámara web supervisada', false);
    }
}
