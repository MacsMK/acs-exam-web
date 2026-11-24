<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     FrontendViewRenderingTest.php
 * Ubicación:   tests/Feature/FrontendViewRenderingTest.php
 * Descripción: Suite de certificación del Frontend Web y UI Kit institucional
 *              A-Code Solutions (§7 y §8 de MPW-V3): renderizado 100% íntegro de
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
use App\Models\StudentAttempt;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use App\Services\AttemptService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Database\Eloquent\Model;
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
        app(\App\Services\AuthService::class)->issueTwoFactorCode($user);

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
        $twoFactorResponse->assertDontSee($user->two_factor_code);
    }

    /**
     * El código 2FA solo se muestra en pantalla en modo demo (correo que no
     * sale de verdad). Con un correo real, llega únicamente por correo.
     */
    public function test_two_factor_demo_banner_is_hidden_when_mail_is_real(): void
    {
        $user = $this->createUser('teacher');
        $user->update(['email' => 'profesor.demo@example.test']);
        config(['mail.default' => 'log', 'auth.two_factor_demo' => true]);
        app(\App\Services\AuthService::class)->issueTwoFactorCode($user);
        $this->withSession(['pending_2fa_user_id' => $user->id])
            ->get(route('auth.verify-2fa.show', $user))
            ->assertOk()
            ->assertSee('Modo demo')
            ->assertSee($user->two_factor_code);

        config(['mail.default' => 'smtp']);
        $this->withSession(['pending_2fa_user_id' => $user->id])
            ->get(route('auth.verify-2fa.show', $user))
            ->assertOk()
            ->assertDontSee('Modo demo')
            ->assertDontSee($user->two_factor_code);
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
        $response->assertSee('¡Hola, '.$teacher->first_name.'!');
        $response->assertSee('Mis grupos');
        $response->assertSee('Banco de preguntas');
        $response->assertSee('Exámenes creados');
        $response->assertSee('Promedio general');
        $response->assertSee((string) 1); // 1 grupo, 1 reactivo, 1 examen
        $response->assertSee('groupScoreChart');
        $response->assertSee('Grupo Métricas A');
        $response->assertSee('METR001');
        $response->assertSee('Examen Métricas 1');
    }

    /**
     * b.1) El panel docente normaliza intentos enviados ('submitted') en escala 0-100
     *      a escala 0-10 para passedCount (>= 6.0), failedCount (< 6.0) y averageScore,
     *      ignorando intentos en progreso o de otros docentes.
     */
    public function test_teacher_dashboard_normalizes_submitted_attempts_across_grading_scales(): void
    {
        $teacher = $this->createUser('teacher');
        $otherTeacher = $this->createUser('teacher');
        $studentA = $this->createUser('student');
        $studentB = $this->createUser('student');

        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo Normalización 100',
            'access_folio' => 'NORM100',
            'is_closed' => false,
        ]);

        $examScale100 = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen Escala 0-100',
            'grading_scale' => '0-100',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 2,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);

        // Intento enviado con calificación 40 en escala 0-100 -> normaliza a 4.0 (< 6.0 reprobado)
        StudentAttempt::create([
            'exam_id' => $examScale100->id,
            'student_id' => $studentA->id,
            'group_id' => $group->id,
            'question_sequence_payload' => [],
            'current_question_index' => 0,
            'started_at' => now()->subMinutes(90),
            'submitted_at' => now()->subMinutes(65),
            'status' => 'submitted',
            'objective_score' => 40.00,
            'final_score' => 40.00,
        ]);

        // Intento en curso con 100 puntos -> debe ser ignorado por no estar 'submitted'
        StudentAttempt::create([
            'exam_id' => $examScale100->id,
            'student_id' => $studentB->id,
            'group_id' => $group->id,
            'question_sequence_payload' => [],
            'current_question_index' => 0,
            'started_at' => now()->subMinutes(30),
            'status' => 'in_progress',
            'objective_score' => 100.00,
            'final_score' => 100.00,
        ]);

        // Intento de otro docente -> debe ser aislado (multi-docente)
        $otherGroup = Group::create([
            'teacher_id' => $otherTeacher->id,
            'name' => 'Grupo Otro Docente',
            'access_folio' => 'OTRO100',
            'is_closed' => false,
        ]);
        $otherExam = $this->createDraftExam($otherTeacher, 'Examen Ajeno');
        StudentAttempt::create([
            'exam_id' => $otherExam->id,
            'student_id' => $studentA->id,
            'group_id' => $otherGroup->id,
            'question_sequence_payload' => [],
            'current_question_index' => 0,
            'started_at' => now()->subMinutes(90),
            'submitted_at' => now()->subMinutes(65),
            'status' => 'submitted',
            'objective_score' => 10.00,
            'final_score' => 10.00,
        ]);

        $response = $this->actingAs($teacher)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('passedCount', 0);
        $response->assertViewHas('failedCount', 1);
        $response->assertViewHas('averageScore', 4.0);
        $response->assertSee('4.0');
        $response->assertSee('Aprobados (0)');
        $response->assertSee('Reprobados (1)');

        $group->forceFill(['grading_scale' => '0-10', 'passing_score' => 4.0])->save();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('passedCount', 1)
            ->assertViewHas('failedCount', 0)
            ->assertViewHas('averageScore', 4.0);
    }

    public function test_teacher_dashboard_separates_pending_attempts_from_zero_grades_and_counts_current_submissions(): void
    {
        $this->freezeSecond();

        $teacher = $this->createUser('teacher');
        $student = $this->createUser('student');
        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo sin escala configurada',
            'access_folio' => 'PEND0001',
            'is_closed' => false,
        ]);

        $exam = $this->createDraftExam($teacher, 'Examen activo');
        $exam->update(['starts_at' => now()->subHour(), 'is_published' => true]);
        $draft = $this->createDraftExam($teacher, 'Examen en borrador');
        $this->createDraftExam($teacher, 'Examen programado')->update(['is_published' => true]);
        $this->createDraftExam($teacher, 'Examen cerrado')->update([
            'starts_at' => now()->subDay(),
            'ends_at' => now(),
            'is_published' => true,
        ]);

        $attempt = [
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'question_sequence_payload' => [],
            'started_at' => now()->subMinutes(30),
        ];

        StudentAttempt::create($attempt + [
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'submitted_at' => now()->subMinutes(25),
            'final_score' => 9.5,
        ]);
        $pendingAttempt = StudentAttempt::create($attempt + [
            'student_id' => $student->id,
            'attempt_number' => 2,
            'status' => 'submitted',
            'submitted_at' => now()->subMinutes(10),
            'objective_score' => 25,
            'pending_ai_count' => 1,
        ]);
        StudentAttempt::create($attempt + [
            'student_id' => $this->createUser('student')->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($teacher)->get(route('dashboard'));
        $response->assertOk()
            ->assertViewHas('examsCount', 4)
            ->assertViewHas('activeExamsCount', 1)
            ->assertViewHas('averageScore', static fn ($averageScore): bool => $averageScore === null)
            ->assertViewHas('passedCount', 0)
            ->assertViewHas('failedCount', 0)
            ->assertViewHas('gradedCount', 0)
            ->assertViewHas('pendingCount', 1)
            ->assertViewHas('outOfScaleCount', 0)
            ->assertSee('Aún no hay calificaciones definitivas')
            ->assertSee('Pendientes de revisión')
            ->assertSee(route('exams.assemble', $draft))
            ->assertSee(route('exams.monitor', $exam))
            ->assertSee(route('exams.revision.index', $exam))
            ->assertSee(route('exams.review', $exam))
            ->assertDontSee(route('exams.assemble', $exam));

        $recentExam = $response->viewData('recentExams')->find($exam->id);
        $this->assertInstanceOf(Exam::class, $recentExam);
        $this->assertEquals(1, $recentExam->submitted_attempts_count);
        $this->assertEquals(3, $recentExam->student_attempts_count);
        $this->assertFalse($recentExam->supportsStructuralEdit());

        $pendingAttempt->update(['final_score' => 0.0, 'pending_ai_count' => 0]);
        StudentAttempt::create($attempt + [
            'student_id' => $this->createUser('student')->id,
            'status' => 'submitted',
            'submitted_at' => now()->subMinutes(8),
            'final_score' => 25,
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('averageScore', static fn ($averageScore): bool => $averageScore === 0.0)
            ->assertViewHas('passedCount', 0)
            ->assertViewHas('failedCount', 1)
            ->assertViewHas('gradedCount', 1)
            ->assertViewHas('pendingCount', 0)
            ->assertViewHas('outOfScaleCount', 1)
            ->assertSee('Calificaciones fuera de escala');
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
        $exam->update(['is_published' => true, 'starts_at' => now()->subMinute()]);
        $exam->groups()->attach($group->id);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Panel del Estudiante');
        $response->assertSee('Exámenes activos y programados');
        $response->assertSee('Examen Parcial de Matemáticas');
        $response->assertSee('Abrir examen');
        $response->assertSee('Historial de intentos recientes');
        $response->assertSee('Matemáticas Discretas 1A');
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
        $groupsIndex->assertSee('Mis grupos');
        $groupsIndex->assertSee('Crear grupo');
        $groupsIndex->assertSee($group->name);

        // 2. groups.create
        $groupsCreate = $this->actingAs($teacher)->get(route('groups.create'));
        $groupsCreate->assertOk();
        $groupsCreate->assertSee('Crear grupo');
        $groupsCreate->assertSee('Nombre del grupo');

        // 3. groups.show
        $groupsShow = $this->actingAs($teacher)->get(route('groups.show', $group));
        $groupsShow->assertOk();
        $groupsShow->assertSee($group->name);
        $groupsShow->assertSee($group->access_folio);

        // 4. questions.index (con múltiples paráfrasis y prevención estricta de lazy loading)
        $question->paraphrases()->createMany([
            ['stem' => 'Variante anti-copia A sobre estructura LIFO en memoria.'],
            ['stem' => 'Variante anti-copia B sobre comportamiento último en entrar, primero en salir.'],
        ]);
        $this->assertTrue(Model::preventsLazyLoading());

        $questionsIndex = $this->actingAs($teacher)->get(route('questions.index'));
        $questionsIndex->assertOk();
        $questionsIndex->assertSee($question->title);
        $questionsIndex->assertSee('2 paráfrasis');
        $questionsIndex->assertDontSee('@js(', false);

        // 5. questions.create
        $questionsCreate = $this->actingAs($teacher)->get(route('questions.create'));
        $questionsCreate->assertOk();
        $questionsCreate->assertSee('Nueva pregunta');

        // 5.1 questions.batch.create
        $questionsBatchCreate = $this->actingAs($teacher)->get(route('questions.batch.create'));
        $questionsBatchCreate->assertOk();
        $questionsBatchCreate->assertSee('Captura rápida en lote');
        $questionsBatchCreate->assertSee('Materia compartida del lote');

        // 5.2 teacher.subscription.index con plan Pro mostrando 'Ilimitado'
        $this->seed(SubscriptionPackageSeeder::class);
        $proPackage = SubscriptionPackage::where('tier', 'pro')->firstOrFail();
        TeacherSubscription::create([
            'teacher_id' => $teacher->id,
            'subscription_package_id' => $proPackage->id,
            'payment_status' => 'paid',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $subscriptionIndex = $this->actingAs($teacher)->get(route('teacher.subscription.index'));
        $subscriptionIndex->assertOk();
        $subscriptionIndex->assertSee('Pro');
        $subscriptionIndex->assertSee('Ilimitado');

        // 6. exams.index
        $examsIndex = $this->actingAs($teacher)->get(route('exams.index'));
        $examsIndex->assertOk();
        $examsIndex->assertSee('Nuevo examen');
        $examsIndex->assertSee($exam->title);
        $examsIndex->assertDontSee('@js(', false);

        // 7. exams.create
        $examsCreate = $this->actingAs($teacher)->get(route('exams.create'));
        $examsCreate->assertOk();
        $examsCreate->assertSee('Crear examen');
        $examsCreate->assertSee('Título del examen');

        // 8. exams.assemble
        $examsAssemble = $this->actingAs($teacher)->get(route('exams.assemble', $exam));
        $examsAssemble->assertOk();
        $examsAssemble->assertSee($exam->title);
        $examsAssemble->assertSee($question->title);
        $examsAssemble->assertDontSee('@js(', false);
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
        $joinForm->assertSee('Unirme a un grupo');
        $joinForm->assertSee('Folio de acceso');
        $joinForm->assertSee('Unirme al grupo');

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
        $examsIndexWithExam->assertSee('Abrir examen');
        // RF-EXA-026: tiempo máximo calculado en lugar del valor fijo de 60 minutos
        $examsIndexWithExam->assertSee('Tiempo máximo:');
        $examsIndexWithExam->assertSee($exam->maxDurationMinutes() . ' minutos');
        // RF-EXA-028: «Abrir examen» lleva a las instrucciones antes de iniciar el intento
        $examsIndexWithExam->assertSee(route('student.exams.instructions', $exam), false);
        $examsIndexWithExam->assertDontSee(route('attempts.start', $exam), false);

        // 3. take-exam (intento en curso)
        $attemptService = app(AttemptService::class);
        $attempt = $attemptService->start($exam, $student);

        $takeExamResponse = $this->actingAs($student)->get(route('attempts.show', $attempt));
        $takeExamResponse->assertOk();
        $takeExamResponse->assertSee('Presentando examen');
        $takeExamResponse->assertSee($exam->title);
        $takeExamResponse->assertDontSee($question->title);
        $takeExamResponse->assertSee($question->stem);
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
