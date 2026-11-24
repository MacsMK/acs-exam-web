<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AiEvaluationService.php
 * Ubicación:      app/Services/AiEvaluationService.php
 * Requerimiento:  RF-IAR-001..014, RF-EVA-004..013, RNF-PRI-001, RNF-CON-002,
 *                 ADR-059
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Servicio de dominio transaccional que orquesta la evaluación
 *                 asistida por IA bajo demanda (individual y en lote), el
 *                 despacho a colas, la minimización estricta de datos LFPDPPP,
 *                 el descuento atómico de créditos solo ante respuestas reales
 *                 (RF-IAR-011) y el tablero docente de consumo de créditos IA.
 * ============================================================================
 */

namespace App\Services;

use App\Contracts\AiEvaluatorContract;
use App\Exceptions\SubscriptionLimitExceededException;
use App\Jobs\EvaluateStudentAnswerJob;
use App\Models\AiCreditUsage;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Services\Ai\GeminiAiEvaluator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Orquestador de evaluaciones asistidas por IA bajo demanda y control de créditos.
 *
 * @package App\Services
 */
class AiEvaluationService
{
    /**
     * Inicializa el servicio inyectando el contrato de IA y gestor de cuotas SaaS.
     *
     * @param AiEvaluatorContract $aiEvaluator Driver activo de evaluación semántica.
     * @param SubscriptionLimitService $subscriptionLimitService Gestor de límites y créditos SaaS.
     */
    public function __construct(
        private readonly AiEvaluatorContract $aiEvaluator,
        private readonly SubscriptionLimitService $subscriptionLimitService,
    ) {
    }

    /**
     * Compila la cuota vigente y los indicadores de respuestas del docente.
     * Cada respuesta se cuenta si su último resultado guardado proviene de IA externa.
     *
     * @param User $teacher Cuenta docente autenticada.
     * @return array{
     *     creditSummary: array<string, mixed>,
     *     aiEvaluatedAnswersCount: int,
     *     aiReviewedAnswersCount: int,
     *     activeDriver: string,
     *     hasGeminiKey: bool
     * }
     */
    public function getTeacherAiCreditsOverview(User $teacher): array
    {
        $aiAnswersQuery = StudentAnswer::query()
            ->whereHas('examQuestion.exam', static function ($query) use ($teacher): void {
                $query->where('teacher_id', $teacher->id);
            })
            ->whereNotNull('ai_evaluated_at')
            ->whereNotNull('ai_similarity_score')
            ->where('ai_is_fallback', false);

        $counts = (clone $aiAnswersQuery)->toBase()
            ->whereNotIn('ai_model_used', ['none', 'mock-local'])
            ->selectRaw('COUNT(*) AS evaluated, COUNT(teacher_recoteje_score) AS reviewed')
            ->first();

        $evaluated = (int) $counts->evaluated;
        $reviewed = (int) $counts->reviewed;

        // Los resultados anteriores al registro del modelo pueden tener respuestas en blanco.
        $legacyAnswers = $aiAnswersQuery->whereNull('ai_model_used')
            ->select(['id', 'answer_payload', 'teacher_recoteje_score'])
            ->lazyById();

        foreach ($legacyAnswers as $answer) {
            if ($answer->canBeReviewedQualitatively()) {
                $evaluated++;
                $reviewed += (int) ($answer->teacher_recoteje_score !== null);
            }
        }

        return [
            'creditSummary' => $this->subscriptionLimitService->getAiCreditQuotaSummary($teacher),
            'aiEvaluatedAnswersCount' => $evaluated,
            'aiReviewedAnswersCount' => $reviewed,
            'activeDriver' => (string) config('services.ai.driver', 'gemini'),
            'hasGeminiKey' => trim((string) config('services.gemini.api_key', '')) !== '',
        ];
    }

