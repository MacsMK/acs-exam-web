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
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\QuestionParaphrase;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttemptService
{
    public function __construct(
        private readonly ExamAssemblyService $assemblyService,
        private readonly AiEvaluatorContract $aiEvaluator,
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

        return DB::transaction(function () use ($exam, $student, $group): StudentAttempt {
            return StudentAttempt::firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $student->id],
                [
                    'group_id' => $group->id,
                    'question_sequence_payload' => $this->assemblyService->buildQuestionSequence($exam),
                    'current_question_index' => 0,
                    'started_at' => now(),
                    'status' => 'in_progress',
                ],
            );
        });
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
            'points_weight' => $examQuestion->points_weight,
            'question' => $this->questionPayloadWithoutAnswer($examQuestion, $attempt),
        ];
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
     * y tiempo límite del lado servidor con tolerancia de red de 60 segundos.
     *
     * @throws DomainException
     */
    public function submitAnswer(StudentAttempt $attempt, int $examQuestionId, mixed $answerPayload): void
    {
        if ($attempt->status !== 'in_progress') {
            throw new DomainException('El intento ya no se encuentra activo.');
        }

        $this->assertAttemptWithinTimeLimit($attempt);

        $examQuestion = ExamQuestion::with('question.questionable')->findOrFail($examQuestionId);
        $question = $examQuestion->question;
        $questionable = $question->questionable;

        $graded = $this->gradeAnswer(
            $question->question_type,
            $questionable,
            $answerPayload,
            (float) $examQuestion->points_weight,
        );

        DB::transaction(static function () use ($attempt, $examQuestion, $graded, $answerPayload): void {
            StudentAnswer::updateOrCreate(
                ['student_attempt_id' => $attempt->id, 'exam_question_id' => $examQuestion->id],
                array_merge($graded, [
                    'answer_payload' => $answerPayload,
                    'answered_at' => now(),
                ]),
            );

            // No se puede regresar a una pregunta anterior aunque sobre tiempo (RW-20).
            $attempt->increment('current_question_index');
        });
    }

    public function submitFinal(StudentAttempt $attempt): StudentAttempt
    {
        if ($attempt->status !== 'in_progress') {
            throw new DomainException('El intento ya no se encuentra activo.');
        }

        $this->assertAttemptWithinTimeLimit($attempt);

        return DB::transaction(static function () use ($attempt): StudentAttempt {
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

        $durationMinutes = (int) ($exam->duration_minutes ?? 0);
        if ($durationMinutes > 0 && $attempt->started_at !== null) {
            $deadline = $attempt->started_at->copy()->addMinutes($durationMinutes)->addSeconds(60);
            if (now()->gt($deadline)) {
                $attempt->update(['status' => 'expired', 'submitted_at' => now()]);
                throw new DomainException('El tiempo límite del examen ha expirado.');
            }
        }

        if ($exam->ends_at !== null && now()->gt($exam->ends_at->copy()->addSeconds(60))) {
            $attempt->update(['status' => 'expired', 'submitted_at' => now()]);
            throw new DomainException('El tiempo límite del examen ha expirado.');
        }
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
     * Calificación proporcional en relación de columnas (RF-EVA-002 / P-114):
     * (pares_correctos / total_pares) * pointsWeight.
     *
     * @return array<string, mixed>
     */
    private function gradeMatching(mixed $item, mixed $answerPayload, float $pointsWeight): array
    {
        $submittedPairs = is_array($answerPayload) ? $answerPayload : [];
        $expected = $this->normalizePairs($item->pairs_payload ?? []);
        $normalized = array_values(array_unique($this->normalizePairs($submittedPairs)));

        $totalPairs = count($expected);
        if ($totalPairs === 0) {
            return ['is_correct' => false, 'points_awarded' => 0.0];
        }

        $correctPairs = count(array_intersect($expected, $normalized));
        $score = round(($correctPairs / $totalPairs) * $pointsWeight, 2);
        $isCorrect = $correctPairs === $totalPairs;

        return [
            'is_correct' => $isCorrect,
            'points_awarded' => $score,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $pairs
     * @return array<int, string>
     */
    private function normalizePairs(array $pairs): array
    {
        $normalized = array_map(
            static fn (array $pair) => ($pair['left_id'] ?? '').'-'.($pair['right_id'] ?? ''),
            $pairs,
        );
        sort($normalized);

        return $normalized;
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
     * aplicando la paráfrasis activa cuando existe (RF-BAN-010).
     *
     * @return array<string, mixed>
     */
    private function questionPayloadWithoutAnswer(ExamQuestion $examQuestion, ?StudentAttempt $attempt = null): array
    {
        $question = $examQuestion->question;
        $item = $question->questionable;

        $activeParaphrase = $this->resolveActiveParaphrase($examQuestion, $attempt);
        $stem = $activeParaphrase !== null ? $activeParaphrase->stem : $question->stem;

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

        if ($activeParaphrase !== null) {
            $basePayload['paraphrase_id'] = $activeParaphrase->id;
            $basePayload['paraphrased_text'] = $activeParaphrase->stem;
        }

        return array_merge($basePayload, $itemPayload);
    }

    private function resolveActiveParaphrase(ExamQuestion $examQuestion, ?StudentAttempt $attempt): ?QuestionParaphrase
    {
        $question = $examQuestion->question;
        $paraphrases = $question->relationLoaded('paraphrases')
            ? $question->paraphrases
            : $question->paraphrases()->get();

        if ($paraphrases->isEmpty()) {
            return null;
        }

        $explicitParaphraseId = $examQuestion->getAttribute('paraphrase_id');
        if ($explicitParaphraseId !== null) {
            return $paraphrases->firstWhere('id', (int) $explicitParaphraseId) ?? $paraphrases->first();
        }

        $seed = ($attempt?->id ?? 0) + $examQuestion->id;

        return $paraphrases->values()->get($seed % $paraphrases->count());
    }
}
