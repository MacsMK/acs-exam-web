<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentWebModuleComplianceTest.php
 * Ubicación:   tests/Feature/StudentWebModuleComplianceTest.php
 * Descripción: Suite de certificación normativa y funcional del módulo web del
 *              estudiante en acs-exam-web (MPW-V2 §7 y §8, MC-V2, P-01 a P-226).
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\StudentPeriodGrade;
use App\Models\EvaluationPeriod;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentWebModuleComplianceTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $teacher;
    private Group $group;
    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = $this->createUser('student');
        $this->teacher = $this->createUser('teacher');

        $this->group = Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Ingeniería de Software 5A',
            'subject' => 'Ingeniería de Software',
            'access_folio' => 'SOFT501',
            'is_closed' => false,
        ]);
        $this->group->students()->attach($this->student->id, ['joined_at' => now()]);

        $this->exam = Exam::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Examen Parcial de Métricas',
            'description' => 'Evaluación de métricas de software contrarreloj.',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 3,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->addHours(2),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $this->exam->groups()->attach($this->group->id);
    }

    /**
     * 1. Layout institucional: Sidebar, header sticky con avatar, menú de perfil y footer de dos secciones.
     */
    public function test_student_layout_renders_institutional_shell_sidebar_and_footer(): void
    {
        $response = $this->actingAs($this->student)->get(route('dashboard'));

        $response->assertOk();
        // Sidebar institucional
        $response->assertSee('Panel del Estudiante');
        $response->assertSee('Inicio');
        $response->assertSee('Mis Grupos');
        $response->assertSee('Mis Exámenes');
        $response->assertSee('Mis Calificaciones');
        $response->assertSee('Unirme a un grupo');

        // Header y Dropdown de perfil
        $response->assertSee($this->student->full_name);
        $response->assertSee('Estudiante');
        $response->assertSee('Aviso de privacidad');
        $response->assertSee('Cerrar sesión');

        // Footer normativo de dos secciones (MPW-V2 §8.10.7)
        $response->assertSee('© 2026 A-Code Solutions.');
        $response->assertSee('Plataforma Evaluadora de Exámenes');
        $response->assertSee('v1.0.0');
        $response->assertSee('Términos del Servicio');
        $response->assertSee('Centro de Ayuda');
        $response->assertSee('Soporte Técnico');
    }

    /**
     * 2. Consulta de grupos matriculados y filtrado de grupos cerrados (P-21, P-186).
     */
    public function test_student_can_view_enrolled_groups_and_closed_groups_are_excluded(): void
    {
        // Grupo cerrado
        $closedGroup = Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Materia Concluida Pasada',
            'subject' => 'Historia',
            'access_folio' => 'HIST001',
            'is_closed' => true,
        ]);
        $closedGroup->students()->attach($this->student->id, ['joined_at' => now()->subYear()]);

        $response = $this->actingAs($this->student)->get(route('student.groups.index'));

        $response->assertOk();
        $response->assertSee('Mis Grupos Matriculados');
        $response->assertSee('Ingeniería de Software 5A');
        $response->assertSee($this->teacher->full_name);
        $response->assertDontSee('Materia Concluida Pasada');
    }

    /**
     * 3. Prevención de incorporaciones duplicadas por código/folio (P-19 / RF-GRU-009).
     */
    public function test_student_cannot_join_group_with_duplicate_folio(): void
    {
        $response = $this->actingAs($this->student)->post(route('student.groups.join'), [
            'access_folio' => 'SOFT501',
        ]);

        $response->assertSessionHasErrors(['access_folio']);
        $this->assertSame(
            'Ya te encuentras inscrito en este grupo escolar.',
            session('errors')->first('access_folio')
        );
    }

    /**
     * 4. Abandono voluntario del grupo escolar cuando no hay exámenes en curso (P-23 / RF-GRU-012).
     */
    public function test_student_can_voluntarily_leave_group(): void
    {
        $this->assertTrue($this->group->students()->where('acs_users.id', $this->student->id)->exists());

        $response = $this->actingAs($this->student)->delete(route('student.groups.leave', $this->group));

        $response->assertRedirect(route('student.groups.index'));
        $response->assertSessionHas('status');

        $this->assertFalse(
            $this->group->students()->where('acs_users.id', $this->student->id)->exists()
        );
    }

    /**
     * 5. Restricción de salida durante un intento de examen en curso (RF-GRU-017 / P-108).
     */
    public function test_student_cannot_leave_group_while_having_attempt_in_progress(): void
    {
        // Crear un intento activo
        $attempt = StudentAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'group_id' => $this->group->id,
            'question_sequence_payload' => [1],
            'current_question_index' => 0,
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->student)->delete(route('student.groups.leave', $this->group));

        $response->assertSessionHasErrors(['group']);
        $this->assertTrue(
            $this->group->students()->where('acs_users.id', $this->student->id)->exists()
        );
    }

    /**
     * 6. Pantalla previa de instrucciones y confirmación antes del inicio (P-83, P-84 / RF-EXA-028, RF-EXA-029).
     */
    public function test_student_pre_exam_instructions_screen_renders_rules_and_confirmation(): void
    {
        $response = $this->actingAs($this->student)->get(route('student.exams.instructions', $this->exam));

        $response->assertOk();
        $response->assertSee('Instrucciones del Examen');
        $response->assertSee($this->exam->title);
        $response->assertSee('Condiciones obligatorias para presentar esta evaluación');
        $response->assertSee('El tiempo corre en el servidor');
        $response->assertSee('Confirmar e iniciar examen');
    }

    /**
     * 7. Blindaje de IA: Restricción de estimaciones preliminares antes de recoteje docente (P-136 / RF-EVA-009 / RW-27).
     */
    public function test_student_does_not_see_tentative_ai_score_before_teacher_recoteje(): void
    {
        $section = ExamSection::create([
            'exam_id' => $this->exam->id,
            'title' => 'Apartado Abierto',
            'question_type' => 'open',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);

        $item = OpenItem::create([
            'reference_answer' => 'La cohesión mide el grado de afinidad de los métodos.',
            'evaluation_rubric' => 'Explicación clara de afinidad funcional.',
        ]);

        $question = Question::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Pregunta de Calidad',
            'stem' => 'Explique la cohesión modular.',
            'question_type' => 'open',
            'default_points' => 10,
            'default_time_seconds' => 180,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $item->id,
        ]);

        $examQuestion = ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 180,
            'points_weight' => 10,
            'sort_order' => 0,
        ]);

        $attempt = StudentAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'group_id' => $this->group->id,
            'question_sequence_payload' => [$examQuestion->id],
            'current_question_index' => 1,
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now()->subMinutes(2),
            'status' => 'submitted',
            'objective_score' => 0.0,
            'ai_estimated_score' => 8.5,
            'final_score' => null, // Docente aún NO recoteja
            'pending_ai_count' => 1,
        ]);

        StudentAnswer::create([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $examQuestion->id,
            'answer_payload' => 'Es la medida en que los elementos pertenecen juntos.',
            'is_correct' => null,
            'points_awarded' => 8.5,
            'ai_evaluated_at' => now(),
            'ai_meets_goal' => true,
            'ai_feedback' => 'Cumple parcialmente con la definición formal.',
            'teacher_recoteje_score' => null, // Pendiente de recoteje
        ]);

        $response = $this->actingAs($this->student)->get(route('attempts.result', $attempt));

        $response->assertOk();
        // Debe mostrar insignia de pendiente
        $response->assertSee('Pendiente de evaluación docente');
        // NO debe revelar la retroalimentación de IA preliminar
        $response->assertDontSee('Cumple parcialmente con la definición formal.');
        $response->assertDontSee('IA: Cumple objetivo');
    }

    /**
     * 8. Tablero de calificaciones del estudiante por periodo (P-174, P-175, P-180 / RF-REP-007).
     */
    public function test_student_can_view_grades_hub_and_period_evaluations(): void
    {
        $period = EvaluationPeriod::create([
            'group_id' => $this->group->id,
            'name' => '1er Parcial',
            'formula' => '50% Examen + 50% Tareas',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        StudentPeriodGrade::create([
            'evaluation_period_id' => $period->id,
            'student_id' => $this->student->id,
            'exams_component' => 9.0,
            'homework_component' => 8.0,
            'project_component' => 0.0,
            'other_component' => 0.0,
            'final_grade' => 8.5,
        ]);

        $response = $this->actingAs($this->student)->get(route('student.grades.index'));

        $response->assertOk();
        $response->assertSee('Mis Calificaciones e Historial Académico');
        $response->assertSee('Ingeniería de Software 5A');
        $response->assertSee('1er Parcial');
        $response->assertSee('8.5');
    }

    /**
     * 9. Aislamiento multi-docente y multi-alumno IDOR (P-214 / RNF-SEG-005).
     */
    public function test_student_cannot_view_another_students_result(): void
    {
        $classmate = $this->createUser('student');

        $attempt = StudentAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $classmate->id,
            'group_id' => $this->group->id,
            'question_sequence_payload' => [1],
            'current_question_index' => 0,
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now(),
            'status' => 'submitted',
            'objective_score' => 10.0,
            'final_score' => 10.0,
        ]);

        $response = $this->actingAs($this->student)->get(route('attempts.result', $attempt));
        $response->assertForbidden();
    }

    /**
     * 10. Consulta de soluciones de referencia y respuestas post-examen (P-171, P-172, P-173 / RF-REP-005).
     */
    public function test_student_can_view_model_solutions_review_when_published_and_closed(): void
    {
        // Examen cerrado
        $this->exam->update([
            'starts_at' => now()->subHours(5),
            'ends_at' => now()->subHours(2),
        ]);

        $attempt = StudentAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'group_id' => $this->group->id,
            'question_sequence_payload' => [],
            'current_question_index' => 0,
            'started_at' => now()->subHours(4),
            'submitted_at' => now()->subHours(3),
            'status' => 'submitted',
            'objective_score' => 10.0,
            'final_score' => 10.0, // Calificación publicada
        ]);

        $response = $this->actingAs($this->student)->get(route('attempts.review', $attempt));

        $response->assertOk();
        $response->assertSee('Revisión de Soluciones y Respuestas');
        $response->assertSee($this->exam->title);
    }

    /**
     * 11. Restricción de consulta de soluciones mientras el examen siga abierto.
     */
    public function test_student_cannot_view_review_while_exam_is_still_open(): void
    {
        // Examen aún abierto (ends_at en el futuro)
        $this->exam->update([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
        ]);

        $attempt = StudentAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'group_id' => $this->group->id,
            'question_sequence_payload' => [],
            'current_question_index' => 0,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(10),
            'status' => 'submitted',
            'objective_score' => 9.0,
            'final_score' => 9.0,
        ]);

        $response = $this->actingAs($this->student)->get(route('attempts.review', $attempt));
        $response->assertForbidden();
    }

    private function createUser(string $role): User
    {
        return User::create([
            'role' => $role,
            'first_name' => $role === 'teacher' ? 'Prof. Fernando' : 'Estudiante Andrea',
            'last_name' => $role === 'teacher' ? 'Castillo' : 'Moreno',
            'email' => uniqid("{$role}-", true).'@acode.solutions',
            'password' => 'Demo1234',
            'is_active' => true,
        ]);
    }
}