    /**
     * Solicita la evaluación (o reevaluación) con IA de una respuesta cualitativa
     * específica validando IDOR, estado entregado, omisión de vacías y saldo de créditos.
     *
     * @param User $teacher Docente propietario del examen.
     * @param Exam $exam Examen evaluado.
     * @param StudentAttempt $attempt Intento entregado por el estudiante.
     * @param StudentAnswer $answer Respuesta abierta o de programación objetivo.
     * @param bool $isReevaluation Indica si el docente solicitó explícitamente reevaluar.
     * @param bool $dismissWarning Persiste preferencia de no mostrar advertencia de reevaluación (RF-IAR-014).
     * @return array<string, mixed>
     *
     * @throws SubscriptionLimitExceededException
     * @throws ValidationException
     */
    public function requestEvaluationForAnswer(
        User $teacher,
        Exam $exam,
        StudentAttempt $attempt,
        StudentAnswer $answer,
        bool $isReevaluation = false,
        bool $dismissWarning = false,
    ): array {
        abort_unless((int) $exam->teacher_id === (int) $teacher->id, 403);
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);
        abort_unless((int) $answer->student_attempt_id === (int) $attempt->id, 404);

        if ($attempt->status !== 'submitted') {
            throw ValidationException::withMessages([
                'attempt' => ['Solo se pueden evaluar con IA los intentos en estado entregado'],
            ]);
        }

        $answer->loadMissing('examQuestion.question');
        $questionType = (string) ($answer->examQuestion?->question?->question_type ?? '');

        if (! in_array($questionType, ['open', 'code'], true)) {
            throw ValidationException::withMessages([
                'answer' => ['Solo los reactivos abiertos o de programación admiten evaluación con IA'],
            ]);
        }

        $studentText = StudentAnswer::qualitativeText($answer->answer_payload);

        if ($studentText === '') {
            $this->markEmptyAnswerWithoutAi($answer, $attempt);

            return [
                'status' => 'empty',
                'queued' => false,
                'consumed_credit' => false,
                'answer' => $answer->fresh(),
                'message' => 'La respuesta está en blanco; se asignó 0 sin consumir créditos de IA.',
            ];
        }

        $this->subscriptionLimitService->ensureCanConsumeAiCredits($teacher, 1);

        if ($dismissWarning && ! $teacher->skip_ai_reevaluation_warning) {
            $teacher->forceFill(['skip_ai_reevaluation_warning' => true])->save();
        }

        $effectiveIsReevaluation = $isReevaluation
            || ($answer->ai_evaluated_at !== null && ! (bool) $answer->ai_is_fallback && $answer->ai_model_used !== 'none');

        EvaluateStudentAnswerJob::dispatch((int) $answer->id, (int) $teacher->id, $effectiveIsReevaluation)
            ->afterCommit();

