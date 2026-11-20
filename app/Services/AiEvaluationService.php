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
 * Versión:        1.0.0
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
use App\Models\SubscriptionPackage;
use App\Models\User;
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
     * Compila el resumen integral de créditos, consumo histórico y configuración
     * del motor de IA para el tablero docente (/docente/creditos-ia).
     *
     * @param User $teacher Cuenta docente autenticada.
     * @return array{
     *     teacher: User,
     *     activePackage: ?SubscriptionPackage,
     *     creditSummary: array<string, mixed>,
     *     totalAiEvaluations: int,
     *     recotejadasCount: int,
     *     promedioSimilitud: float,
     *     activeDriver: string,
     *     activeModel: string,
     *     hasGeminiKey: bool,
     *     queueDriver: string,
     *     aiRequestsLimit: ?int
     * }
     */
    public function getTeacherAiCreditsOverview(User $teacher): array
    {
        $teacher->loadMissing('subscription.subscriptionPackage');

        try {
            $activePackage = $this->subscriptionLimitService->resolveActivePackage($teacher);
        } catch (SubscriptionLimitExceededException) {
            $activePackage = null;
        }

        $creditSummary = $this->subscriptionLimitService->getAiCreditQuotaSummary($teacher);

        $aiAnswersQuery = StudentAnswer::query()
            ->whereHas('examQuestion.exam', static function ($query) use ($teacher): void {
                $query->where('teacher_id', $teacher->id);
            })
            ->whereNotNull('ai_evaluated_at');

        $totalAiEvaluations = (clone $aiAnswersQuery)->count();
        $recotejadasCount = (clone $aiAnswersQuery)->whereNotNull('teacher_recoteje_score')->count();
        $rawAverage = (clone $aiAnswersQuery)->avg('ai_similarity_score');
        $promedioSimilitud = $rawAverage !== null ? round((float) $rawAverage, 1) : 0.0;

        return [
            'teacher' => $teacher,
            'activePackage' => $activePackage,
            'creditSummary' => $creditSummary,
            'totalAiEvaluations' => $totalAiEvaluations,
            'recotejadasCount' => $recotejadasCount,
            'promedioSimilitud' => $promedioSimilitud,
            'activeDriver' => (string) config('services.ai.driver', 'gemini'),
            'activeModel' => (string) config('services.gemini.model', 'gemini-2.5-flash'),
            'hasGeminiKey' => trim((string) config('services.gemini.api_key', '')) !== '',
            'queueDriver' => (string) config('queue.default', 'sync'),
            'aiRequestsLimit' => $creditSummary['limit'],
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

        $studentText = $this->extractStudentAnswerText($answer->answer_payload);

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
            $studentText = $this->extractStudentAnswerText($candidate->answer_payload);

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

        if ($exam === null || $attempt === null || (int) $exam->teacher_id !== $teacherId) {
            return null;
        }

        if (! in_array($question->question_type, ['open', 'code'], true)) {
            return null;
        }

        $studentText = $this->extractStudentAnswerText($answer->answer_payload);

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

            $lockedAnswer = StudentAnswer::query()
                ->whereKey($answer->id)
                ->lockForUpdate()
                ->first();

            if ($lockedAnswer === null) {
                return null;
            }

            $isFallback = (bool) ($result['is_fallback'] ?? false);
            $modelUsed = (string) ($result['model'] ?? config('services.gemini.model', 'gemini-2.5-flash'));

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
            $suggestedPoints = round(($similarityScore / 100.0) * (float) $examQuestion->points_weight, 2);

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

            $this->recalculateAiEstimatedScore($attempt);

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
            $updateAttributes = [
                'ai_similarity_score' => 0.0,
                'ai_meets_goal' => false,
                'ai_feedback' => 'Respuesta en blanco; se asigna puntaje 0 sin consumo de IA.',
                'ai_evaluated_at' => now(),
                'ai_is_fallback' => false,
                'ai_model_used' => 'none',
            ];

            if ($answer->teacher_recoteje_score === null) {
                $updateAttributes['points_awarded'] = 0.0;
                $updateAttributes['is_correct'] = false;
            }

            $answer->update($updateAttributes);
            $this->recalculateAiEstimatedScore($attempt);
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
        $aiEstimatedScore = (float) $attempt->answers()
            ->whereHas('examQuestion.question', static function ($query): void {
                $query->whereIn('question_type', ['open', 'code']);
            })
            ->whereNotNull('ai_similarity_score')
            ->sum('points_awarded');

        StudentAttempt::query()
            ->whereKey($attempt->id)
            ->update([
                'ai_estimated_score' => round($aiEstimatedScore, 2),
            ]);
    }

    /**
     * Extrae de forma segura el contenido textual o código fuente de answer_payload.
     *
     * @param mixed $payload Carga almacenada en acs_student_answers.answer_payload.
     * @return string Texto normalizado sin espacios extremos.
     */
    private function extractStudentAnswerText(mixed $payload): string
    {
        if (is_string($payload)) {
            return trim($payload);
        }

        if (is_array($payload)) {
            foreach (['text', 'code', 'answer', 'given_answer'] as $key) {
                if (isset($payload[$key]) && is_string($payload[$key])) {
                    return trim($payload[$key]);
                }
            }
        }

        return '';
    }
}
