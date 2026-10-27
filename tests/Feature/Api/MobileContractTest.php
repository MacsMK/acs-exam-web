<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     MobileContractTest.php
 * Ubicación:   tests/Feature/Api/MobileContractTest.php
 * Descripción: Valida de punta a punta que la API v1 responde exactamente
 *              en la forma que espera el cliente Flutter (acs-exam-mobile,
 *              lib/core/network/api_endpoints.dart y los modelos de
 *              lib/features/*\/data/models), para los puntos que estaban
 *              "pendientes de confirmación con el equipo backend" (DE-03).
 * ============================================================================
 */

namespace Tests\Feature\Api;

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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_two_factor_and_logout(): void
    {
        $user = $this->createUser('student');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Demo1234',
        ]);

        $login->assertOk();
        $login->assertJsonPath('status', 'success');

        $user->refresh();
        $this->assertNotNull($user->two_factor_code);

        $twoFactor = $this->postJson('/api/v1/auth/two-factor', [
            'code' => $user->two_factor_code,
        ]);

        $twoFactor->assertOk();
        $twoFactor->assertJsonPath('status', 'success');
        $twoFactor->assertJsonPath('data.role', 'student');
        $token = $twoFactor->json('data.token');
        $this->assertNotEmpty($token);

        $logout = $this->withToken($token)->postJson('/api/v1/auth/logout');
        $logout->assertOk();
        $logout->assertJsonPath('status', 'success');
    }

    public function test_two_factor_rejects_invalid_code(): void
    {
        $response = $this->postJson('/api/v1/auth/two-factor', ['code' => '000000']);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
    }

    public function test_privacy_notice_show_and_accept(): void
    {
        PrivacyNotice::create([
            'version' => 'v1.0',
            'content' => 'Contenido de prueba.',
            'published_at' => now(),
        ]);

        $token = $this->tokenFor($this->createUser('student'));

        $show = $this->withToken($token)->getJson('/api/v1/privacy-notice');
        $show->assertOk();
        $show->assertJsonPath('data.version', 'v1.0');
        $show->assertJsonPath('data.accepted', false);

        $this->withToken($token)->postJson('/api/v1/privacy-notice/accept')->assertOk();

        $this->withToken($token)->getJson('/api/v1/privacy-notice')
            ->assertJsonPath('data.accepted', true);
    }

    public function test_group_join_index_and_students_list(): void
    {
        $teacher = $this->createUser('teacher');
        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo de prueba',
            'access_folio' => 'TESTF01',
            'is_closed' => false,
        ]);

        $student = $this->createUser('student');
        $studentToken = $this->tokenFor($student);

        $join = $this->withToken($studentToken)->postJson('/api/v1/groups/join', ['folio' => 'testf01']);
        $join->assertOk();
        $join->assertJsonPath('data.folio', 'TESTF01');
        $join->assertJsonPath('data.students_count', 1);

        $index = $this->withToken($studentToken)->getJson('/api/v1/groups');
        $index->assertOk();
        $this->assertContains('TESTF01', collect($index->json('data'))->pluck('folio')->all());

        $teacherToken = $this->tokenFor($teacher);
        $students = $this->withToken($teacherToken)->getJson("/api/v1/groups/{$group->id}/students");
        $students->assertOk();
        $students->assertJsonFragment(['name' => $student->full_name]);
    }

    public function test_group_join_rejects_unknown_folio(): void
    {
        $token = $this->tokenFor($this->createUser('student'));

        $response = $this->withToken($token)->postJson('/api/v1/groups/join', ['folio' => 'NOEXISTE']);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
    }

    public function test_full_exam_flow_covers_all_question_types_and_feeds_grades(): void
    {
        $teacher = $this->createUser('teacher');
        $student = $this->createUser('student');
        $studentToken = $this->tokenFor($student);

        $group = Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Grupo examen',
            'access_folio' => 'EXAMG01',
            'is_closed' => false,
        ]);
        $group->students()->attach($student->id, ['joined_at' => now()]);

        $exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen de prueba',
            'grading_scale' => '0-10',
            'randomize_question_order' => false,
            'result_visibility_mode' => 'immediate_score',
            'require_webcam' => false,
            'max_allowed_incidents' => 1,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'share_token' => bin2hex(random_bytes(8)),
            'is_published' => true,
        ]);
        $exam->groups()->attach($group->id);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'Única',
            'question_type' => 'multiple_choice',
            'requested_quantity' => 1,
            'sort_order' => 0,
        ]);

        [$mcQuestion] = $this->attachMultipleChoiceQuestion($exam, $section, $teacher, 0);
        [$matchingQuestion] = $this->attachMatchingQuestion($exam, $section, $teacher, 1);
        [$openQuestion] = $this->attachOpenQuestion($exam, $section, $teacher, 2);
        [$codeQuestion] = $this->attachCodeQuestion($exam, $section, $teacher, 3);

        $index = $this->withToken($studentToken)->getJson('/api/v1/exams');
        $index->assertOk();
        $this->assertContains((string) $exam->id, collect($index->json('data'))->pluck('id')->all());

        // --- take: primera pregunta (opción múltiple) ---
        $take = $this->withToken($studentToken)->postJson('/api/v1/exams/take', ['exam_id' => $exam->id]);
        $take->assertOk();
        $take->assertJsonPath('data.question.question_type', 'multiple_choice');
        $take->assertJsonPath('data.question.id', (string) $mcQuestion->id);
        $this->assertSame(['0', '1'], collect($take->json('data.question.options'))->pluck('id')->all());

        // --- responde opción múltiple (correcta, índice 1) -> avanza a relación de columnas ---
        $answer1 = $this->withToken($studentToken)->postJson("/api/v1/exams/{$exam->id}/answers", [
            'question_id' => (string) $mcQuestion->id,
            'selected_option_ids' => ['1'],
        ]);
        $answer1->assertOk();
        $answer1->assertJsonPath('data.question.question_type', 'matching');
        $this->assertSame(['L1'], collect($answer1->json('data.question.left_column'))->pluck('id')->all());
        $this->assertSame(['R1'], collect($answer1->json('data.question.right_column'))->pluck('id')->all());

        // --- responde relación de columnas (correcta) -> avanza a abierta ---
        $answer2 = $this->withToken($studentToken)->postJson("/api/v1/exams/{$exam->id}/answers", [
            'question_id' => (string) $matchingQuestion->id,
            'pairs' => ['L1' => 'R1'],
        ]);
        $answer2->assertOk();
        $answer2->assertJsonPath('data.question.question_type', 'open');

        // --- la pregunta activa ya cambió: reenviar la respuesta anterior debe rechazarse (409) ---
        $stale = $this->withToken($studentToken)->postJson("/api/v1/exams/{$exam->id}/answers", [
            'question_id' => (string) $matchingQuestion->id,
            'pairs' => ['L1' => 'R1'],
        ]);
        $stale->assertStatus(409);

        // --- reporta un incidente (no debe cancelar: max_allowed_incidents = 1) ---
        $this->withToken($studentToken)->postJson("/api/v1/exams/{$exam->id}/incidents", [
            'incident_type' => 'focus_lost',
        ])->assertOk();

        // --- responde abierta (coincide con la referencia) -> avanza a código ---
        $answer3 = $this->withToken($studentToken)->postJson("/api/v1/exams/{$exam->id}/answers", [
            'question_id' => (string) $openQuestion->id,
            'text' => 'la fotosintesis convierte luz en energia',
        ]);
        $answer3->assertOk();
        $answer3->assertJsonPath('data.question.question_type', 'code');
        $answer3->assertJsonPath('data.question.language', 'php');
        $answer3->assertJsonPath('data.question.initial_code', '<?php');

        // --- responde código (coincide con la referencia) -> examen termina ---
        $answer4 = $this->withToken($studentToken)->postJson("/api/v1/exams/{$exam->id}/answers", [
            'question_id' => (string) $codeQuestion->id,
            'code' => 'echo 1;',
        ]);
        $answer4->assertOk();
        $this->assertNull($answer4->json('data.question'));

        // --- avance del examen, del lado docente ---
        $teacherToken = $this->tokenFor($teacher);
        $progress = $this->withToken($teacherToken)->getJson("/api/v1/exams/{$exam->id}/progress");
        $progress->assertOk();
        $progress->assertJsonPath('data.answered_count', 1);
        $progress->assertJsonPath('data.total_count', 1);

        // --- la calificación ya terminada alimenta /grades y /grades/statistics ---
        $grades = $this->withToken($studentToken)->getJson('/api/v1/grades');
        $grades->assertOk();
        $rows = $grades->json('data.data');
        $this->assertNotEmpty($rows);
        $this->assertSame('Examen de prueba', $rows[0]['exam_name']);
        $this->assertSame(40.0, $rows[0]['score']);
        $this->assertTrue($rows[0]['is_passing']);

        $stats = $this->withToken($studentToken)->getJson('/api/v1/grades/statistics');
        $stats->assertOk();
        $this->assertNotEmpty($stats->json('data.grades'));
    }

    public function test_device_token_registration(): void
    {
        $token = $this->tokenFor($this->createUser('student'));

        $response = $this->withToken($token)->postJson('/api/v1/devices', ['token' => 'fcm-token-abc']);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }

    private function createUser(string $role): User
    {
        return User::create([
            'role' => $role,
            'first_name' => $role === 'teacher' ? 'Laura' : 'Luis',
            'last_name' => $role === 'teacher' ? 'Martínez' : 'Gómez',
            'email' => uniqid("{$role}-", true).'@example.test',
            'password' => 'Demo1234',
            'is_active' => true,
        ]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * @return array{0: Question}
     */
    private function attachMultipleChoiceQuestion(Exam $exam, ExamSection $section, User $teacher, int $sortOrder): array
    {
        $item = MultipleChoiceItem::create([
            'options_payload' => [['index' => 0, 'text' => 'A'], ['index' => 1, 'text' => 'B']],
            'correct_option_index' => 1,
        ]);

        $question = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'MC',
            'stem' => '¿Cuál es correcta?',
            'question_type' => 'multiple_choice',
            'default_points' => 10,
            'default_time_seconds' => 60,
            'questionable_type' => MultipleChoiceItem::class,
            'questionable_id' => $item->id,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 60,
            'points_weight' => 10,
            'sort_order' => $sortOrder,
        ]);

        return [$question];
    }

    /**
     * @return array{0: Question}
     */
    private function attachMatchingQuestion(Exam $exam, ExamSection $section, User $teacher, int $sortOrder): array
    {
        $item = MatchingItem::create([
            'left_column_payload' => [['id' => 'L1', 'text' => 'Concepto']],
            'right_column_payload' => [['id' => 'R1', 'text' => 'Definición']],
            'pairs_payload' => [['left_id' => 'L1', 'right_id' => 'R1']],
        ]);

        $question = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'Match',
            'stem' => 'Relaciona',
            'question_type' => 'matching',
            'default_points' => 10,
            'default_time_seconds' => 60,
            'questionable_type' => MatchingItem::class,
            'questionable_id' => $item->id,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 60,
            'points_weight' => 10,
            'sort_order' => $sortOrder,
        ]);

        return [$question];
    }

    /**
     * @return array{0: Question}
     */
    private function attachOpenQuestion(Exam $exam, ExamSection $section, User $teacher, int $sortOrder): array
    {
        $item = OpenItem::create(['reference_answer' => 'la fotosintesis convierte luz en energia']);

        $question = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'Open',
            'stem' => 'Explica',
            'question_type' => 'open',
            'default_points' => 10,
            'default_time_seconds' => 120,
            'questionable_type' => OpenItem::class,
            'questionable_id' => $item->id,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 120,
            'points_weight' => 10,
            'sort_order' => $sortOrder,
        ]);

        return [$question];
    }

    /**
     * @return array{0: Question}
     */
    private function attachCodeQuestion(Exam $exam, ExamSection $section, User $teacher, int $sortOrder): array
    {
        $item = CodeItem::create([
            'programming_language' => 'php',
            'is_find_error' => false,
            'starter_code' => '<?php',
            'reference_solution' => 'echo 1;',
            'instructions' => 'Imprime 1',
        ]);

        $question = Question::create([
            'teacher_id' => $teacher->id,
            'title' => 'Code',
            'stem' => 'Programa',
            'question_type' => 'code',
            'default_points' => 10,
            'default_time_seconds' => 180,
            'questionable_type' => CodeItem::class,
            'questionable_id' => $item->id,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section->id,
            'question_id' => $question->id,
            'time_limit_seconds' => 180,
            'points_weight' => 10,
            'sort_order' => $sortOrder,
        ]);

        return [$question];
    }
}