        return [
            'status' => 'dispatched',
            'queued' => config('queue.default') !== 'sync',
            'answer' => $answer->fresh(),
        ];
    }

    /**
     * Solicita la evaluación en lote de todas las respuestas cualitativas aún no
     * evaluadas de forma utilizable (sin evaluar o en modo fallback) de un intento.
     *
     * @param User $teacher Docente propietario del examen.
     * @param Exam $exam Examen evaluado.
     * @param StudentAttempt $attempt Intento entregado del estudiante.
     * @return array{dispatched: int, skipped_empty: int, queued: bool, fallback_count: int}
     *
     * @throws SubscriptionLimitExceededException
     * @throws ValidationException
     */
    public function requestBatchEvaluationForAttempt(User $teacher, Exam $exam, StudentAttempt $attempt): array
    {
        abort_unless((int) $exam->teacher_id === (int) $teacher->id, 403);
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);

        if ($attempt->status !== 'submitted') {
            throw ValidationException::withMessages([
                'attempt' => ['Solo se pueden evaluar con IA los intentos en estado entregado'],
            ]);
        }

        $candidates = $attempt->answers()
            ->with('examQuestion.question')
            ->whereHas('examQuestion.question', static function ($query): void {
                $query->whereIn('question_type', ['open', 'code']);
            })
            ->where(static function ($query): void {
                $query->whereNull('ai_evaluated_at')
                    ->orWhere('ai_is_fallback', true);
            })
            ->orderBy('id')
            ->get();

        $emptyCount = 0;
        $toEvaluate = collect();

        foreach ($candidates as $candidate) {
            $studentText = StudentAnswer::qualitativeText($candidate->answer_payload);

            if ($studentText === '') {
                $this->markEmptyAnswerWithoutAi($candidate, $attempt);
                $emptyCount++;
                continue;
            }

            $toEvaluate->push($candidate);
        }

        if ($toEvaluate->isNotEmpty()) {
            $this->subscriptionLimitService->ensureCanConsumeAiCredits($teacher, $toEvaluate->count());

            foreach ($toEvaluate as $answerToEvaluate) {
                EvaluateStudentAnswerJob::dispatch((int) $answerToEvaluate->id, (int) $teacher->id, false)
                    ->afterCommit();
            }
        }

        $isQueued = config('queue.default') !== 'sync';
        $fallbackCount = 0;

        if (! $isQueued && $toEvaluate->isNotEmpty()) {
            $fallbackCount = StudentAnswer::query()
                ->whereIn('id', $toEvaluate->pluck('id')->all())
                ->where('ai_is_fallback', true)
                ->count();
        }

        return [
            'dispatched' => $toEvaluate->count(),
            'skipped_empty' => $emptyCount,
            'queued' => $isQueued,
            'fallback_count' => $fallbackCount,
        ];
    }

    /**
     * Ejecuta la evaluación semántica contra el contrato de IA fuera de bloqueos SQL
     * y persiste el resultado sugerido junto con el consumo de crédito si no hubo fallback.
     *
     * @param int $studentAnswerId Identificador de la respuesta del estudiante.
     * @param int $teacherId Identificador del docente propietario.
     * @param bool $isReevaluation Indica si corresponde a una reevaluación.
     * @return StudentAnswer|null Respuesta actualizada o null si la entidad no es elegible.
     */
    public function executeEvaluation(int $studentAnswerId, int $teacherId, bool $isReevaluation = false): ?StudentAnswer
    {
        $answer = StudentAnswer::query()
            ->with(['examQuestion.question.questionable', 'examQuestion.exam', 'studentAttempt'])
            ->find($studentAnswerId);

        if ($answer === null || $answer->examQuestion === null || $answer->examQuestion->question === null) {
            return null;
        }

        $examQuestion = $answer->examQuestion;
        $exam = $examQuestion->exam;
        $question = $examQuestion->question;
        $questionable = $question->questionable;
        $attempt = $answer->studentAttempt;

        if ($exam === null || $attempt === null || $attempt->status !== 'submitted' || (int) $exam->teacher_id !== $teacherId) {
            return null;
        }

        if (! in_array($question->question_type, ['open', 'code'], true)) {
            return null;
        }

        $studentText = StudentAnswer::qualitativeText($answer->answer_payload);

        if ($studentText === '') {
            $this->markEmptyAnswerWithoutAi($answer, $attempt);

            return $answer->fresh();
        }

        // Contexto estrictamente pedagógico sin ningún dato personal (RNF-PRI-001 LFPDPPP).
        $context = [
            'question_type' => (string) $question->question_type,
            'stem' => trim((string) ($answer->served_stem_snapshot ?: $question->stem)),
            'points_weight' => (float) $examQuestion->points_weight,
        ];

        $referenceAnswer = '';

        if ($question->question_type === 'open' && $questionable !== null) {
            $referenceAnswer = (string) ($questionable->reference_answer ?? '');

            if (isset($questionable->evaluation_rubric) && trim((string) $questionable->evaluation_rubric) !== '') {
                $context['evaluation_rubric'] = trim((string) $questionable->evaluation_rubric);
            }
        } elseif ($question->question_type === 'code' && $questionable !== null) {
            $referenceAnswer = (string) ($questionable->reference_solution ?? '');
            $context['programming_language'] = (string) ($questionable->programming_language ?? '');
            $context['instructions'] = (string) ($questionable->instructions ?? '');
            $context['is_find_error'] = (bool) ($questionable->is_find_error ?? false);

            if (isset($questionable->starter_code) && trim((string) $questionable->starter_code) !== '') {
                $context['starter_code'] = (string) $questionable->starter_code;
            }

            // Criterios de evaluación de programación (RF-BAN-004, RF-IAR-003)
            if (isset($questionable->evaluation_rubric) && trim((string) $questionable->evaluation_rubric) !== '') {
                $context['evaluation_rubric'] = trim((string) $questionable->evaluation_rubric);
            }
        }

        if ($this->aiEvaluator instanceof GeminiAiEvaluator) {
            // Estos valores se usan para filtrar localmente y quedan fuera de la lista del prompt.
            $context['local_privacy_identifiers'] = $this->evaluationAccountIdentifiers($attempt->student, $exam->teacher);
        }

        // Llamada HTTP a IA fuera de DB::transaction() para no retener bloqueos SQL.
        $result = $this->aiEvaluator->evaluate($studentText, $referenceAnswer, $context);

        return DB::transaction(function () use (
            $answer,
            $examQuestion,
            $attempt,
            $teacherId,
            $isReevaluation,
            $result,
        ): ?StudentAnswer {
            $lockedTeacher = User::query()
                ->with('subscription.subscriptionPackage')
                ->whereKey($teacherId)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAttempt = StudentAttempt::query()->lockForUpdate()->find($attempt->id);
            if ($lockedAttempt === null || $lockedAttempt->status !== 'submitted') {
                return null;
            }

            $lockedAnswer = $lockedAttempt->answers()
                ->whereKey($answer->id)
                ->lockForUpdate()
                ->first();

            if ($lockedAnswer === null) {
                return null;
            }

            $isFallback = (bool) ($result['is_fallback'] ?? false);
            $modelUsed = (string) ($result['model'] ?? (config('services.gemini.model') ?: GeminiAiEvaluator::DEFAULT_MODEL));

            if (! $isFallback) {
                try {
                    $this->subscriptionLimitService->ensureCanConsumeAiCredits($lockedTeacher, 1);
                } catch (SubscriptionLimitExceededException $e) {
                    Log::warning('Evaluación con IA omitida por agotamiento concurrente de créditos.', [
                        'teacher_id' => $lockedTeacher->id,
                        'student_answer_id' => $lockedAnswer->id,
                        'error' => $e->getMessage(),
                    ]);

                    return $lockedAnswer;
                }
            }

            $similarityScore = max(0.0, min(100.0, round((float) ($result['similarity_score'] ?? 0.0), 2)));
            $meetsGoal = (bool) ($result['meets_goal'] ?? ($similarityScore >= 60.0));
            $feedback = (string) ($result['feedback'] ?? 'Evaluación semántica generada por IA.');
        $suggestedPoints = StudentAnswer::pointsForAiSimilarity($similarityScore, (float) $examQuestion->points_weight);

            $updateAttributes = [
                'ai_similarity_score' => $similarityScore,
                'ai_meets_goal' => $meetsGoal,
                'ai_feedback' => $feedback,
                'ai_evaluated_at' => now(),
                'ai_is_fallback' => $isFallback,
                'ai_model_used' => $modelUsed,
            ];

            if ($lockedAnswer->teacher_recoteje_score === null) {
                $updateAttributes['points_awarded'] = $suggestedPoints;
            }

            $lockedAnswer->update($updateAttributes);

            $this->recalculateAiEstimatedScore($lockedAttempt);

            // Regla RF-IAR-011: Solo se descuenta crédito y se audita consumo cuando la IA real respondió.
            if (! $isFallback) {
                AiCreditUsage::create([
                    'teacher_id' => $lockedTeacher->id,
                    'student_answer_id' => $lockedAnswer->id,
                    'credits_consumed' => 1,
                    'is_reevaluation' => $isReevaluation,
                    'model_used' => $modelUsed,
                    'status' => AiCreditUsage::STATUS_CONSUMED,
                ]);

                AuditLog::create([
                    'user_id' => $lockedTeacher->id,
                    'action' => $isReevaluation ? 'ai.answer_reevaluated' : 'ai.answer_evaluated',
                    'auditable_type' => StudentAnswer::class,
                    'auditable_id' => $lockedAnswer->id,
                    'new_values' => [
                        'student_attempt_id' => $attempt->id,
                        'exam_question_id' => $examQuestion->id,
                        'ai_similarity_score' => $similarityScore,
                        'ai_meets_goal' => $meetsGoal,
                        'suggested_points' => $suggestedPoints,
                        'model_used' => $modelUsed,
                        'is_reevaluation' => $isReevaluation,
                        'credits_consumed' => 1,
                    ],
                ]);
            }

            return $lockedAnswer->fresh();
        });
    }

    /**
     * Marca una respuesta cualitativa en blanco con puntaje 0 sin consumir IA (RF-EVA-008).
     *
     * @param StudentAnswer $answer Respuesta cualitativa vacía.
     * @param StudentAttempt $attempt Intento propietario de la respuesta.
     * @return void
     */
    private function markEmptyAnswerWithoutAi(StudentAnswer $answer, StudentAttempt $attempt): void
    {
        DB::transaction(function () use ($answer, $attempt): void {
            $lockedAttempt = StudentAttempt::query()->lockForUpdate()->find($attempt->id);
            $lockedAnswer = $lockedAttempt?->answers()->lockForUpdate()->find($answer->id);
            if ($lockedAnswer === null) {
                return;
            }

            $updateAttributes = [
                'ai_similarity_score' => 0.0,
                'ai_meets_goal' => false,
                'ai_feedback' => 'Respuesta en blanco; se asigna puntaje 0 sin consumo de IA.',
                'ai_evaluated_at' => now(),
                'ai_is_fallback' => false,
                'ai_model_used' => 'none',
            ];

            if ($lockedAnswer->teacher_recoteje_score === null) {
                $updateAttributes['points_awarded'] = 0.0;
                $updateAttributes['is_correct'] = false;
            }

            $lockedAnswer->update($updateAttributes);
            $this->recalculateAiEstimatedScore($lockedAttempt);
        });
    }

    /**
     * Recalcula exclusivamente ai_estimated_score en el intento manteniendo intactos
     * final_score y pending_ai_count hasta el recoteje docente (RF-EVA-009).
     *
     * @param StudentAttempt $attempt Intento cuyo puntaje estimado de IA se actualiza.
     * @return void
     */
    private function recalculateAiEstimatedScore(StudentAttempt $attempt): void
    {
        $attempt->load(['answers' => static fn ($answers) => $answers->whereNotNull('ai_similarity_score')->with('examQuestion.question')]);
        $attempt->update(['ai_estimated_score' => $attempt->aiSuggestedScore() ?? 0]);
    }

    /**
     * Recupera los identificadores conocidos para filtrarlos de la copia externa.
     *
     * @param User|null $student Cuenta que entregó la respuesta.
     * @param User|null $teacher Cuenta a cargo de la referencia.
     * @return array<int|string, string> Identificadores y su sustituto neutro.
     */
    private function evaluationAccountIdentifiers(?User $student, ?User $teacher): array
    {
        $identifiers = [];
        foreach ([$student, $teacher] as $account) {
            if ($account === null) {
                continue;
            }

            $fullName = trim($account->first_name.' '.$account->last_name);
            if ($account->first_name !== '' && $account->last_name !== '') {
                $identifiers[$fullName] = 'Persona';
            }
            $identifiers[$account->email] = 'persona@example.invalid';
            if (filled($account->phone)) {
                $identifiers[$account->phone] = '0000000000';
            }
        }

        return $identifiers;
    }
}
