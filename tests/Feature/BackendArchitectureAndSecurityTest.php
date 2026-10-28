<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     BackendArchitectureAndSecurityTest.php
 * Ubicación:   tests/Feature/BackendArchitectureAndSecurityTest.php
 * Descripción: Suite de certificación de seguridad OWASP Top 10 (aislamiento
 *              multi-docente IDOR, protección de máquina de estados de intento)
 *              y reglas normativas críticas de negocio (RF-EVA-002, RF-EVA-008,
 *              RF-GRU-018, RF-PRI-001 y paridad de contrato móvil en
 *              POST /api/v1/groups/verify).
 * ============================================================================
 */

namespace Tests\Feature;

use App\Contracts\AiEvaluatorContract;
use App\Models\CodeItem;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\MatchingItem;
use App\Models\MultipleChoiceItem;
use App\Models\OpenItem;
use App\Models\PrivacyNotice;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Services\AttemptService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendArchitectureAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_teacher_isolation_blocks_foreign_group_assignment_and_foreign_question_reshuffle(): void
    {
        $teacherA = $this->createUser('teacher');
        $teacherB = $this->createUser('teacher');

        $groupB = Group::create([
            'teacher_id' => $teacherB->id,
            'name' => 'Grupo Docente B',
            'access_folio' => 'GRPB001',
            'is_closed' => false,
        ]);

        // 1. Docente A intenta crear un examen asignándolo al grupo del Docente B -> 422
        $storeResponse = $this->actingAs($teacherA)->postJson(route('exams.store'), [
            'title' => 'Examen con grupo ajeno',
            'description' => 'Intento de IDOR sobre grupo de otro docente',
            'grading_scale' => '0-10',
            'randomize_question_order' => true,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 2,
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->addHours(2)->toDateTimeString(),
            'group_ids' => [$groupB->id],
        ]);

        $storeResponse->assertStatus(422);
        $storeResponse->assertJsonValidationErrors(['group_ids.0']);

        // 2. Docente A intenta re-sortear un reactivo perteneciente a un examen del Docente B -> 404 / 403
        $examA = $this->createDraftExam($teacherA);
        $examB = $this->createDraftExam($teacherB);

        $sectionB = ExamSection::create([
            'exam_id' => $examB->id,
            'title' => 'Apartado B',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);

        [, $examQuestionB] = $this->attachMultipleChoiceQuestion($examB, $sectionB, $teacherB, 0);

        $crossExamReshuffle = $this->actingAs($teacherA)->post(
            route('exams.questions.reshuffle', ['exam' => $examA->id, 'examQuestion' => $examQuestionB->id]),
        );
        $crossExamReshuffle->assertStatus(404);

        $crossTeacherReshuffle = $this->actingAs($teacherA)->post(
            route('exams.questions.reshuffle', ['exam' => $examB->id, 'examQuestion' => $examQuestionB->id]),
        );
        $crossTeacherReshuffle->assertStatus(403);
    }

    public function test_matching_question_awards_proportional_score(): void
    {
        $teacher = $this->createUser('teacher');
        $student = $this->createUser('student');
        $studentToken = $this->tokenFor($student);

        [$exam, $section] = $this->createPublishedExamForStudent($teacher, $student);

        $matchingItem = MatchingItem::create([
            'left_column_payload' => [
                ['id' => 'L1', 'text' => 'Concepto 1'],
                ['id' => 'L2', 'text' => 'Concepto 2'],
            ],
            'right_column_payload' => [
                ['id' => 'R1', 'text' => 'Definición 1'],
                ['id' => 'R2', 'text' => 'Definición 2'],
            ],
            'pairs_payload' => [
                ['left_id' => 'L1', 'right_id' => 'R1'],
                ['left_id' => 'L2', 'right_id' => 'R2'],
            ],
        ]);

        $matchingQuestion = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'Relación proporcional',
            'stem' => 'Relaciona cada concepto con su definición',
            'question_type' => 'matching',
            'default_points' => 10,
            'default_time_seconds' => 90,
            'questionable_type' => MatchingItem::class,
            'questionable_id' => $matchingItem->id,
        ]);

        $examQuestion = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $matchingQuestion->id,
            'time_limit_seconds' => 90,
            'points_weight' => 10,
            'sort_order' => 0,
        ]);

        $this->withFreshToken($studentToken)
            ->postJson('/api/v1/exams/take', ['exam_id' => $exam->id])
            ->assertOk();

        // Acierta 1 de 2 pares (L1 => R1 correcto, L2 => R1 incorrecto) sobre 10 puntos -> 5.0 puntos
        $answerResponse = $this->withFreshToken($studentToken)
            ->postJson("/api/v1/exams/{$exam->id}/answers", [
                'question_id' => (string) $matchingQuestion->id,
                'pairs' => [
                    'L1' => 'R1',
                    'L2' => 'R1',
                ],
            ]);

        $answerResponse->assertOk();

        $storedAnswer = StudentAnswer::where('exam_question_id', $examQuestion->id)->firstOrFail();
        $this->assertFalse($storedAnswer->is_correct);
        $this->assertEquals(5.0, (float) $storedAnswer->points_awarded);

        $attempt = StudentAttempt::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertEquals(5.0, (float) $attempt->objective_score);
        $this->assertEquals(5.0, (float) $attempt->final_score);
    }

    public function test_empty_open_and_code_answers_bypass_ai_evaluator_and_award_zero_points(): void
    {
        $aiEvaluationCalls = 0;

        $this->app->instance(AiEvaluatorContract::class, new class($aiEvaluationCalls) implements AiEvaluatorContract {
            public function __construct(private int &$calls)
            {
            }

            public function evaluate(string $studentAnswer, string $referenceAnswer): array
            {
                $this->calls++;

                return [
                    'similarity_score' => 100.0,
                    'meets_goal' => true,
                    'feedback' => 'No debería invocarse en respuestas vacías.',
                ];
            }
        });
        $this->app->forgetInstance(AttemptService::class);

        $teacher = $this->createUser('teacher');
        $student = $this->createUser('student');
        $studentToken = $this->tokenFor($student);

        [$exam, $section] = $this->createPublishedExamForStudent($teacher, $student);

        [$openQuestion, $openExamQuestion] = $this->attachOpenQuestion($exam, $section, $teacher, 0);
        [$codeQuestion, $codeExamQuestion] = $this->attachCodeQuestion($exam, $section, $teacher, 1);

        $this->withFreshToken($studentToken)
            ->postJson('/api/v1/exams/take', ['exam_id' => $exam->id])
            ->assertOk();

        // Respuesta abierta vacía ("")
        $this->withFreshToken($studentToken)
            ->postJson("/api/v1/exams/{$exam->id}/answers", [
                'question_id' => (string) $openQuestion->id,
                'text' => '',
            ])
            ->assertOk();

        // Respuesta de código nula (null)
        $this->withFreshToken($studentToken)
            ->postJson("/api/v1/exams/{$exam->id}/answers", [
                'question_id' => (string) $codeQuestion->id,
                'code' => null,
            ])
            ->assertOk();

        $this->assertSame(0, $aiEvaluationCalls);

        $openAnswer = StudentAnswer::where('exam_question_id', $openExamQuestion->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $openAnswer->points_awarded);
        $this->assertEquals(0.0, (float) $openAnswer->ai_similarity_score);
        $this->assertFalse((bool) $openAnswer->ai_meets_goal);

        $codeAnswer = StudentAnswer::where('exam_question_id', $codeExamQuestion->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $codeAnswer->points_awarded);
        $this->assertEquals(0.0, (float) $codeAnswer->ai_similarity_score);
        $this->assertFalse((bool) $codeAnswer->ai_meets_goal);
    }

    public function test_non_active_attempt_rejects_new_answers_with_409_and_cannot_be_resubmitted(): void
    {
        $teacher = $this->createUser('teacher');
        $student = $this->createUser('student');
        $studentToken = $this->tokenFor($student);

        [$exam, $section] = $this->createPublishedExamForStudent($teacher, $student, maxAllowedIncidents: 0);
        [$mcQuestion1] = $this->attachMultipleChoiceQuestion($exam, $section, $teacher, 0);
        [$mcQuestion2] = $this->attachMultipleChoiceQuestion($exam, $section, $teacher, 1);

        $this->withFreshToken($studentToken)
            ->postJson('/api/v1/exams/take', ['exam_id' => $exam->id])
            ->assertOk();

        // Reportar incidente cuando max_allowed_incidents = 0 cancela el intento
        $this->withFreshToken($studentToken)
            ->postJson("/api/v1/exams/{$exam->id}/incidents", [
                'incident_type' => 'focus_lost',
            ])
            ->assertOk();

        $attempt = StudentAttempt::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('cancelled_by_incident', $attempt->status);

        // Enviar respuesta a un intento cancelado por incidente retorna 409 Conflict
        $cancelledAnswerResponse = $this->withFreshToken($studentToken)
            ->postJson("/api/v1/exams/{$exam->id}/answers", [
                'question_id' => (string) $mcQuestion1->id,
                'selected_option_ids' => ['1'],
            ]);
        $cancelledAnswerResponse->assertStatus(409);

        // Tampoco puede ser re-marcado como submitted mediante AttemptService::submitFinal()
        try {
            app(AttemptService::class)->submitFinal($attempt->fresh());
            $this->fail('Se esperaba DomainException al intentar finalizar un intento cancelado.');
        } catch (DomainException) {
            $this->assertSame('cancelled_by_incident', $attempt->fresh()->status);
        }

        // Si el intento está en estado submitted, también rechaza respuestas nuevas con 409 Conflict
        $attempt->update(['status' => 'submitted', 'submitted_at' => now()]);

        $submittedAnswerResponse = $this->withFreshToken($studentToken)
            ->postJson("/api/v1/exams/{$exam->id}/answers", [
                'question_id' => (string) $mcQuestion2->id,
                'selected_option_ids' => ['1'],
            ]);
        $submittedAnswerResponse->assertStatus(409);
    }

    public function test_group_close_is_blocked_when_an_exam_attempt_is_in_progress(): void
    {
        $teacher = $this->createUser('teacher');
        $student = $this->createUser('student');

        [$exam, $section, $group] = $this->createPublishedExamForStudent($teacher, $student);
        $this->attachMultipleChoiceQuestion($exam, $section, $teacher, 0);

        StudentAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'group_id' => $group->id,
            'question_sequence_payload' => [],
            'current_question_index' => 0,
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($teacher)->post(route('groups.close', $group));

        $response->assertRedirect(route('groups.show', $group));
        $response->assertSessionHasErrors('group');
        $this->assertFalse($group->fresh()->is_closed);
    }

    public function test_mobile_group_verify_endpoint_accepts_both_qr_payload_and_folio(): void
    {
        $teacher = $this->createUser('teacher');
        $student = $this->createUser('student');
        $studentToken = $this->tokenFor($student);

        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo Verificación Móvil',
            'access_folio' => 'VERIF01',
            'is_closed' => false,
        ]);
        $group->students()->attach($student->id, ['joined_at' => now()]);

        $qrResponse = $this->withFreshToken($studentToken)->postJson('/api/v1/groups/verify', [
            'qr_payload' => 'verif01',
        ]);
        $qrResponse->assertOk();
        $qrResponse->assertJsonPath('status', 'success');

        $folioResponse = $this->withFreshToken($studentToken)->postJson('/api/v1/groups/verify', [
            'folio' => 'VERIF01',
        ]);
        $folioResponse->assertOk();
        $folioResponse->assertJsonPath('status', 'success');
    }

    public function test_user_registration_records_privacy_acceptance_for_current_notice(): void
    {
        $notice = PrivacyNotice::create([
            'version' => 'v2.0',
            'content' => 'Aviso de privacidad institucional vigente.',
            'published_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/register/student', [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'email' => 'ana.lopez@example.test',
            'phone' => '5512345678',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'accepts_privacy_notice' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', 'success');

        $userId = $response->json('data.user_id');
        $this->assertNotNull($userId);

        $this->assertDatabaseHas('acs_privacy_acceptances', [
            'user_id' => $userId,
            'privacy_notice_id' => $notice->id,
        ]);
    }

    private function createUser(string $role): User
    {
        return User::create([
            'role' => $role,
            'first_name' => $role === 'teacher' ? 'Carlos' : 'Sofía',
            'last_name' => $role === 'teacher' ? 'Ramírez' : 'Hernández',
            'email' => uniqid("{$role}-", true).'@example.test',
            'password' => 'Demo1234',
            'is_active' => true,
        ]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function withFreshToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function createDraftExam(User $teacher): Exam
    {
        return Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen Borrador',
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

    /**
     * @return array{0: Exam, 1: ExamSection, 2: Group}
     */
    private function createPublishedExamForStudent(User $teacher, User $student, int $maxAllowedIncidents = 1): array
    {
        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo Activo',
            'access_folio' => strtoupper(substr(bin2hex(random_bytes(4)), 0, 7)),
            'is_closed' => false,
        ]);
        $group->students()->attach($student->id, ['joined_at' => now()]);

        $exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen Publicado',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'final_score',
            'require_webcam' => false,
            'max_allowed_incidents' => $maxAllowedIncidents,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $exam->groups()->attach($group->id);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Apartado Único',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);

        return [$exam, $section, $group];
    }

    /**
     * @return array{0: Question, 1: ExamQuestion}
     */
    private function attachMultipleChoiceQuestion(Exam $exam, ExamSection $section, User $teacher, int $sortOrder): array
    {
        $item = MultipleChoiceItem::create([
            'options_payload' => [['index' => 0, 'text' => 'Opción A'], ['index' => 1, 'text' => 'Opción B']],
            'correct_option_index' => 1,
        ]);

        $question = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'MC',
            'stem' => '¿Cuál opción es correcta?',
            'question_type' => 'multiple_choice',
            'default_points' => 10,
            'default_time_seconds' => 60,
            'questionable_type' => MultipleChoiceItem::class,
            'questionable_id' => $item->id,
        ]);

        $examQuestion = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 60,
            'points_weight' => 10,
            'sort_order' => $sortOrder,
        ]);

        return [$question, $examQuestion];
    }

    /**
     * @return array{0: Question, 1: ExamQuestion}
     */
    private function attachOpenQuestion(Exam $exam, ExamSection $section, User $teacher, int $sortOrder): array
    {
        $item = OpenItem::create([
            'reference_answer' => 'la fotosintesis convierte luz solar en energia quimica',
        ]);

        $question = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'Open',
            'stem' => 'Explica la fotosíntesis',
            'question_type' => 'open',
            'default_points' => 10,
            'default_time_seconds' => 120,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $item->id,
        ]);

        $examQuestion = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 120,
            'points_weight' => 10,
            'sort_order' => $sortOrder,
        ]);

        return [$question, $examQuestion];
    }

    /**
     * @return array{0: Question, 1: ExamQuestion}
     */
    private function attachCodeQuestion(Exam $exam, ExamSection $section, User $teacher, int $sortOrder): array
    {
        $item = CodeItem::create([
            'programming_language' => 'php',
            'is_find_error' => false,
            'starter_code' => '<?php',
            'reference_solution' => 'echo 1;',
            'instructions' => 'Imprime el número 1',
        ]);

        $question = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'Code',
            'stem' => 'Escribe el código solicitado',
            'question_type' => 'code',
            'default_points' => 10,
            'default_time_seconds' => 180,
            'questionable_type' => CodeItem::class,
            'questionable_id' => $item->id,
        ]);

        $examQuestion = ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 180,
            'points_weight' => 10,
            'sort_order' => $sortOrder,
        ]);

        return [$question, $examQuestion];
    }
}
