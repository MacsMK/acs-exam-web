<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherReviewController.php
 * Ubicación:      app/Http/Controllers/TeacherReviewController.php
 * Requerimiento:  RF-IAR-001..014, RF-EVA-009, RF-EVA-013, RW-11, ADR-059
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 08/11/2026
 * Descripción:    Controlador delgado de revisión docente para invocar
 *                 evaluaciones de IA bajo demanda (individual y en lote),
 *                 recotejar y ajustar calificaciones con auditoría inmutable
 *                 (RF-EVA-013), recalcular pending_ai_count y normalizar escala.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Exceptions\SubscriptionLimitExceededException;
use App\Http\Requests\RequestAiEvaluationRequest;
use App\Http\Requests\TeacherReviewStoreRequest;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Services\AiEvaluationService;
use App\Services\AttemptService;
use App\Services\GradeCalculatorService;
use App\Services\SubscriptionLimitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DomainException;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de revisión docente, evaluación IA bajo demanda y recoteje definitivo.
 *
 * @package App\Http\Controllers
 */
class TeacherReviewController extends Controller
{
    /**
     * Inicializa el controlador con el repositorio de exámenes y servicios de dominio.
     *
     * @param ExamRepositoryInterface $examRepository Repositorio de exámenes e intentos.
     * @param GradeCalculatorService $gradeCalculator Normalizador de escala de calificaciones.
     * @param AiEvaluationService $aiEvaluationService Servicio de evaluación asistida por IA.
     * @param SubscriptionLimitService $subscriptionLimitService Gestor de cuotas y créditos IA.
     */
    public function __construct(
        private readonly ExamRepositoryInterface $examRepository,
        private readonly GradeCalculatorService $gradeCalculator,
        private readonly AiEvaluationService $aiEvaluationService,
        private readonly SubscriptionLimitService $subscriptionLimitService,
    ) {
    }

