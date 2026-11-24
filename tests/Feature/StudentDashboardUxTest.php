<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentDashboardUxTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-24 10:00:00', config('app.timezone')));

        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Matemáticas 1A',
            'subject' => 'Matemáticas discretas',
            'access_folio' => 'MATE001',
        ]);
        $this->group->students()->attach($this->student->id, ['joined_at' => now()]);
    }

    public function test_dashboard_distinguishes_missing_enrollment_and_no_upcoming_exams(): void
    {
        $this->actingAs($this->student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No tienes exámenes activos ni programados')
            ->assertSee('Aún no hay intentos recientes')
            ->assertSee('Matemáticas 1A');

        $this->group->students()->detach($this->student->id);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aún no tienes grupos inscritos')
            ->assertSee('Solicita el folio')
            ->assertSee(route('student.groups.join.form'), false)
            ->assertDontSee('Todos tus exámenes asignados se encuentran al corriente.');
    }

    public function test_dashboard_and_catalog_distinguish_new_starts_schedules_and_resumable_attempts(): void
    {
        $active = $this->exam('Apertura disponible', ['starts_at' => now()]);
        $scheduled = $this->exam('Evaluación programada', ['starts_at' => now()->addMinutes(30)]);
        $closed = $this->exam('Evaluación cerrada', [
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
        ]);
        $resumable = $this->exam('Plazo individual vigente', [
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
            'free_duration_minutes' => 30,
        ]);
        $attempt = $this->attempt($resumable, [
            'started_at' => now()->subMinutes(5),
            'free_edit_until' => now()->addMinutes(25),
            'free_close_at' => now()->addMinutes(25),
        ]);
        $finished = $this->exam('Evaluación ya finalizada');
        $this->attempt($finished, ['status' => 'submitted', 'submitted_at' => now()->subMinutes(2)]);
        $reapplication = $this->exam('Evaluación con reaplicación');
        $this->attempt($reapplication, ['status' => 'expired', 'reapplication_authorized_at' => now()]);
        $draft = $this->exam('Borrador sin publicar', ['is_published' => false]);
        $unassigned = $this->exam('Evaluación de otro grupo');
        $unassigned->groups()->detach($this->group->id);

        $panel = $this->actingAs($this->student)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('activeExamCount', 2)
            ->assertViewHas('inProgressExamCount', 1)
            ->assertViewHas('scheduledExamCount', 1)
            ->assertSeeInOrder(['Exámenes activos y programados', $resumable->title, $scheduled->title, 'Historial de intentos recientes'])
            ->assertSee('24/11/2026')
            ->assertDontSee($closed->title)
            ->assertDontSee($draft->title)
            ->assertDontSee($unassigned->title);

        $rows = $panel->viewData('examRows')->keyBy(fn (array $row) => $row['exam']->getKey());
        $this->assertSame(route('attempts.show', $attempt), $rows[$resumable->id]['href']);
        $this->assertSame(route('student.exams.instructions', $active), $rows[$active->id]['href']);
        $this->assertSame('scheduled', $rows[$scheduled->id]['state']);
        $this->assertNull($rows[$scheduled->id]['href']);
        $this->assertSame('start', $rows[$reapplication->id]['action']);
        $this->assertFalse($rows->has($finished->id));

        $this->get(route('student.exams.index'))
            ->assertOk()
            ->assertSee($closed->title)
            ->assertSee($scheduled->title)
            ->assertSee(route('attempts.show', $attempt), false)
            ->assertDontSee(route('student.exams.instructions', $scheduled), false)
            ->assertDontSee(route('student.exams.instructions', $closed), false)
            ->assertDontSee($unassigned->title);

        foreach ([$scheduled, $closed] as $unavailableExam) {
            $this->get(route('student.exams.instructions', $unavailableExam))
                ->assertOk()
                ->assertDontSee('Confirmar e iniciar examen')
                ->assertDontSee(route('attempts.start', $unavailableExam), false);
        }

        $this->get(route('student.exams.instructions', $active))
            ->assertOk()
            ->assertSee('Confirmar e iniciar examen');
        $this->get(route('student.exams.instructions', $resumable))
            ->assertRedirect(route('attempts.show', $attempt));
        $this->get(route('student.exams.instructions', $unassigned))->assertForbidden();
        $this->assertDatabaseMissing('acs_student_attempts', ['exam_id' => $active->id]);
    }

    public function test_history_displays_only_authorized_grades_and_points_to_the_correct_destination(): void
    {
        $hidden = $this->attempt($this->exam('Resultado reservado', ['result_visibility_mode' => 'hidden']), [
            'status' => 'submitted', 'final_score' => 92.37, 'submitted_at' => now()->subMinute(),
        ]);
        $pending = $this->attempt($this->exam('Resultado en revisión'), [
            'status' => 'submitted', 'objective_score' => 76.61, 'pending_ai_count' => 1,
        ]);
        $published = $this->attempt($this->exam('Resultado publicado', ['grading_scale' => '0-100']), [
            'status' => 'submitted', 'final_score' => 42.25,
        ]);
        $cancelled = $this->attempt($this->exam('Intento cancelado'), [
            'status' => 'cancelled_by_incident', 'final_score' => 0, 'submitted_at' => now()->subMinute(),
        ]);
        $expired = $this->attempt($this->exam('Intento secuencial vencido'), [
            'started_at' => now()->subMinutes(10), 'question_sequence_payload' => [999999],
        ]);

        $panel = $this->actingAs($this->student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reservado')
            ->assertSee('En revisión')
            ->assertSee('Reprobado')
            ->assertSee('Sin envío formal')
            ->assertSee('Expirado')
            ->assertDontSee('92.37')
            ->assertDontSee('76.61');

        $rows = $panel->viewData('attemptRows')->keyBy(fn (array $row) => $row['attempt']->getKey());
        $this->assertSame('reserved', $rows[$hidden->id]['grade']['status']);
        $this->assertNull($rows[$pending->id]['grade']['score']);
        $this->assertSame(42.25, $rows[$published->id]['grade']['score']);
        $this->assertSame(100, $rows[$published->id]['grade']['scaleMax']);
        $this->assertFalse($rows[$published->id]['grade']['isPassed']);
        $this->assertSame('Inicio', $rows[$cancelled->id]['timestampLabel']);
        $this->assertSame('Entrega', $rows[$hidden->id]['timestampLabel']);
        $this->assertSame(route('attempts.result', $cancelled), $rows[$cancelled->id]['actionHref']);
        $this->assertSame(route('attempts.result', $expired), $rows[$expired->id]['actionHref']);
        $this->assertSame('in_progress', $expired->fresh()->status);

        $this->get(route('attempts.result', $hidden))
            ->assertOk()
            ->assertSee('Resultado reservado temporalmente')
            ->assertDontSee('92.37');
        $this->get(route('attempts.result', $cancelled))->assertOk()->assertSee('Sin envío formal');
        $this->get(route('attempts.result', $expired))->assertOk()->assertSee('Sin envío formal');
        $this->assertSame('expired', $expired->fresh()->status);
        $this->get(route('student.grades.index'))->assertOk()->assertDontSee('92.37');
        $this->get(route('student.grades.show', $this->group))->assertOk()->assertDontSee('92.37');
    }

    public function test_scheduled_preview_is_bounded_and_catalog_filters_apply_before_pagination(): void
    {
        for ($hour = 1; $hour <= 13; $hour++) {
            $this->exam('Álgebra programada '.sprintf('%02d', $hour), [
                'starts_at' => now()->addHours($hour),
                'ends_at' => now()->addHours($hour + 1),
            ]);
        }
        $active = $this->exam('Álgebra activa');

        $this->actingAs($this->student)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('scheduledExamCount', 13)
            ->assertSeeInOrder(['Álgebra programada 01', 'Álgebra programada 02', 'Álgebra programada 03'])
            ->assertDontSee('Álgebra programada 04');

        $url = route('student.exams.index', ['search' => 'algebra', 'status' => 'scheduled']);
        $this->get($url)
            ->assertOk()
            ->assertSee('Álgebra programada 01')
            ->assertSee('Álgebra programada 12')
            ->assertDontSee('Álgebra programada 13')
            ->assertDontSee($active->title);

        $this->get($url.'&page=2', ['X-Catalog-Fragment' => 'student-exam-results'])
            ->assertOk()
            ->assertSee('Álgebra programada 13')
            ->assertDontSee('Álgebra programada 01')
            ->assertDontSee('<!DOCTYPE html>', false);
    }

    public function test_free_attempt_closes_at_its_deadline_and_can_be_consulted_once_finalized(): void
    {
        $exam = $this->exam('Modalidad libre al cierre', ['free_duration_minutes' => 15]);
        $attempt = $this->attempt($exam, [
            'started_at' => now()->subMinutes(15)->subSeconds(30),
            'free_edit_until' => now()->subSeconds(30),
            'free_close_at' => now()->addSeconds(30),
        ]);

        $gracePanel = $this->actingAs($this->student)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('activeExamCount', 0)
            ->assertViewHas('inProgressExamCount', 1);
        $examRow = $gracePanel->viewData('examRows')->sole();
        $this->assertTrue($examRow['attemptHasGrace']);
        $this->assertSame(route('attempts.show', $attempt), $examRow['href']);

        $this->travel(30)->seconds();
        $panel = $this->get(route('dashboard'))->assertOk()->assertViewHas('inProgressExamCount', 0);
        $row = $panel->viewData('attemptRows')->sole();
        $this->assertSame('Cerrado', $row['statusLabel']);
        $this->assertSame(route('attempts.result', $attempt), $row['actionHref']);
        $this->assertSame('in_progress', $attempt->fresh()->status);

        $this->get($row['actionHref'])->assertOk()->assertSee('Resultado de Evaluación');
        $this->assertSame('submitted', $attempt->fresh()->status);
        $this->assertTrue($attempt->fresh()->submitted_at->equalTo(now()));

        $this->get($row['actionHref'])->assertOk();
        $this->assertTrue($attempt->fresh()->submitted_at->equalTo(now()));
    }

    private function exam(string $title, array $attributes = []): Exam
    {
        $exam = Exam::create($attributes + [
            'teacher_id' => $this->group->teacher_id,
            'title' => $title,
            'grading_scale' => '0-10',
            'result_visibility_mode' => 'final_score',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $exam->groups()->attach($this->group->id);

        return $exam;
    }

    private function attempt(Exam $exam, array $attributes): StudentAttempt
    {
        return StudentAttempt::create($attributes + [
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'group_id' => $this->group->id,
            'question_sequence_payload' => [],
            'started_at' => now()->subMinutes(5),
        ]);
    }
}
