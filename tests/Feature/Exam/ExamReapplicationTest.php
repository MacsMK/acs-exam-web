<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamReapplicationTest.php
 * Ubicación:      tests/Feature/Exam/ExamReapplicationTest.php
 * Requerimiento:  RF-EXA-051 (control de intentos), RF-EXA-052 (reaplicación)
 * Actividad:      ACT-WEB-032
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Verifica que el docente autorice un intento adicional
 *                 individual mientras el examen sigue abierto, que el
 *                 intento anterior se conserve con sus resultados y que la
 *                 calificación vigente sea la del intento más reciente.
 *                 v1.1.0: la API del alumno informa el intento más reciente
 *                 y suma el intento autorizado mientras no se inicia.
 * ============================================================================
 */

namespace Tests\Feature\Exam;

use App\Contracts\PushNotifierContract;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MultipleChoiceItem;
use App\Models\Question;
use App\Models\StudentAttempt;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use App\Services\AttemptService;
use App\Services\TeacherGradingService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

class ExamReapplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        $this->app->instance(PushNotifierContract::class, new FakePushNotifier());

        $this->teacher = $this->createTeacher();
        $this->student = User::factory()->student()->create();

        $group = Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Grupo reaplicación',
            'access_folio' => 'REAPLI01',
            'is_closed' => false,
        ]);
        $group->students()->attach($this->student->id, ['joined_at' => now()]);

        $this->exam = Exam::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Examen con reaplicación',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 3,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $this->exam->groups()->attach($group->id);

        $section = ExamSection::create([
            'exam_id' => $this->exam->id,
            'title' => 'Única',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);
        $this->attachQuestion($section);
    }

    public function test_without_authorization_the_student_cannot_start_a_second_attempt(): void
    {
        $first = $this->startAndSubmit();

        $this->actingAs($this->student)
            ->get(route('student.exams.instructions', $this->exam))
            ->assertRedirect(route('attempts.result', $first));

        $this->assertSame($first->id, app(AttemptService::class)->start($this->exam, $this->student)->id);
        $this->assertSame(1, StudentAttempt::where('exam_id', $this->exam->id)->count());
    }

    public function test_teacher_authorizes_reapplication_and_previous_attempt_is_kept(): void
    {
        $first = $this->startAndSubmit();
        $first->update(['final_score' => 5]);

        $this->actingAs($this->teacher)
            ->post(route('exams.revision.reapply', [$this->exam, $first]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull($first->fresh()->reapplication_authorized_at);
        $this->assertDatabaseHas('acs_audit_logs', [
            'user_id' => $this->teacher->id,
            'action' => StudentAttempt::ACTION_REAPPLICATION_AUTHORIZED,
            'auditable_id' => $first->id,
        ]);

        // El alumno vuelve a ver las instrucciones, avisado de que es una reaplicación
        $this->actingAs($this->student)
            ->get(route('student.exams.instructions', $this->exam))
            ->assertOk()
            ->assertViewHas('reapplicationNumber', 2)
            ->assertSee('Reaplicación autorizada');

        $second = app(AttemptService::class)->start($this->exam, $this->student);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $second->attempt_number);
        $this->assertSame('in_progress', $second->status);

        // El intento anterior se conserva con sus resultados
        $kept = $first->fresh();
        $this->assertSame('submitted', $kept->status);
        $this->assertSame('5.00', (string) $kept->final_score);

        // Reanudar no crea otro intento
        $this->assertSame($second->id, app(AttemptService::class)->start($this->exam, $this->student)->id);
        $this->assertSame(2, StudentAttempt::where('exam_id', $this->exam->id)->count());
    }

    public function test_api_lists_the_latest_attempt_and_the_authorized_reapplication(): void
    {
        $first = $this->startAndSubmit();
        app(AttemptService::class)->authorizeReapplication($first, $this->teacher);

        $this->withToken($this->student->createToken('test')->plainTextToken)
            ->getJson('/api/v1/exams')
            ->assertOk()
            ->assertJsonPath('data.0.attempt_status', 'submitted')
            ->assertJsonPath('data.0.attempts_used', 1)
            ->assertJsonPath('data.0.attempts_allowed', 2);

        app(AttemptService::class)->start($this->exam, $this->student);
        $this->app['auth']->forgetGuards();

        $this->withToken($this->student->createToken('test')->plainTextToken)
            ->getJson('/api/v1/exams')
            ->assertOk()
            ->assertJsonPath('data.0.attempt_status', 'in_progress')
            ->assertJsonPath('data.0.attempts_used', 2)
            ->assertJsonPath('data.0.attempts_allowed', 2);
    }

    public function test_the_grade_that_counts_is_the_one_of_the_latest_attempt(): void
    {
        $first = $this->startAndSubmit();
        $first->update(['final_score' => 5]);
        app(AttemptService::class)->authorizeReapplication($first->fresh(), $this->teacher);

        $second = $this->startAndSubmit();
        $second->update(['final_score' => 9]);

        $rows = app(TeacherGradingService::class)->gradeRowsForUser($this->student->fresh());

        $this->assertCount(1, $rows);
        $this->assertSame((string) $second->id, $rows->first()['id']);
        $this->assertSame(9.0, $rows->first()['score']);

        // En la revisión del docente se ven ambos intentos y el anterior aparece reemplazado
        $this->actingAs($this->teacher)
            ->get(route('exams.revision.index', $this->exam))
            ->assertOk()
            ->assertSee('Intento 1 (reemplazado)')
            ->assertSee('Intento 2');
    }

    public function test_reapplication_is_rejected_in_the_invalid_cases(): void
    {
        $service = app(AttemptService::class);

        // Intento en curso
        $attempt = $service->start($this->exam, $this->student);
        $this->assertFalse($service->canAuthorizeReapplication($attempt));

        $service->submitFinal($attempt);
        $attempt = $attempt->fresh();
        $this->assertTrue($service->canAuthorizeReapplication($attempt));

        // Otro docente no puede autorizar
        $this->actingAs($this->createTeacher())
            ->post(route('exams.revision.reapply', [$this->exam, $attempt]))
            ->assertForbidden();

        // Dos veces sobre el mismo intento
        $service->authorizeReapplication($attempt, $this->teacher);
        $this->actingAs($this->teacher)
            ->post(route('exams.revision.reapply', [$this->exam, $attempt]))
            ->assertSessionHasErrors('reapplication');

        // Con el examen cerrado
        $other = User::factory()->student()->create();
        $this->exam->groups()->first()->students()->attach($other->id, ['joined_at' => now()]);
        $otherAttempt = $service->start($this->exam, $other);
        $service->submitFinal($otherAttempt);
        $this->exam->update(['ends_at' => now()->subMinute()]);

        $this->assertFalse($service->canAuthorizeReapplication($otherAttempt->fresh()->setRelation('exam', $this->exam->fresh())));
        $this->assertSame(1, StudentAttempt::where('student_id', $other->id)->count());
    }

    private function startAndSubmit(): StudentAttempt
    {
        $service = app(AttemptService::class);
        $attempt = $service->start($this->exam, $this->student);

        return $service->submitFinal($attempt)->fresh();
    }

    private function createTeacher(): User
    {
        $this->seed(SubscriptionPackageSeeder::class);
        $teacher = User::factory()->teacher()->create();

        TeacherSubscription::create([
            'teacher_id' => $teacher->id,
            'subscription_package_id' => SubscriptionPackage::where('tier', 'pro')->firstOrFail()->id,
            'payment_status' => 'paid',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        return $teacher;
    }

    private function attachQuestion(ExamSection $section): void
    {
        $item = MultipleChoiceItem::create([
            'options_payload' => [['index' => 0, 'text' => 'A'], ['index' => 1, 'text' => 'B']],
            'correct_option_index' => 1,
        ]);

        $question = Question::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Reactivo único',
            'stem' => '¿Cuál es correcta?',
            'question_type' => 'multiple_choice',
            'default_points' => 100,
            'default_time_seconds' => 60,
            'questionable_type' => MultipleChoiceItem::class,
            'questionable_id' => $item->id,
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 60,
            'points_weight' => 100,
            'sort_order' => 0,
        ]);
    }
}