    /**
     * Panel concentrador de revisiones de exámenes para el docente.
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @return View
     */
    public function globalIndex(Request $request): View
    {
        $teacherId = (int) $request->user()->id;
        $filters = $request->only(['search', 'status']);

        $exams = $this->examRepository->paginateForTeacher($teacherId, 12, $filters);
        $exams->getCollection()->each(static function (Exam $exam): void {
            $exam->setAttribute('pending_reviews_count', (int) ($exam->pending_recoteje_count ?? 0));
        });

        $stats = $this->examRepository->getTeacherExamStats($teacherId);

        return view('teacher.reviews.index', [
            'exams' => $exams,
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    /**
     * Lista de intentos de examen presentados por los alumnos para revisión.
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @param Exam $exam Examen propiedad del docente.
     * @return View
     */
    public function index(Request $request, Exam $exam): View
    {
        abort_unless((int) $exam->teacher_id === (int) $request->user()->id, 403);

        $filters = $request->only(['search', 'status']);
        $attempts = $this->examRepository->paginateAttemptsForReview($exam, $filters, 15);

        // RF-EXA-052: número del intento más reciente de cada alumno para marcar
        // los reemplazados y ofrecer la reaplicación solo en el vigente.
        $latestAttemptNumbers = StudentAttempt::where('exam_id', $exam->id)
            ->groupBy('student_id')
            ->selectRaw('student_id, MAX(attempt_number) as latest_number')
            ->pluck('latest_number', 'student_id')
            ->map(static fn ($number): int => (int) $number);

        return view('exams.review-index', [
            'exam' => $exam,
            'attempts' => $attempts,
            'filters' => $filters,
            'latestAttemptNumbers' => $latestAttemptNumbers,
            'reapplicationOpen' => $exam->is_published && ($exam->ends_at === null || now()->lt($exam->ends_at)),
        ]);
    }

    /**
     * Autoriza un intento adicional individual para el alumno (RF-EXA-052),
     * conservando el intento anterior y sus resultados.
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @param Exam $exam Examen del intento.
     * @param StudentAttempt $attempt Intento vigente del alumno.
     * @param AttemptService $attemptService Servicio de intentos.
     * @return RedirectResponse
     */
    public function authorizeReapplication(
        Request $request,
        Exam $exam,
        StudentAttempt $attempt,
        AttemptService $attemptService,
    ): RedirectResponse {
        /** @var User $teacher */
        $teacher = $request->user();

        abort_unless((int) $exam->teacher_id === (int) $teacher->id, 403);
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);

        $attempt->setRelation('exam', $exam);

        try {
            $attemptService->authorizeReapplication($attempt, $teacher);
        } catch (DomainException $e) {
            return back()->withErrors(['reapplication' => $e->getMessage()]);
        }

        $studentName = $attempt->student?->full_name ?? 'el alumno';

        return back()->with('status', 'Reaplicación autorizada para ' . $studentName . '. Podrá presentar el examen de nuevo mientras siga abierto.');
    }

    /**
     * Vista de detalle del intento de un alumno con saldo de créditos IA,
     * desglose de evaluación asistida y formulario de recoteje definitivo.
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @param Exam $exam Examen evaluado.
     * @param StudentAttempt $attempt Intento del estudiante a revisar.
     * @return View
     */
    public function show(Request $request, Exam $exam, StudentAttempt $attempt): View
    {
        /** @var User $teacher */
        $teacher = $request->user();

        abort_unless((int) $exam->teacher_id === (int) $teacher->id, 403);
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);

        $loadedAttempt = $this->examRepository->findAttemptForReviewWithAnswers($exam, (int) $attempt->id);
        abort_if($loadedAttempt === null, 404);

        return view('exams.review-attempt', [
            'exam' => $exam,
            'attempt' => $loadedAttempt,
            'aiCreditSummary' => $this->subscriptionLimitService->getAiCreditQuotaSummary($teacher),
            'skipAiReevaluationWarning' => (bool) $teacher->skip_ai_reevaluation_warning,
        ]);
    }

    /**
     * Solicita la evaluación o reevaluación individual con IA de una respuesta
     * cualitativa específica dentro de un intento entregado (RF-IAR-001..014).
     *
     * @param RequestAiEvaluationRequest $request Solicitud validada del docente.
     * @param Exam $exam Examen propietario.
     * @param StudentAttempt $attempt Intento entregado.
     * @param StudentAnswer $answer Respuesta cualitativa objetivo.
     * @return RedirectResponse
     */
    public function evaluateWithAi(
        RequestAiEvaluationRequest $request,
        Exam $exam,
        StudentAttempt $attempt,
        StudentAnswer $answer,
    ): RedirectResponse {
        /** @var User $teacher */
        $teacher = $request->user();

        try {
            $result = $this->aiEvaluationService->requestEvaluationForAnswer(
                $teacher,
                $exam,
                $attempt,
                $answer,
                $request->boolean('is_reevaluation'),
                $request->boolean('dismiss_warning'),
            );
        } catch (SubscriptionLimitExceededException $e) {
            return redirect()
                ->route('exams.revision.show', [$exam, $attempt])
                ->withErrors(['ai_credits' => $e->getMessage()]);
        } catch (ValidationException $e) {
            return redirect()
                ->route('exams.revision.show', [$exam, $attempt])
                ->withErrors($e->errors());
        }

        if ($result['status'] === 'empty') {
            return redirect()
                ->route('exams.revision.show', [$exam, $attempt])
                ->with('status', (string) $result['message']);
        }

        if ((bool) ($result['queued'] ?? false)) {
            return redirect()
                ->route('exams.revision.show', [$exam, $attempt])
                ->with('status', 'Evaluación con IA enviada a la cola. Actualiza en unos segundos para consultar la sugerencia.');
        }

        /** @var StudentAnswer|null $freshAnswer */
        $freshAnswer = $result['answer'] ?? null;

        if ($freshAnswer !== null && $freshAnswer->ai_is_fallback) {
            return redirect()
                ->route('exams.revision.show', [$exam, $attempt])
                ->with('warning', 'El motor de IA no estuvo disponible y se aplicó una estimación local de contingencia. No se descontó ningún crédito de tu saldo.');
        }

        return redirect()
            ->route('exams.revision.show', [$exam, $attempt])
            ->with('status', 'Evaluación con IA completada. Revisa la sugerencia generada y guarda tu recoteje definitivo.');
    }

    /**
     * Solicita la evaluación en lote con IA de todas las respuestas cualitativas
     * pendientes o degradadas de un intento entregado (RF-IAR-001, RF-IAR-010).
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @param Exam $exam Examen propietario.
     * @param StudentAttempt $attempt Intento entregado.
     * @return RedirectResponse
     */
    public function evaluateAttemptWithAi(Request $request, Exam $exam, StudentAttempt $attempt): RedirectResponse
    {
        /** @var User $teacher */
        $teacher = $request->user();

        try {
            $result = $this->aiEvaluationService->requestBatchEvaluationForAttempt($teacher, $exam, $attempt);
        } catch (SubscriptionLimitExceededException $e) {
            return redirect()
                ->route('exams.revision.show', [$exam, $attempt])
                ->withErrors(['ai_credits' => $e->getMessage()]);
        } catch (ValidationException $e) {
            return redirect()
                ->route('exams.revision.show', [$exam, $attempt])
                ->withErrors($e->errors());
        }

        if ($result['dispatched'] === 0) {
            $message = $result['skipped_empty'] > 0
                ? "Se asignó puntaje 0 a {$result['skipped_empty']} respuesta(s) en blanco sin consumir créditos de IA; no hay reactivos pendientes por evaluar."
                : 'No hay reactivos cualitativos pendientes de evaluación con IA en este intento.';

            return redirect()
                ->route('exams.revision.show', [$exam, $attempt])
                ->with('status', $message);
        }

        if ($result['queued']) {
            return redirect()
                ->route('exams.revision.show', [$exam, $attempt])
                ->with('status', "Se encolaron {$result['dispatched']} reactivo(s) para evaluación con IA. Actualiza en unos segundos para ver los resultados.");
        }

        $fallbackCount = (int) ($result['fallback_count'] ?? 0);

        if ($fallbackCount > 0) {
            return redirect()
                ->route('exams.revision.show', [$exam, $attempt])
                ->with('warning', "Evaluación en lote finalizada ({$result['dispatched']} procesado(s); {$fallbackCount} en modo de contingencia local sin descuento de créditos).");
        }

        return redirect()
            ->route('exams.revision.show', [$exam, $attempt])
            ->with('status', "Evaluación en lote con IA completada para {$result['dispatched']} reactivo(s).");
    }

    /**
     * Persiste el recoteje docente sobre reactivos cualitativos, registra
     * auditoría inmutable (RF-EVA-013), recalcula pendientes y normaliza escala.
     *
     * @param TeacherReviewStoreRequest $request Solicitud validada de recoteje.
     * @param Exam $exam Examen evaluado.
     * @param StudentAttempt $attempt Intento entregado.
     * @return RedirectResponse
     */
    public function update(TeacherReviewStoreRequest $request, Exam $exam, StudentAttempt $attempt): RedirectResponse
    {
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);

        /** @var User $teacher */
        $teacher = $request->user();
        $reviews = $request->validated('reviews');
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent() !== null ? mb_substr($request->userAgent(), 0, 255) : null;

        DB::transaction(function () use ($teacher, $exam, $attempt, $reviews, $ipAddress, $userAgent): void {
            $lockedAttempt = StudentAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAttempt->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'attempt' => ['Solo se pueden recotejar intentos en estado entregado'],
                ]);
            }

