<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptService.php
 * Ubicación:   app/Services/AttemptService.php
 * Descripción: Lógica transaccional de aplicación de examen (Módulo 7)
 *              compartida entre el panel web y la API móvil v1 (MPW-V2 §5.6,
 *              §6.4): validación server-side de ventana y cronómetro,
 *              calificación proporcional en relación de columnas (RF-EVA-002),
 *              omisión de IA en respuestas en blanco (RF-EVA-008) y exposición
 *              de paráfrasis activa (RF-BAN-010).
 * ============================================================================
 */

namespace App\Services;

use App\Contracts\AiEvaluatorContract;
use App\Events\ExamAttemptIncidentReported;
use App\Events\ExamAttemptStarted;
use App\Events\ExamAttemptSubmitted;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\QuestionParaphrase;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttemptService
{
    /**
     * Tolerancia técnica de red, en segundos, que se suma al límite de cada
     * pregunta antes de considerar una respuesta extemporánea (MPW-V2 §5.6.1
     * y MPM-V4 §5.4.1: tres segundos).
     */
    public const QUESTION_TIME_TOLERANCE_SECONDS = 3;

    public function __construct(
        private readonly ExamAssemblyService $assemblyService,
        private readonly AiEvaluatorContract $aiEvaluator,
        private readonly ExamNotificationService $examNotifications,
    ) {
    }

    public function start(Exam $exam, User $student): StudentAttempt
    {
        return $this->startOrResumeAttempt($exam, $student);
    }

    /**
     * Inicia o recupera el intento activo del alumno validando publicación,
     * ventana horaria (starts_at / ends_at) y pertenencia a grupo asignado.
     *
     * @throws DomainException
     */
    public function startOrResumeAttempt(Exam $exam, User $student): StudentAttempt
    {
        abort_unless($exam->is_published, 404);

        if ($exam->starts_at !== null && now()->lt($exam->starts_at)) {
            throw new DomainException('El examen aún no se encuentra dentro de su ventana de aplicación.');
        }

        if ($exam->ends_at !== null && now()->gt($exam->ends_at)) {
            throw new DomainException('La ventana de aplicación del examen ha concluido.');
        }

        $group = $exam->groups()
            ->whereHas('students', fn ($q) => $q->where('acs_users.id', $student->id))
            ->first();

        abort_unless($group, 403, 'No perteneces a ningún grupo asignado a este examen.');

        $attempt = DB::transaction(function () use ($exam, $student, $group): StudentAttempt {
            $existing = StudentAttempt::where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $sequence = $this->assemblyService->buildQuestionSequence($exam);
            $paraphraseSnapshot = $this->buildParaphraseSnapshot($sequence, (int) $student->id);

            return StudentAttempt::firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $student->id],
                [
                    'group_id' => $group->id,
                    'question_sequence_payload' => $sequence,
                    'paraphrase_snapshot_payload' => $paraphraseSnapshot,
                    'current_question_index' => 0,
                    'started_at' => now(),
                    'status' => 'in_progress',
                ],
            );
        });

        // Solo el primer inicio de cada alumno cuenta; reanudar no avisa.
        if ($attempt->wasRecentlyCreated) {
            $this->examNotifications->examStarted($exam, $attempt);

            $studentUser = $attempt->relationLoaded('student') ? $attempt->student : $attempt->student()->first();
            ExamAttemptStarted::dispatch(
                (int) $exam->id,
                (int) $attempt->id,
                (int) $student->id,
                $studentUser ? $studentUser->full_name : 'Estudiante #' . $student->id,
                now()->format('H:i:s'),
                'in_progress'
            );
        }

        return $attempt;
    }

    /**
     * @return array<string, mixed>|null null cuando ya no hay más preguntas
     */
    public function currentQuestion(StudentAttempt $attempt): ?array
    {
        $sequence = $attempt->question_sequence_payload ?? [];

        if ($attempt->current_question_index >= count($sequence)) {
            return null;
        }

        $examQuestionId = $sequence[$attempt->current_question_index];
        $examQuestion = ExamQuestion::with(['question.questionable', 'question.paraphrases'])->findOrFail($examQuestionId);

        return [
            'exam_question_id' => $examQuestion->id,
            'index' => $attempt->current_question_index,
            'total' => count($sequence),
            'time_limit_seconds' => $examQuestion->time_limit_seconds,
            'remaining_seconds' => $this->remainingSeconds($attempt, (int) $examQuestion->time_limit_seconds),
            'points_weight' => $examQuestion->points_weight,
            'question' => $this->questionPayloadWithoutAnswer($examQuestion, $attempt),
        ];
    }

    /**
     * Momento en que el servidor entregó la pregunta activa: la respuesta
     * anterior (cada respuesta devuelve la siguiente pregunta) o, para la
     * primera pregunta, el inicio del intento. No requiere columnas nuevas.
     */
    public function questionServedAt(StudentAttempt $attempt): CarbonInterface
    {
        $lastAnswer = StudentAnswer::query()
            ->where('student_attempt_id', $attempt->id)
            ->whereNotNull('answered_at')
            ->latest('answered_at')
            ->first();

        return $lastAnswer?->answered_at ?? $attempt->started_at ?? now();
    }

    /**
     * Segundos transcurridos en la pregunta activa según el reloj del
     * servidor (MPW-V2 §5.6.1: el cliente nunca es la autoridad del tiempo).
     */
    public function elapsedOnActiveQuestion(StudentAttempt $attempt): float
    {
        return max(0.0, (float) $this->questionServedAt($attempt)->diffInSeconds(now(), false));
    }

    /**
     * Segundos que le quedan al alumno en la pregunta activa, entre 0 y el
     * límite de la pregunta. Reabrir la app o volver a consultar el examen ya
     * no devuelve el tiempo completo (MPM-V4 §5.4.1).
     */
    public function remainingSeconds(StudentAttempt $attempt, int $timeLimitSeconds): int
    {
        if ($timeLimitSeconds <= 0) {
            return 0;
        }

        $remaining = ceil($timeLimitSeconds - $this->elapsedOnActiveQuestion($attempt));

        return (int) max(0, min($timeLimitSeconds, $remaining));
    }

    /**
     * Indica si una respuesta que llega ahora excede el límite de la pregunta
     * activa más la tolerancia técnica de tres segundos.
     */
    public function isAnswerLate(StudentAttempt $attempt, int $timeLimitSeconds): bool
    {
        if ($timeLimitSeconds <= 0) {
            return false;
        }

        return $this->elapsedOnActiveQuestion($attempt) > $timeLimitSeconds + self::QUESTION_TIME_TOLERANCE_SECONDS;
    }

    /**
     * Alias semántico de currentQuestion() para verificar la pregunta activa
     * aún sin responder en la secuencia del intento.
     *
     * @return array<string, mixed>|null
     */
    public function currentUnansweredQuestion(StudentAttempt $attempt): ?array
    {
        return $this->currentQuestion($attempt);
    }

    /**
     * Registra y califica la respuesta de una pregunta validando estado activo
     * y tiempo del lado servidor: la ventana del examen (con margen de 60
     * segundos) y el límite de la propia pregunta (con tolerancia de 3
     * segundos, MPW-V2 §5.6.1). Una respuesta extemporánea se conserva para
     * la revisión docente, pero se califica con cero puntos y sin evaluador
     * de IA; el intento avanza igual a la siguiente pregunta.
     *
     * @return bool true cuando la respuesta fue extemporánea
     *
     * @throws DomainException
     */
    public function submitAnswer(StudentAttempt $attempt, int $examQuestionId, mixed $answerPayload): bool
    {
        if ($attempt->status !== 'in_progress') {
            throw new DomainException('El intento ya no se encuentra activo.');
        }

        $this->assertAttemptWithinTimeLimit($attempt);

        $examQuestion = ExamQuestion::with(['question.questionable', 'question.paraphrases'])->findOrFail($examQuestionId);
        $question = $examQuestion->question;
        $questionable = $question->questionable;
        $resolvedStem = $this->resolvedStemData($examQuestion, $attempt);
        $persistedParaphraseId = $resolvedStem['paraphrase_id'] !== null
            && $question->paraphrases->contains('id', $resolvedStem['paraphrase_id'])
            ? $resolvedStem['paraphrase_id']
            : null;

        $isLate = $this->isAnswerLate($attempt, (int) $examQuestion->time_limit_seconds);

        $graded = $isLate
            ? ['is_correct' => false, 'points_awarded' => 0.0]
            : $this->gradeAnswer(
                $question->question_type,
                $questionable,
                $answerPayload,
                (float) $examQuestion->points_weight,
            );

        DB::transaction(static function () use ($attempt, $examQuestion, $graded, $answerPayload, $persistedParaphraseId, $resolvedStem): void {
            $lockedAttempt = StudentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            StudentAnswer::updateOrCreate(
                ['student_attempt_id' => $lockedAttempt->id, 'exam_question_id' => $examQuestion->id],
                array_merge($graded, [
                    'answer_payload' => $answerPayload,
                    'question_paraphrase_id' => $persistedParaphraseId,
                    'served_stem_snapshot' => $resolvedStem['stem'],
                    'answered_at' => now(),
                ]),
            );

            // No se puede regresar a una pregunta anterior aunque sobre tiempo (RW-20).
            $lockedAttempt->increment('current_question_index');
        });

        return $isLate;
    }

    /**
     * Registra un incidente de sospecha anti-trampa, incrementa el contador
     * y emite el evento en vivo por WebSockets Reverb (RW-12, RW-16, ADR-019).
     */
    public function reportIncident(StudentAttempt $attempt, string $incidentType, string $description = ''): StudentAttempt
    {
        $exam = $attempt->relationLoaded('exam') ? $attempt->exam : $attempt->exam()->first();
        $student = $attempt->relationLoaded('student') ? $attempt->student : $attempt->student()->first();

        $isCancelled = false;
        if ($attempt->status === 'in_progress') {
            $attempt->increment('incidents_count');
            $attempt->refresh();

            if ($exam && $attempt->incidents_count > $exam->max_allowed_incidents) {
                $attempt->update([
                    'status' => 'cancelled_by_incident',
                    'submitted_at' => now(),
                    'final_score' => 0.0,
                ]);
                $isCancelled = true;
            }
        }

        ExamAttemptIncidentReported::dispatch(
            (int) $attempt->exam_id,
            (int) $attempt->id,
            (int) $attempt->student_id,
            $student ? $student->full_name : 'Estudiante #' . $attempt->student_id,
            $incidentType,
            $description,
            (int) $attempt->incidents_count,
            now()->format('H:i:s'),
            $isCancelled
        );

        return $attempt;
    }

    public function submitFinal(StudentAttempt $attempt): StudentAttempt
    {
        if ($attempt->status !== 'in_progress') {
            throw new DomainException('El intento ya no se encuentra activo.');
        }

        $this->assertAttemptWithinTimeLimit($attempt);

        $submitted = DB::transaction(static function () use ($attempt): StudentAttempt {
            $answers = $attempt->answers()->with('examQuestion.question')->get();

            $objectiveScore = (float) $answers
                ->filter(static fn (StudentAnswer $a) => in_array($a->examQuestion->question->question_type, ['multiple_choice', 'matching'], true))
                ->sum('points_awarded');
            $aiEstimatedScore = (float) $answers
                ->whereNotNull('ai_similarity_score')
                ->sum('points_awarded');
            $pendingAiCount = $answers
                ->whereNull('ai_evaluated_at')
                ->filter(static fn (StudentAnswer $a) => in_array($a->examQuestion->question->question_type, ['open', 'code'], true))
                ->count();

            $attempt->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'objective_score' => $objectiveScore,
                'ai_estimated_score' => $aiEstimatedScore,
                'final_score' => $pendingAiCount === 0 ? $objectiveScore + $aiEstimatedScore : null,
                'pending_ai_count' => $pendingAiCount,
            ]);

            return $attempt->fresh();
        });

        $this->examNotifications->attemptClosed($submitted);

        $studentUser = $submitted->relationLoaded('student') ? $submitted->student : $submitted->student()->first();
        ExamAttemptSubmitted::dispatch(
            (int) $submitted->exam_id,
            (int) $submitted->id,
            (int) $submitted->student_id,
            $studentUser ? $studentUser->full_name : 'Estudiante #' . $submitted->student_id,
            now()->format('H:i:s'),
            $submitted->final_score !== null ? (float) $submitted->final_score : null,
            (string) $submitted->status
        );

        return $submitted;
    }

    /**
     * Verifica la ventana y duración del intento en el servidor con margen de
     * tolerancia de red de 60 segundos (MPW-V2 §5.6.1, RNF-CON-001).
     *
     * @throws DomainException
     */
    private function assertAttemptWithinTimeLimit(StudentAttempt $attempt): void
    {
        $exam = $attempt->relationLoaded('exam') ? $attempt->exam : $attempt->exam()->first();

        if ($exam === null) {
            return;
        }

        if ($this->deadlineHasPassed($attempt, $exam)) {
            $attempt->update(['status' => 'expired', 'submitted_at' => now()]);
            throw new DomainException('El tiempo límite del examen ha expirado.');
        }
    }

    /**
     * Indica si el intento sigue en curso (RF-GRU-017): no fue enviado ni
     * cancelado y su duración y la ventana del examen aún no vencen, con la
     * misma tolerancia de 60 segundos de la validación de envío. El estado
     * `expired` solo se marca cuando alguien intenta enviar el intento, así
     * que uno abandonado a medias deja de contar como en curso al vencer.
     */
    public function isInProgress(StudentAttempt $attempt): bool
    {
        if ($attempt->status !== 'in_progress') {
            return false;
        }

        $exam = $attempt->relationLoaded('exam') ? $attempt->exam : $attempt->exam()->first();

        return $exam === null || ! $this->deadlineHasPassed($attempt, $exam);
    }

    /**
     * Venció la duración del intento o la ventana del examen (tolerancia de
     * red de 60 segundos en ambos casos).
     */
    private function deadlineHasPassed(StudentAttempt $attempt, Exam $exam): bool
    {
        $durationMinutes = (int) ($exam->duration_minutes ?? 0);
        if ($durationMinutes > 0 && $attempt->started_at !== null) {
            $deadline = $attempt->started_at->copy()->addMinutes($durationMinutes)->addSeconds(60);
            if (now()->gt($deadline)) {
                return true;
            }
        }

        return $exam->ends_at !== null && now()->gt($exam->ends_at->copy()->addSeconds(60));
    }

    /**
     * @return array<string, mixed>
     */
    private function gradeAnswer(string $questionType, mixed $questionable, mixed $answerPayload, float $pointsWeight): array
    {
        return match ($questionType) {
            'multiple_choice' => $this->gradeMultipleChoice($questionable, $answerPayload, $pointsWeight),
            'matching' => $this->gradeMatching($questionable, $answerPayload, $pointsWeight),
            'open' => $this->gradeWithAi((string) $questionable->reference_answer, (string) ($answerPayload ?? ''), $pointsWeight),
            'code' => $this->gradeWithAi((string) $questionable->reference_solution, (string) ($answerPayload ?? ''), $pointsWeight),
            default => throw ValidationException::withMessages(['question_type' => ['Tipo de pregunta desconocido.']]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function gradeMultipleChoice(mixed $item, mixed $answerPayload, float $pointsWeight): array
    {
        $selectedIndex = (int) (is_array($answerPayload) ? ($answerPayload['selected_option_index'] ?? -1) : $answerPayload);
        $isCorrect = $selectedIndex === (int) $item->correct_option_index;

        return ['is_correct' => $isCorrect, 'points_awarded' => $isCorrect ? $pointsWeight : 0.0];
    }

    /**
     * Calificación proporcional estrictamente biyectiva en relación de columnas [CRÍTICA-03]
     * (RF-EVA-002 / P-114): (pares_correctos / total_pares) * pointsWeight.
     *
     * @return array<string, mixed>
     */
    private function gradeMatching(mixed $item, mixed $answerPayload, float $pointsWeight): array
    {
        $expectedRaw = is_array($item->pairs_payload ?? null) ? $item->pairs_payload : [];
        $totalPairs = count($expectedRaw);
        if ($totalPairs === 0 || ! is_array($answerPayload)) {
            return ['is_correct' => false, 'points_awarded' => 0.0];
        }

        if (count($answerPayload) > $totalPairs) {
            return ['is_correct' => false, 'points_awarded' => 0.0];
        }

        $validLeftIds = collect($item->left_column_payload ?? [])
            ->pluck('id')
            ->filter()
            ->mapWithKeys(static fn (mixed $id): array => [(string) $id => true])
            ->all();
        $validRightIds = collect($item->right_column_payload ?? [])
            ->pluck('id')
            ->filter()
            ->mapWithKeys(static fn (mixed $id): array => [(string) $id => true])
            ->all();

        $expectedMap = [];
        foreach ($expectedRaw as $pair) {
            if (is_array($pair) && isset($pair['left_id'], $pair['right_id'])) {
                $expectedMap[(string) $pair['left_id']] = (string) $pair['right_id'];
            }
        }

        $seenLeft = [];
        $seenRight = [];
        $correctPairs = 0;

        foreach ($answerPayload as $pair) {
            if (! is_array($pair)) {
                return ['is_correct' => false, 'points_awarded' => 0.0];
            }

            $leftId = trim((string) ($pair['left_id'] ?? ''));
            $rightId = trim((string) ($pair['right_id'] ?? ''));

            if ($leftId === '' || isset($seenLeft[$leftId])) {
                return ['is_correct' => false, 'points_awarded' => 0.0];
            }
            if ($validLeftIds !== [] && ! isset($validLeftIds[$leftId])) {
                return ['is_correct' => false, 'points_awarded' => 0.0];
            }
            $seenLeft[$leftId] = true;

            if ($rightId === '') {
                continue;
            }
            if (($validRightIds !== [] && ! isset($validRightIds[$rightId])) || isset($seenRight[$rightId])) {
                return ['is_correct' => false, 'points_awarded' => 0.0];
            }
            $seenRight[$rightId] = true;

            if (isset($expectedMap[$leftId]) && $expectedMap[$leftId] === $rightId) {
                $correctPairs++;
            }
        }

        $score = round(($correctPairs / $totalPairs) * $pointsWeight, 2);
        $isCorrect = $correctPairs === $totalPairs;

        return [
            'is_correct' => $isCorrect,
            'points_awarded' => $score,
        ];
    }

    /**
     * Evalúa respuestas abiertas y de código omitiendo la llamada al evaluador
     * de IA cuando la respuesta está en blanco (RF-EVA-008 / P-126).
     *
     * @return array<string, mixed>
     */
    private function gradeWithAi(string $referenceAnswer, string $studentAnswer, float $pointsWeight): array
    {
        if (trim($studentAnswer) === '') {
            return [
                'is_correct' => false,
                'points_awarded' => 0.0,
                'ai_similarity_score' => 0.0,
                'ai_meets_goal' => false,
                'ai_feedback' => 'Respuesta en blanco; se asigna puntaje 0 sin consumo de IA.',
                'ai_evaluated_at' => now(),
            ];
        }

        $result = $this->aiEvaluator->evaluate($studentAnswer, $referenceAnswer);

        return [
            'ai_similarity_score' => $result['similarity_score'],
            'ai_meets_goal' => $result['meets_goal'],
            'ai_feedback' => $result['feedback'],
            'ai_evaluated_at' => now(),
            'points_awarded' => $result['meets_goal'] ? $pointsWeight : 0.0,
        ];
    }

    /**
     * Construye el payload de la pregunta ocultando la respuesta correcta y
     * aplicando la paráfrasis congelada en snapshot o activa (RF-BAN-010).
     *
     * @return array<string, mixed>
     */
    private function questionPayloadWithoutAnswer(ExamQuestion $examQuestion, ?StudentAttempt $attempt = null): array
    {
        $question = $examQuestion->question;
        $item = $question->questionable;

        $resolved = $this->resolvedStemData($examQuestion, $attempt);
        $stem = $resolved['stem'];

        $itemPayload = match ($question->question_type) {
            'multiple_choice' => ['options' => $item->options_payload],
            'matching' => ['left_items' => $item->left_column_payload, 'right_items' => $item->right_column_payload],
            'open' => [],
            'code' => [
                'programming_language' => $item->programming_language,
                'is_find_error' => $item->is_find_error,
                'starter_code' => $item->starter_code,
                'instructions' => $item->instructions,
            ],
            default => [],
        };

        $basePayload = [
            'id' => $question->id,
            'title' => $question->title,
            'stem' => $stem,
            'question_type' => $question->question_type,
        ];

        if ($resolved['paraphrase_id'] !== null) {
            $basePayload['paraphrase_id'] = $resolved['paraphrase_id'];
            $basePayload['paraphrased_text'] = $stem;
        }

        return array_merge($basePayload, $itemPayload);
    }

    /**
     * Enunciado que el estudiante vio en su intento (paráfrasis congelada en
     * snapshot o rotación determinista), para mostrarlo igual en la revisión
     * posterior (RF-REP-005).
     */
    public function visibleStem(ExamQuestion $examQuestion, ?StudentAttempt $attempt = null): string
    {
        return $this->resolvedStemData($examQuestion, $attempt)['stem'];
    }

    /**
     * Construye el mapa inmutable de enunciados servidos por exam_question_id
     * al iniciar el intento, con rotación 1 + N (enunciado raíz + paráfrasis)
     * cargando las relaciones en bloque sin N+1 [ALTA-09 / MEDIA-05].
     *
     * @param array<int, int> $sequence
     * @return array<string, array{paraphrase_id: ?int, stem: string}>
     */
    private function buildParaphraseSnapshot(array $sequence, int $studentId): array
    {
        if ($sequence === []) {
            return [];
        }

        $examQuestions = ExamQuestion::with('question.paraphrases')
            ->whereIn('id', $sequence)
            ->get()
            ->keyBy('id');

        $snapshot = [];
        foreach ($sequence as $examQuestionId) {
            $examQuestion = $examQuestions->get((int) $examQuestionId);
            if ($examQuestion === null || $examQuestion->question === null) {
                continue;
            }

            $selected = $this->selectVariantForStudent($examQuestion, $studentId);
            $snapshot[(string) $examQuestion->id] = [
                'paraphrase_id' => $selected['paraphrase_id'],
                'stem' => $selected['stem'],
            ];
        }

        return $snapshot;
    }

    /**
     * Resuelve el enunciado y paráfrasis efectivos priorizando el snapshot congelado
     * en el intento para garantizar inmutabilidad histórica [ALTA-09 / MEDIA-05].
     *
     * @return array{paraphrase_id: ?int, paraphrase: ?QuestionParaphrase, stem: string}
     */
    private function resolvedStemData(ExamQuestion $examQuestion, ?StudentAttempt $attempt): array
    {
        $question = $examQuestion->question;

        if ($attempt !== null && is_array($attempt->paraphrase_snapshot_payload)) {
            $snapshotEntry = $attempt->paraphrase_snapshot_payload[(string) $examQuestion->id]
                ?? $attempt->paraphrase_snapshot_payload[(int) $examQuestion->id]
                ?? null;

            if (is_array($snapshotEntry) && array_key_exists('stem', $snapshotEntry)) {
                $paraphraseId = isset($snapshotEntry['paraphrase_id']) && $snapshotEntry['paraphrase_id'] !== null
                    ? (int) $snapshotEntry['paraphrase_id']
                    : null;
                $frozenStem = (string) $snapshotEntry['stem'];
                $paraphrase = null;

                if ($paraphraseId !== null) {
                    $paraphrases = $question->relationLoaded('paraphrases')
                        ? $question->paraphrases
                        : $question->paraphrases()->get();
                    $paraphrase = $paraphrases->firstWhere('id', $paraphraseId);
                    if ($paraphrase === null) {
                        $paraphrase = new QuestionParaphrase([
                            'question_id' => $question->id,
                            'stem' => $frozenStem,
                        ]);
                        $paraphrase->id = $paraphraseId;
                        $paraphrase->exists = true;
                    }
                }

                return [
                    'paraphrase_id' => $paraphraseId,
                    'paraphrase' => $paraphrase,
                    'stem' => $frozenStem,
                ];
            }
        }

        $seedActor = (int) ($attempt?->student_id ?? $attempt?->id ?? 0);

        return $this->selectVariantForStudent($examQuestion, $seedActor);
    }

    /**
     * @return array{paraphrase_id: ?int, paraphrase: ?QuestionParaphrase, stem: string}
     */
    private function selectVariantForStudent(ExamQuestion $examQuestion, int $seedActor): array
    {
        $question = $examQuestion->question;
        $paraphrases = $question->relationLoaded('paraphrases')
            ? $question->paraphrases
            : $question->paraphrases()->get();

        if ($paraphrases->isEmpty()) {
            return [
                'paraphrase_id' => null,
                'paraphrase' => null,
                'stem' => (string) $question->stem,
            ];
        }

        $explicitParaphraseId = $examQuestion->getAttribute('paraphrase_id');
        if ($explicitParaphraseId !== null) {
            $chosen = $paraphrases->firstWhere('id', (int) $explicitParaphraseId) ?? $paraphrases->first();

            return [
                'paraphrase_id' => $chosen !== null ? (int) $chosen->id : null,
                'paraphrase' => $chosen,
                'stem' => $chosen !== null ? (string) $chosen->stem : (string) $question->stem,
            ];
        }

        // Rotación 1 + N incluyendo el enunciado original en el índice 0
        $variants = array_merge([null], $paraphrases->values()->all());
        $hash = (int) sprintf('%u', crc32($seedActor . ':' . $examQuestion->id));
        $chosen = $variants[$hash % count($variants)];

        if ($chosen instanceof QuestionParaphrase) {
            return [
                'paraphrase_id' => (int) $chosen->id,
                'paraphrase' => $chosen,
                'stem' => (string) $chosen->stem,
            ];
        }

        return [
            'paraphrase_id' => null,
            'paraphrase' => null,
            'stem' => (string) $question->stem,
        ];
    }

    private function resolveActiveParaphrase(ExamQuestion $examQuestion, ?StudentAttempt $attempt): ?QuestionParaphrase
    {
        return $this->resolvedStemData($examQuestion, $attempt)['paraphrase'];
    }
}
