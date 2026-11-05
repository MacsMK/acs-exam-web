<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     FrontendViewRenderingTest.php
 * Ubicación:   tests/Feature/FrontendViewRenderingTest.php
 * Descripción: Suite de certificación del Frontend Web y UI Kit institucional
 *              A-Code Solutions (§7 y §8 de MPW-V2): renderizado 100% íntegro de
 *              rutas públicas de autenticación (login, registros, 2FA), dashboards
 *              operativos (docente y alumno), vistas de gestión académica docente
 *              y runner/dictamen de evaluación para alumnos.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MultipleChoiceItem;
use App\Models\Question;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendViewRenderingTest extends TestCase
{
    use RefreshDatabase;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!defined('sidebarOpen')) {
            define('sidebarOpen', false);
        }
        if (!defined('remaining')) {
            define('remaining', 60);
        }
    }

    /**
     * a) Rutas públicas de autenticación (login, register-teacher, register-student, verify-2fa)
     *    retornan 200 y contienen componentes clave (x-logo, x-button, SweetAlert2 / alerts.js).
     */
    public function test_public_authentication_routes_render_successfully_with_ui_kit_components(): void
    {
        // 1. Login
        $loginResponse = $this->get(route('auth.login.show'));
        $loginResponse->assertOk();
        $loginResponse->assertSee('Iniciar Sesión');
        $loginResponse->assertSee('A-Code Solutions');
        $loginResponse->assertSee('Continuar');
        $loginResponse->assertSee('sweetalert2');
        $loginResponse->assertSee('alerts.js');
        $loginResponse->assertSee('Correo electrónico institucional');
        $loginResponse->assertSee('Contraseña');

        // 2. Registro Docente
        $teacherRegResponse = $this->get(route('auth.register.teacher.show'));
        $teacherRegResponse->assertOk();
        $teacherRegResponse->assertSee('Registro de Docente');
        $teacherRegResponse->assertSee('A-Code Solutions');
        $teacherRegResponse->assertSee('Continuar');
        $teacherRegResponse->assertSee('sweetalert2');
        $teacherRegResponse->assertSee('alerts.js');
        $teacherRegResponse->assertSee('Aviso de Privacidad y Términos de Servicio');

        // 3. Registro Alumno
        $studentRegResponse = $this->get(route('auth.register.student.show'));
        $studentRegResponse->assertOk();
        $studentRegResponse->assertSee('Registro de Alumno');
        $studentRegResponse->assertSee('A-Code Solutions');
        $studentRegResponse->assertSee('Continuar');
        $studentRegResponse->assertSee('sweetalert2');
        $studentRegResponse->assertSee('alerts.js');
        $studentRegResponse->assertSee('Correo electrónico institucional o personal');

        // 4. Verificación 2FA
        $user = $this->createUser('teacher');
        $user->forceFill(['two_factor_code' => '654321'])->save();

        // La pantalla 2FA solo se abre en el navegador que acaba de pasar la
        // contraseña (sesión `pending_2fa_user_id`); sin ella responde 403.
        $this->get(route('auth.verify-2fa.show', $user))->assertForbidden();

        $twoFactorResponse = $this->withSession(['pending_2fa_user_id' => $user->id])
            ->get(route('auth.verify-2fa.show', $user));
        $twoFactorResponse->assertOk();
        $twoFactorResponse->assertSee('Verificación en Dos Pasos');
        $twoFactorResponse->assertSee('A-Code Solutions');
        $twoFactorResponse->assertSee('Continuar');
        $twoFactorResponse->assertSee('sweetalert2');
        $twoFactorResponse->assertSee('alerts.js');
        $twoFactorResponse->assertSee($user->email);
        $twoFactorResponse->assertSee('654321');
    }

    /**
     * b) Panel de control docente (dashboard) autenticado como teacher
     *    retorna 200, renderiza métricas y componente Chart.js / canvas.
     */
    public function test_teacher_dashboard_renders_metrics_and_chart_canvas(): void
    {
        $teacher = $this->createUser('teacher');

        // Creamos un grupo, reactivo y examen para poblar métricas
        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo Métricas A',
            'access_folio' => 'METR001',
            'is_closed' => false,
        ]);

        $this->createQuestionForTeacher($teacher, 'Reactivo Métricas 1');
        $this->createDraftExam($teacher, 'Examen Métricas 1');

        $response = $this->actingAs($teacher)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Panel del Docente');
        $response->assertSee('Grupos Activos');
        $response->assertSee('Banco de Reactivos');
        $response->assertSee('Exámenes Totales');
        $response->assertSee('Promedio General');

        // Certificación del componente interactivo Chart.js y canvas analítico (§7.9, Tabla 23)
        $response->assertSee('Distribución Institucional de Aprobados y Calificaciones');
        $response->assertSee('<canvas x-ref="canvas"></canvas>', false);
        $response->assertSee('chart.umd.min.js');
        $response->assertSee('groupScoreChart');
        $response->assertSee($group->name);
    }

    /**
     * c) Panel de control alumno (dashboard) autenticado como student
     *    retorna 200 y muestra sus secciones.
     */
    public function test_student_dashboard_renders_student_sections(): void
    {
        $student = $this->createUser('student');
        $teacher = $this->createUser('teacher');

        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Matemáticas Discretas 1A',
            'access_folio' => 'DISC101',
            'is_closed' => false,
        ]);
        $group->students()->attach($student->id, ['joined_at' => now()]);

        // Examen publicado asignado al grupo para probar la tabla con examen pendiente
        $exam = $this->createDraftExam($teacher, 'Examen Parcial de Matemáticas');
        $exam->update(['is_published' => true]);
        $exam->groups()->attach($group->id);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Panel del Estudiante');
        $response->assertSee('Grupos Inscritos');
        $response->assertSee('Exámenes Pendientes');
        $response->assertSee('Evaluaciones Presentadas');
        $response->assertSee('Exámenes Disponibles');
        $response->assertSee('Examen Parcial de Matemáticas');
        $response->assertSee('Abrir examen');
        $response->assertSee('Historial de Intentos Recientes');
        $response->assertSee('Unirme a un grupo');
    }

    /**
     * d) Vistas docentes (groups.index, groups.create, groups.show, questions.index,
     *    questions.create, exams.index, exams.create, exams.assemble) retornan 200
     *    para un docente autenticado dueño de los recursos.
     */
    public function test_teacher_management_views_render_for_authorized_teacher_owner(): void
    {
        $teacher = $this->createUser('teacher');

        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Estructura de Datos 3B',
            'access_folio' => 'ESTR003',
            'is_closed' => false,
        ]);

        $question = $this->createQuestionForTeacher($teacher, '¿Qué es una pila LIFO?');
        $exam = $this->createDraftExam($teacher, 'Examen Parcial de Estructuras');

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Apartado Teórico',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 90,
            'points_weight' => 10,
            'sort_order' => 0,
        ]);

        // 1. groups.index
        $groupsIndex = $this->actingAs($teacher)->get(route('groups.index'));
        $groupsIndex->assertOk();
        $groupsIndex->assertSee('Grupos Escolares');
        $groupsIndex->assertSee('Crear grupo');
        $groupsIndex->assertSee($group->name);

        // 2. groups.create
        $groupsCreate = $this->actingAs($teacher)->get(route('groups.create'));
        $groupsCreate->assertOk();
        $groupsCreate->assertSee('Crear Grupo Escolar');
        $groupsCreate->assertSee('Nombre del grupo escolar');

        // 3. groups.show
        $groupsShow = $this->actingAs($teacher)->get(route('groups.show', $group));
        $groupsShow->assertOk();
        $groupsShow->assertSee($group->name);
        $groupsShow->assertSee($group->access_folio);
        $groupsShow->assertSee('Folio Oficial de Acceso');
        $groupsShow->assertSee('group-qr-modal');

        // 4. questions.index
        $questionsIndex = $this->actingAs($teacher)->get(route('questions.index'));
        $questionsIndex->assertOk();
        $questionsIndex->assertSee('Banco de Reactivos');
        $questionsIndex->assertSee('Crear reactivo');
        $questionsIndex->assertSee($question->title);

        // 5. questions.create
        $questionsCreate = $this->actingAs($teacher)->get(route('questions.create'));
        $questionsCreate->assertOk();
        $questionsCreate->assertSee('Crear Reactivo');
        $questionsCreate->assertSee('Título identificador del reactivo');
        $questionsCreate->assertSee('Continuar al paso 2');
        $questionsCreate->assertSee('Volver al listado');
        $questionsCreate->assertSee('Modo Auditoría / Debugging');

        // 6. exams.index
        $examsIndex = $this->actingAs($teacher)->get(route('exams.index'));
        $examsIndex->assertOk();
        $examsIndex->assertSee('Catálogo de Exámenes');
        $examsIndex->assertSee('Crear examen');
        $examsIndex->assertSee($exam->title);

        // 7. exams.create
        $examsCreate = $this->actingAs($teacher)->get(route('exams.create'));
        $examsCreate->assertOk();
        $examsCreate->assertSee('Crear Examen');
        $examsCreate->assertSee('Título del examen');

        // 8. exams.assemble
        $examsAssemble = $this->actingAs($teacher)->get(route('exams.assemble', $exam));
        $examsAssemble->assertOk();
        $examsAssemble->assertSee($exam->title);
        $examsAssemble->assertSee('Apartados y Reactivos Asignados');
        $examsAssemble->assertSee('Apartado Teórico');
        $examsAssemble->assertSee($question->title);
    }

    /**
     * e) Vistas de alumno (student.exams.index, student.groups.join.form, take-exam,
     *    result-summary) retornan 200 para un alumno con intento en curso o finalizado.
     */
    public function test_student_exam_views_render_for_student_with_active_and_finished_attempts(): void
    {
        $teacher = $this->createUser('teacher');
        $student = $this->createUser('student');

        // 1. student.groups.join.form
        $joinForm = $this->actingAs($student)->get(route('student.groups.join.form'));
        $joinForm->assertOk();
        $joinForm->assertSee('Unirme a un Grupo Escolar');
        $joinForm->assertSee('Folio alfanumérico de acceso');
        $joinForm->assertSee('Enviar solicitud');

        // 2. student.exams.index
        $examsIndex = $this->actingAs($student)->get(route('student.exams.index'));
        $examsIndex->assertOk();
        $examsIndex->assertSee('Mis Exámenes Asignados');

        // Preparamos examen publicado y matriculación del alumno
        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Programación Web 5C',
            'access_folio' => 'WEB5C01',
            'is_closed' => false,
        ]);
        $group->students()->attach($student->id, ['joined_at' => now()]);

        $exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen de Certificación Frontend',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 2,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $exam->groups()->attach($group->id);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Sección A',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);

        $question = $this->createQuestionForTeacher($teacher, '¿Cuál es la función de Blade en Laravel?');

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 120,
            'points_weight' => 10,
            'sort_order' => 0,
        ]);

        // Verificamos que el examen aparezca en student.exams.index
        $examsIndexWithExam = $this->actingAs($student)->get(route('student.exams.index'));
        $examsIndexWithExam->assertOk();
        $examsIndexWithExam->assertSee($exam->title);
        $examsIndexWithExam->assertSee('Iniciar examen');

        // 3. take-exam (intento en curso)
        $attemptService = app(AttemptService::class);
        $attempt = $attemptService->start($exam, $student);

        $takeExamResponse = $this->actingAs($student)->get(route('attempts.show', $attempt));
        $takeExamResponse->assertOk();
        $takeExamResponse->assertSee('Presentando examen');
        $takeExamResponse->assertSee($exam->title);
        $takeExamResponse->assertSee($question->title);
        $takeExamResponse->assertSee('Reactivo 1 de 1');
        $takeExamResponse->assertSee('answerForm');
        $takeExamResponse->assertSee('role="timer"', false);
        $takeExamResponse->assertSee('formattedTime()');

        // 4. result-summary (intento finalizado)
        $finishedAttempt = $attemptService->submitFinal($attempt);

        $resultResponse = $this->actingAs($student)->get(route('attempts.result', $finishedAttempt));
        $resultResponse->assertOk();
        $resultResponse->assertSee('Resultado de Evaluación');
        $resultResponse->assertSee($exam->title);
        $resultResponse->assertSee('Escala de evaluación');
        $resultResponse->assertSee('Volver al listado');
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

    private function createDraftExam(User $teacher, string $title): Exam
    {
        return Exam::create([
            'teacher_id' => $teacher->id,
            'title' => $title,
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 2,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => false,
        ]);
    }

    private function createQuestionForTeacher(User $teacher, string $title): Question
    {
        $item = MultipleChoiceItem::create([
            'options_payload' => [
                ['index' => 0, 'text' => 'Motor de plantillas ligero'],
                ['index' => 1, 'text' => 'Servidor web integrado'],
            ],
            'correct_option_index' => 0,
        ]);

        return Question::create([
            'teacher_id' => $teacher->id,
            'title' => $title,
            'stem' => 'Selecciona la opción correcta conforme a la documentación oficial.',
            'question_type' => 'multiple_choice',
            'default_points' => 10,
            'default_time_seconds' => 120,
            'questionable_type' => MultipleChoiceItem::class,
            'questionable_id' => $item->id,
        ]);
    }
}
