<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EvaluationPeriod;
use App\Models\Exam;
use App\Models\Group;
use App\Models\PrivacyNotice;
use App\Models\Question;
use App\Models\StudentAttempt;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\TeacherVerification;
use App\Models\User;
use App\Services\AttemptService;
use App\Services\ExamAssemblyService;
use App\Services\QuestionBankService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Carga cuentas y entregas ficticias para probar evaluación asistida en la web local.
 */
final class GeminiDemoSeeder extends Seeder
{
    private const DOMAIN = '@gemini.acode-solutions.test';

    private const TEACHERS = [
        [
            'login' => 'docente.programacion',
            'name' => 'Elena',
            'surname' => 'Ríos Demo',
            'tier' => 'basic',
            'subject' => GeminiDemoQuestions::PROGRAMMING,
            'sections' => ['prog-loop', 'prog-pure', 'prog-sum-even'],
        ],
        [
            'login' => 'docente.bases',
            'name' => 'Andrés',
            'surname' => 'Vega Demo',
            'tier' => 'advanced',
            'subject' => GeminiDemoQuestions::DATABASES,
            'sections' => ['db-is-null', 'db-primary-key', 'db-inner-join'],
        ],
    ];

    private const STUDENT_NAMES = ['Abril', 'Bruno', 'Celia', 'Diego', 'Emilia', 'Fabián',
        'Gabriela', 'Hugo', 'Inés', 'Javier', 'Karla', 'Leonardo'];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('El escenario de Gemini requiere un entorno local o de pruebas.');
        }

        $password = Str::password(18, symbols: false);
        $hash = Hash::make($password);
        $notice = PrivacyNotice::query()->where('published_at', '<=', now())->latest('published_at')->latest('id')->first();
        $accountCount = 0;
        $studentIndex = 0;

        config(['services.fcm.enabled' => false, 'mail.default' => 'array', 'broadcasting.default' => 'null']);
        $bank = app(QuestionBankService::class);
        $assembly = app(ExamAssemblyService::class);
        $attemptService = app(AttemptService::class);

        DB::transaction(function () use ($hash, $notice, $bank, $assembly, $attemptService, &$accountCount, &$studentIndex): void {
            foreach (self::TEACHERS as $scenario) {
                $teacher = $this->account($scenario['login'], $scenario['name'], $scenario['surname'], 'teacher', $hash, $notice);
                $accountCount += (int) $teacher->wasRecentlyCreated;
                $this->prepareTeacher($teacher, $scenario['tier']);
                $questions = $this->seedBank($teacher, $scenario['subject'], $bank);

                $rosters = [];
                foreach (['A', 'B'] as $section) {
                    $group = Group::firstOrCreate(
                        ['teacher_id' => $teacher->id, 'name' => "{$scenario['subject']} · Demo Gemini {$section}"],
                        ['subject' => $scenario['subject'], 'access_folio' => strtoupper(Str::random(8)), 'is_closed' => false],
                    );
                    EvaluationPeriod::firstOrCreate(
                        ['group_id' => $group->id, 'name' => 'Primer periodo de demostración'],
                        ['formula' => 'exams*1', 'starts_at' => now()->subWeek(), 'ends_at' => now()->addMonth()],
                    );

                    $students = [];
                    for ($seat = 0; $seat < 3; $seat++) {
                        $name = self::STUDENT_NAMES[$studentIndex++];
                        $student = $this->account(sprintf('alumno%02d', $studentIndex), $name, 'Prueba Demo', 'student', $hash, $notice);
                        $accountCount += (int) $student->wasRecentlyCreated;

                        if (! $group->students()->whereKey($student->id)->exists()) {
                            $group->students()->attach($student->id, ['joined_at' => now()]);
                        }
                        $students[] = $student;
                    }
                    $rosters[$group->id] = $students;
                }

                $exam = $this->seedExam($teacher, $scenario, $questions, array_keys($rosters), $assembly);
                foreach (array_values($rosters) as $groupIndex => $students) {
                    $this->seedSubmission($exam, $students[0], $scenario['sections'], 0, $attemptService);
                    $this->seedSubmission($exam, $students[1], $scenario['sections'], $groupIndex + 1, $attemptService);
                }
            }
        });

        $this->command?->info('Escenario Gemini preparado: 2 docentes, 12 alumnos, 4 grupos, 20 reactivos y 2 exámenes.');
        if ($accountCount > 0) {
            $this->command?->info("Contraseña asignada a las {$accountCount} cuentas nuevas: {$password}");
        }
    }

    private function account(string $login, string $name, string $surname, string $role, string $hash, ?PrivacyNotice $notice): User
    {
        $user = User::firstOrCreate(
            ['email' => $login.self::DOMAIN],
            ['first_name' => $name, 'last_name' => $surname, 'role' => $role,
                'password' => $hash, 'is_active' => true, 'email_verified_at' => now()],
        );
        if ($user->role !== $role) {
            throw new RuntimeException("La cuenta de demostración {$login} ya existe con otro rol.");
        }
        if ($notice !== null) {
            $user->privacyAcceptances()->firstOrCreate(
                ['privacy_notice_id' => $notice->id],
                ['accepted_at' => now(), 'ip_address' => '127.0.0.1'],
            );
        }

        return $user;
    }

    private function prepareTeacher(User $teacher, string $tier): void
    {
        TeacherVerification::firstOrCreate(
            ['user_id' => $teacher->id],
            ['status' => TeacherVerification::STATUS_APPROVED, 'verification_method' => TeacherVerification::METHOD_INVITATION_CODE,
                'institution_name' => 'Institución de demostración Gemini', 'educational_level' => 'superior', 'is_institutional_email' => true,
                'submitted_at' => now(), 'reviewed_at' => now(), 'plan_onboarding_completed_at' => now()],
        );

        $package = SubscriptionPackage::query()->where('tier', $tier)->firstOrFail();
        TeacherSubscription::firstOrCreate(
            ['teacher_id' => $teacher->id],
            ['subscription_package_id' => $package->id, 'payment_status' => 'paid',
                'starts_at' => now(), 'expires_at' => now()->addYear(), 'auto_renew' => false],
        );
    }

    /** @return array<string, Question> */
    private function seedBank(User $teacher, string $subject, QuestionBankService $bank): array
    {
        $questions = [];
        foreach (GeminiDemoQuestions::questions() as $key => $data) {
            if ($data['subject_tag'] === $subject) {
                $questions[$key] = Question::query()->where('teacher_id', $teacher->id)->where('title', $data['title'])->first()
                    ?? $bank->createQuestion($teacher, $data);
            }
        }

        return $questions;
    }

    /** @param array<string, Question> $questions */
    private function seedExam(User $teacher, array $scenario, array $questions, array $groupIds, ExamAssemblyService $assembly): Exam
    {
        $exam = Exam::firstOrCreate(
            ['teacher_id' => $teacher->id, 'title' => "Demo Gemini · {$scenario['subject']}"],
            ['description' => 'Examen ficticio de demostración con preguntas objetivas y cualitativas.',
                'grading_scale' => '0-10', 'result_visibility_mode' => 'final_score', 'randomize_question_order' => false,
                'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'share_token' => Str::random(32), 'is_published' => false],
        );
        if (! $exam->wasRecentlyCreated) {
            return $exam;
        }

        $exam->groups()->attach($groupIds);
        $sections = ['Opción múltiple', 'Respuesta abierta', 'Código'];
        $weights = [25, 35, 40];
        foreach ($scenario['sections'] as $index => $key) {
            $question = $questions[$key];
            $section = $assembly->acceptProposal($exam, $sections[$index], $question->question_type, [$question->id], $question->subject_tag);
            $section->examQuestions()->update(['points_weight' => $weights[$index]]);
        }

        $readiness = $assembly->evaluatePublishReadiness($exam->fresh());
        if (! $readiness['can_publish']) {
            throw new RuntimeException(implode('. ', $readiness['messages']));
        }
        $exam->update(['is_published' => true]);

        return $exam;
    }

    /** @param list<string> $keys */
    private function seedSubmission(Exam $exam, User $student, array $keys, int $variant, AttemptService $service): void
    {
        if (StudentAttempt::query()->where('exam_id', $exam->id)->where('student_id', $student->id)->exists()) {
            return;
        }

        $attempt = $service->startOrResumeAttempt($exam, $student);
        $questionBank = GeminiDemoQuestions::questions();
        $responses = GeminiDemoQuestions::responses();
        $answers = [
            ['selected_option_index' => $questionBank[$keys[0]]['correct_option_index']],
            ['text' => $responses[$keys[1]][$variant === 2 ? 1 : $variant]],
            ['code' => $responses[$keys[2]][$variant]],
        ];
        foreach ($attempt->question_sequence_payload as $index => $examQuestionId) {
            $service->submitAnswer($attempt, (int) $examQuestionId, $answers[$index]);
        }
        $service->submitFinal($attempt);
    }
}
