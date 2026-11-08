<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamMonitoringReverbTest.php
 * Ubicación:      tests/Feature/Realtime/ExamMonitoringReverbTest.php
 * Requerimiento:  RW-12, RW-16, RNF-CON-001, RNF-CON-002, RNF-SEG-004
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Pruebas automatizadas de la capa de tiempo real con Laravel Reverb
 *                 y WebSockets: canales privados autorizados, IDOR multi-docente,
 *                 y despacho de eventos de inicio, incidentes y entrega.
 * ============================================================================
 */

namespace Tests\Feature\Realtime;

use App\Events\ExamAttemptIncidentReported;
use App\Events\ExamAttemptStarted;
use App\Events\ExamAttemptSubmitted;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class ExamMonitoringReverbTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $foreignTeacher;
    private User $student;
    private Group $group;
    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->foreignTeacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student', 'first_name' => 'Carlos', 'last_name' => 'Santana']);

        $this->group = Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Grupo Algoritmos A',
            'subject' => 'Estructura de Datos',
            'access_folio' => 'FOLIO-REV-1',
        ]);
        $this->group->students()->attach($this->student->id, ['joined_at' => now()]);

        $this->exam = Exam::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Examen Parcial de Estructuras',
            'share_token' => 'REVTOKEN99',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_published' => true,
            'max_allowed_incidents' => 1,
        ]);
        $this->exam->groups()->attach($this->group->id);

        $section = ExamSection::create([
            'exam_id' => $this->exam->id,
            'title' => 'Sección General',
            'question_type' => 'open',
            'requested_quantity' => 1,
            'sort_order' => 1,
        ]);

        $openItem = OpenItem::create([
            'reference_answer' => 'Un árbol binario tiene a lo sumo dos hijos.',
            'evaluation_rubric' => 'Explicar concepto de árbol binario.',
            'allow_file_upload' => false,
        ]);

        $question = Question::create([
            'teacher_id' => $this->teacher->id,
            'subject_tag' => 'Estructuras',
            'title' => 'Árbol Binario',
            'stem' => 'Defina qué es un árbol binario.',
            'question_type' => 'open',
            'default_points' => 10.0,
            'default_time_seconds' => 120,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $openItem->id,
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'order_index' => 1,
            'points_weight' => 10.0,
            'time_limit_seconds' => 120,
        ]);
    }

    public function test_teacher_owner_can_access_exam_monitoring_dashboard(): void
    {
        // Crear un intento activo
        StudentAttempt::create([
            'exam_id' => $this->exam->id,
            'group_id' => $this->group->id,
            'student_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'current_question_index' => 1,
            'incidents_count' => 0,
            'question_sequence_payload' => [1],
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('exams.monitor', $this->exam));

        $response->assertOk();
        $response->assertSee('Monitoreo en Vivo');
        $response->assertSee('Carlos Santana');
        $response->assertSee('Examen Parcial de Estructuras');
        $response->assertSee('Reverb Conectado en Vivo');
        $response->assertSee('wsKey =');
    }

    public function test_foreign_teacher_cannot_access_exam_monitoring_dashboard(): void
    {
        $response = $this->actingAs($this->foreignTeacher)
            ->get(route('exams.monitor', $this->exam));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('exams.monitor', $this->exam));

        $response->assertRedirect('/login');
    }

    public function test_private_channel_authorization_grants_access_only_to_exam_teacher_owner(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        // Docente titular: autoriza exitosamente retornando firma HMAC
        $reqTeacher = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-exam.' . $this->exam->id,
            'socket_id' => '1234.5678',
        ]);
        $reqTeacher->setUserResolver(fn () => $this->teacher);
        $authResponse = \Illuminate\Support\Facades\Broadcast::auth($reqTeacher);
        $this->assertIsArray($authResponse);
        $this->assertArrayHasKey('auth', $authResponse);

        // Docente ajeno: deniega lanzando AccessDeniedHttpException (403)
        $reqForeign = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-exam.' . $this->exam->id,
            'socket_id' => '1234.5678',
        ]);
        $reqForeign->setUserResolver(fn () => $this->foreignTeacher);
        $foreignDenied = false;
        try {
            \Illuminate\Support\Facades\Broadcast::auth($reqForeign);
        } catch (AccessDeniedHttpException) {
            $foreignDenied = true;
        }
        $this->assertTrue($foreignDenied, 'El docente ajeno no debe poder autorizarse en el canal privado.');

        // Estudiante: deniega lanzando AccessDeniedHttpException (403)
        $reqStudent = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-exam.' . $this->exam->id,
            'socket_id' => '1234.5678',
        ]);
        $reqStudent->setUserResolver(fn () => $this->student);
        $studentDenied = false;
        try {
            \Illuminate\Support\Facades\Broadcast::auth($reqStudent);
        } catch (AccessDeniedHttpException) {
            $studentDenied = true;
        }
        $this->assertTrue($studentDenied, 'El estudiante no debe poder autorizarse en el canal de monitoreo docente.');

        config(['broadcasting.default' => 'null']);
    }

    public function test_exam_attempt_started_dispatches_realtime_event(): void
    {
        Event::fake([ExamAttemptStarted::class]);

        /** @var AttemptService $attemptService */
        $attemptService = $this->app->make(AttemptService::class);
        $attempt = $attemptService->start($this->exam, $this->student);

        Event::assertDispatched(ExamAttemptStarted::class, function (ExamAttemptStarted $event) use ($attempt) {
            $channel = $event->broadcastOn();
            return $event->examId === $this->exam->id
                && $event->attemptId === $attempt->id
                && $event->studentId === $this->student->id
                && $event->studentName === $this->student->full_name
                && $event->status === 'in_progress'
                && $channel->name === 'private-exam.' . $this->exam->id;
        });
    }

    public function test_incident_reporting_broadcasts_event_and_cancels_if_exceeded(): void
    {
        Event::fake([
            ExamAttemptStarted::class,
            ExamAttemptIncidentReported::class,
            ExamAttemptSubmitted::class,
        ]);

        /** @var AttemptService $attemptService */
        $attemptService = $this->app->make(AttemptService::class);
        $attempt = $attemptService->start($this->exam, $this->student);

        // Primer incidente (dentro de tolerancia: max_allowed_incidents = 1)
        $attempt = $attemptService->reportIncident($attempt, 'tab_switch', 'Cambio a pestaña de navegador');

        $this->assertSame(1, $attempt->incidents_count);
        $this->assertSame('in_progress', $attempt->status);

        Event::assertDispatched(ExamAttemptIncidentReported::class, function (ExamAttemptIncidentReported $event) use ($attempt) {
            return $event->attemptId === $attempt->id
                && $event->incidentType === 'tab_switch'
                && $event->incidentCount === 1
                && ! $event->isCancelled;
        });

        // Segundo incidente (excede tolerancia: se cancela automáticamente)
        $attempt = $attemptService->reportIncident($attempt, 'focus_lost', 'Ventana minimizada');

        $this->assertSame(2, $attempt->incidents_count);
        $this->assertSame('cancelled_by_incident', $attempt->status);
        $this->assertSame(0.0, (float) $attempt->final_score);

        Event::assertDispatched(ExamAttemptIncidentReported::class, function (ExamAttemptIncidentReported $event) use ($attempt) {
            return $event->attemptId === $attempt->id
                && $event->incidentType === 'focus_lost'
                && $event->incidentCount === 2
                && $event->isCancelled === true;
        });
    }

    public function test_exam_attempt_submitted_dispatches_realtime_event(): void
    {
        Event::fake([
            ExamAttemptStarted::class,
            ExamAttemptIncidentReported::class,
            ExamAttemptSubmitted::class,
        ]);

        /** @var AttemptService $attemptService */
        $attemptService = $this->app->make(AttemptService::class);
        $attempt = $attemptService->start($this->exam, $this->student);

        $submitted = $attemptService->submitFinal($attempt);

        Event::assertDispatched(ExamAttemptSubmitted::class, function (ExamAttemptSubmitted $event) use ($submitted) {
            $channel = $event->broadcastOn();
            return $event->examId === $this->exam->id
                && $event->attemptId === $submitted->id
                && $event->studentId === $this->student->id
                && $channel->name === 'private-exam.' . $this->exam->id;
        });
    }
}