            $answerIds = array_column($reviews, 'answer_id');
            $answers = $lockedAttempt->answers()
                ->with('examQuestion.question')
                ->whereIn('id', $answerIds)
                ->get()
                ->keyBy('id');

            foreach ($reviews as $review) {
                /** @var StudentAnswer|null $answer */
                $answer = $answers->get((int) $review['answer_id']);

                if ($answer === null) {
                    abort(404, 'La respuesta seleccionada no pertenece al intento evaluado.');
                }

                $questionType = (string) ($answer->examQuestion?->question?->question_type ?? '');
                if (! in_array($questionType, ['open', 'code'], true)) {
                    throw ValidationException::withMessages([
                        'reviews' => ['Solo puedes recotejar reactivos abiertos o de programación'],
                    ]);
                }

                $maxPoints = (float) ($answer->examQuestion?->points_weight ?? 0);
                $assignedScore = (float) $review['score'];

                if ($assignedScore < 0 || $assignedScore > $maxPoints) {
                    throw ValidationException::withMessages([
                        'reviews' => ["El puntaje asignado ({$assignedScore}) debe estar entre 0 y {$maxPoints}"],
                    ]);
                }

                $hadPreviousRecoteje = $answer->teacher_recoteje_score !== null;
                $oldValues = [
                    'teacher_recoteje_score' => $hadPreviousRecoteje ? (float) $answer->teacher_recoteje_score : null,
                    'teacher_recoteje_notes' => $answer->teacher_recoteje_notes,
                    'points_awarded' => (float) $answer->points_awarded,
                ];

                $notes = isset($review['notes']) && is_string($review['notes']) && trim($review['notes']) !== ''
                    ? trim($review['notes'])
                    : null;

                $answer->update([
                    'teacher_recoteje_score' => $assignedScore,
                    'teacher_recoteje_notes' => $notes,
                    'points_awarded' => $assignedScore,
                    'is_correct' => $assignedScore > 0,
                ]);

                // Bitácora normativa de revisión y corrección posterior de calificaciones (RF-EVA-013).
                AuditLog::create([
                    'user_id' => (int) $teacher->id,
                    'action' => $hadPreviousRecoteje ? 'teacher.grade_corrected' : 'teacher.grade_reviewed',
                    'auditable_type' => StudentAnswer::class,
                    'auditable_id' => $answer->id,
                    'old_values' => $oldValues,
                    'new_values' => [
                        'exam_id' => $exam->id,
                        'student_attempt_id' => $lockedAttempt->id,
                        'teacher_recoteje_score' => $assignedScore,
                        'teacher_recoteje_notes' => $notes,
                        'points_awarded' => $assignedScore,
                        'reason' => $notes,
                        'reviewed_at' => now()->toIso8601String(),
                    ],
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);
            }

            $pendingAiCount = $lockedAttempt->answers()
                ->whereNull('teacher_recoteje_score')
                ->where(static function ($query): void {
                    $query->whereNull('ai_model_used')
                        ->orWhere('ai_model_used', '!=', 'none');
                })
                ->whereHas('examQuestion.question', static function ($query): void {
                    $query->whereIn('question_type', ['open', 'code']);
                })
                ->count();

            $rawTotal = (float) $lockedAttempt->answers()->sum('points_awarded');
            $totalExamWeight = (float) $exam->examQuestions()->sum('points_weight');
            $finalScore = $pendingAiCount === 0
                ? $this->gradeCalculator->normalizeScoreForExam($rawTotal, $exam, $totalExamWeight)
                : null;

            $lockedAttempt->update([
                'final_score' => $finalScore,
                'pending_ai_count' => $pendingAiCount,
            ]);
        });

        return redirect()
            ->route('exams.revision.show', [$exam, $attempt])
            ->with('status', 'Recoteje docente guardado exitosamente. La calificación final ha sido actualizada.');
    }
}
