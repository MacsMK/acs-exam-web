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
use App\Models\Group;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Services\AiEvaluationService;
use App\Services\AttemptService;
use App\Services\GradeCalculatorService;
use App\Services\GroupGradeResults;
use App\Services\SubscriptionLimitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    private const CENTER_STATUSES = ['all', 'pending_review', 'draft', 'scheduled', 'active', 'closed'];

    private const ATTEMPT_STATUSES = ['all', 'pending_recoteje', 'graded', 'cancelled'];

    /**
     * Inicializa el controlador con el repositorio de exámenes y servicios de dominio.
     *
     * @param ExamRepositoryInterface $examRepository Repositorio de exámenes e intentos.
     * @param GradeCalculatorService $gradeCalculator Normalizador de escala de calificaciones.
     * @param GroupGradeResults $groupResults Calculador de resultados publicados del estudiante.
     * @param AiEvaluationService $aiEvaluationService Servicio de evaluación asistida por IA.
     * @param SubscriptionLimitService $subscriptionLimitService Gestor de cuotas y créditos IA.
     */
    public function __construct(
        private readonly ExamRepositoryInterface $examRepository,
        private readonly GradeCalculatorService $gradeCalculator,
        private readonly GroupGradeResults $groupResults,
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
    public function globalIndex(Request $request): View|Response|RedirectResponse
    {
        $teacherId = (int) $request->user()->id;
        $filters = $this->listQuery($request, self::CENTER_STATUSES);
        $exams = $this->examRepository->paginateForTeacher($teacherId, 12, $filters, pendingFirst: true);

        if ($exams->currentPage() > $exams->lastPage()) {
            return redirect()->route('teacher.reviews.index', array_replace($filters, ['page' => $exams->lastPage()]));
        }

        $view = view('teacher.reviews.index', [
            'exams' => $exams,
            'stats' => $this->examRepository->getTeacherExamStats($teacherId, $filters),
            'filters' => $filters,
        ]);

        return $this->catalogResponse($request, $view, 'teacher-review-exam-results');
    }

    /**
     * Lista de intentos de examen presentados por los alumnos para revisión.
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @param Exam $exam Examen propiedad del docente.
     * @return View
     */
    public function index(Request $request, Exam $exam): View|Response|RedirectResponse
    {
        abort_unless((int) $exam->teacher_id === (int) $request->user()->id, 403);

        $filters = $this->listQuery($request, self::ATTEMPT_STATUSES);
        $reviewGroup = null;
        if ($request->query->has('group_id')) {
            $groupId = filter_var($request->query('group_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_if($groupId === false, 404);
            $reviewGroup = Group::query()
                ->whereKey($groupId)
                ->where('teacher_id', $exam->teacher_id)
                ->where(static fn ($query) => $query
                    ->whereHas('exams', static fn ($exams) => $exams->whereKey($exam->id))
                    ->orWhereHas('studentAttempts', static fn ($attempts) => $attempts->where('exam_id', $exam->id)))
                ->firstOrFail();
            $filters['group_id'] = $reviewGroup->id;
        }
        $returnListQuery = $filters + $this->centerContext($request);
        $attempts = $this->examRepository->paginateAttemptsForReview($exam, $filters, 15);

        if ($attempts->currentPage() > $attempts->lastPage()) {
            return redirect()->route('exams.revision.index', [$exam, ...array_replace($returnListQuery, ['page' => $attempts->lastPage()])]);
        }

        // RF-EXA-052: número del intento más reciente de cada alumno para marcar
        // los reemplazados y ofrecer la reaplicación solo en el vigente.
        $latestAttemptNumbers = StudentAttempt::where('exam_id', $exam->id)
            ->groupBy('student_id')
            ->selectRaw('student_id, MAX(attempt_number) as latest_number')
            ->pluck('latest_number', 'student_id')
            ->map(static fn ($number): int => (int) $number);

        $view = view('exams.review-index', [
            'exam' => $exam,
            'attempts' => $attempts,
            'filters' => $filters,
            'reviewGroup' => $reviewGroup,
            'reviewStats' => $this->examRepository->getAttemptReviewStats($exam, $filters),
            'returnListQuery' => $returnListQuery,
            'reviewBackUrl' => $reviewGroup ? route('groups.show', $reviewGroup) . '#exams' : $this->centerUrl($request),
            'latestAttemptNumbers' => $latestAttemptNumbers,
            'reapplicationOpen' => $exam->is_published && ($exam->ends_at === null || now()->lt($exam->ends_at)),
        ]);

        return $this->catalogResponse($request, $view, 'exam-review-attempt-results');
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
        $redirect = redirect()->route('exams.revision.index', [$exam, ...$this->reviewContext($request, $attempt)]);

        try {
            $attemptService->authorizeReapplication($attempt, $teacher);
        } catch (DomainException $e) {
            return $redirect->withErrors(['reapplication' => $e->getMessage()]);
        }

        $studentName = $attempt->student?->full_name ?? 'el alumno';

        return $redirect->with('status', 'Reaplicación autorizada para ' . $studentName . '. Podrá presentar el examen de nuevo mientras siga abierto.');
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
        $context = $this->reviewContext($request, $attempt);

        return view('exams.review-attempt', [
            'exam' => $exam,
            'attempt' => $loadedAttempt,
            'reviewContext' => $context,
            'reviewBackUrl' => route('exams.revision.index', [$exam, ...$context]),
            'reviewCenterUrl' => $this->centerUrl($request),
            'aiSuggestedScore' => $loadedAttempt->aiSuggestedScore(),
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
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);
        $redirect = $this->aiReviewRedirect($request, $exam, $attempt);

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
            return $redirect->withErrors(['ai_credits' => $e->getMessage()]);
        } catch (ValidationException $e) {
            return $redirect->withErrors($e->errors());
        }

        if ($result['status'] === 'empty') {
            return $redirect->with('status', (string) $result['message']);
        }

        if ((bool) ($result['queued'] ?? false)) {
            return $redirect->with('review_ai_queued', true)
                ->with('status', 'Evaluación con IA en proceso. Actualiza en unos segundos para consultar la sugerencia.');
        }

        /** @var StudentAnswer|null $freshAnswer */
        $freshAnswer = $result['answer'] ?? null;

        if ($freshAnswer !== null && $freshAnswer->ai_is_fallback) {
            return $redirect->with('warning', 'La IA no estuvo disponible. Consulta la estimación de respaldo y revisa el puntaje. No se descontaron créditos.');
        }

        return $redirect->with('status', 'Evaluación con IA completada. Revisa la sugerencia y guarda tu revisión.');
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
        abort_unless((int) $exam->teacher_id === (int) $teacher->id, 403);
        abort_unless((int) $attempt->exam_id === (int) $exam->id, 404);
        $redirect = $this->aiReviewRedirect($request, $exam, $attempt);

        try {
            $result = $this->aiEvaluationService->requestBatchEvaluationForAttempt($teacher, $exam, $attempt);
        } catch (SubscriptionLimitExceededException $e) {
            return $redirect->withErrors(['ai_credits' => $e->getMessage()]);
        } catch (ValidationException $e) {
            return $redirect->withErrors($e->errors());
        }

        if ($result['dispatched'] === 0) {
            $message = $result['skipped_empty'] > 0
                ? "Se asignó puntaje 0 a {$result['skipped_empty']} respuesta(s) en blanco sin consumir créditos de IA; no hay reactivos pendientes por evaluar."
                : 'No hay reactivos cualitativos pendientes de evaluación con IA en este intento.';

            return $redirect->with('status', $message);
        }

        $count = $result['dispatched'];
        $processed = $count === 1 ? '1 respuesta' : "{$count} respuestas";

        if ($result['queued']) {
            return $redirect->with('review_ai_queued', true)
                ->with('status', "Evaluación con IA en proceso para {$processed}. Actualiza en unos segundos para consultar los resultados.");
        }

        $fallbackCount = (int) ($result['fallback_count'] ?? 0);

        if ($fallbackCount > 0) {
            return $redirect->with('warning', "Evaluación finalizada para {$processed}. Las estimaciones de respaldo ({$fallbackCount}) no consumieron créditos.");
        }

        return $redirect->with('status', "Evaluación con IA completada para {$processed}.");
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

        $hasFinalScore = DB::transaction(function () use ($teacher, $exam, $attempt, $reviews, $ipAddress, $userAgent): bool {
            $exam = Exam::whereKey($exam->id)->sharedLock()->firstOrFail();
            $group = Group::whereKey($attempt->group_id)->lockForUpdate()->first();
            $calculator = $this->groupResults;
            $visible = $group && ! $group->is_closed && $exam->result_visibility_mode !== 'hidden'
                && StudentAttempt::current()->whereKey($attempt->id)->exists()
                && $group->students()->where('acs_users.id', $attempt->student_id)->exists();
            $before = $visible ? $calculator->calculate($group, $attempt->student_id, true)['published'] : [];
            $lockedAttempt = StudentAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAttempt->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'attempt' => ['Solo se pueden revisar intentos entregados'],
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
                        'reviews' => ['Solo puedes revisar reactivos abiertos o de programación'],
                    ]);
                }

                if (! $answer->canBeReviewedQualitatively()) {
                    throw ValidationException::withMessages(['reviews' => ['La respuesta en blanco conserva cero puntos']]);
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

            $previousScore = $lockedAttempt->final_score;
            $lockedAttempt->update([
                'final_score' => $finalScore,
                'pending_ai_count' => $pendingAiCount,
            ]);
            if ($visible) {
                $after = $calculator->calculate($group, $attempt->student_id, true)['published'];
                $before[$attempt->student_id]['attempt'] = $previousScore;
                $after[$attempt->student_id]['attempt'] = $finalScore;
                $calculator->notifyChanges($before, $after);
            }

            return $finalScore !== null;
        });

        return redirect()
            ->route('exams.revision.show', [$exam, $attempt, ...$this->reviewContext($request, $attempt)])
            ->with('status', $hasFinalScore
                ? 'Revisión guardada. Se actualizó la calificación final.'
                : 'Revisión guardada. La calificación final sigue pendiente.');
    }

    private function catalogResponse(Request $request, View $view, string $fragment): View|Response
    {
        return $request->header('X-Catalog-Fragment') === $fragment
            ? response($view->fragment($fragment))->header('Cache-Control', 'no-store')
            : $view;
    }

    private function listQuery(Request $request, array $statuses, string $prefix = ''): array
    {
        $search = $request->query($prefix . 'search');
        $status = $request->query($prefix . 'status');
        $page = filter_var($request->query($prefix . 'page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return array_filter([
            'search' => is_string($search) && trim($search) !== '' ? trim($search) : null,
            'status' => in_array($status, $statuses, true) ? $status : null,
            'page' => $page === false ? null : $page,
        ], static fn ($value) => $value !== null);
    }

    private function centerContext(Request $request): array
    {
        $context = [];
        foreach ($this->listQuery($request, self::CENTER_STATUSES, 'center_') as $key => $value) {
            $context['center_' . $key] = $value;
        }

        return $context;
    }

    private function centerUrl(Request $request): string
    {
        return route('teacher.reviews.index', $this->listQuery($request, self::CENTER_STATUSES, 'center_'));
    }

    private function reviewContext(Request $request, StudentAttempt $attempt): array
    {
        $context = $this->listQuery($request, self::ATTEMPT_STATUSES) + $this->centerContext($request);
        $groupId = filter_var($request->query('group_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($groupId === (int) $attempt->group_id) {
            $context['group_id'] = $groupId;
        }

        return $context;
    }

    private function aiReviewRedirect(Request $request, Exam $exam, StudentAttempt $attempt): RedirectResponse
    {
        $redirect = redirect()->route('exams.revision.show', [$exam, $attempt, ...$this->reviewContext($request, $attempt)]);
        $draft = $request->input('review_draft');
        if (! is_array($draft) || $draft === [] || $attempt->status !== 'submitted') {
            return $redirect;
        }

        $answerIds = array_filter(array_keys($draft), static fn ($id) => is_int($id) && $id > 0);
        $answers = $attempt->answers()->whereKey($answerIds)
            ->whereHas('examQuestion.question', static fn ($question) => $question->whereIn('question_type', ['open', 'code']))
            ->get();
        $validatedDraft = [];

        foreach ($answers as $answer) {
            $fields = $draft[$answer->id];
            if (! is_array($fields) || ! $answer->canBeReviewedQualitatively()) {
                continue;
            }

            foreach (['score' => 32, 'notes' => 1000] as $field => $maxLength) {
                if (array_key_exists($field, $fields) && $fields[$field] === null) {
                    $validatedDraft[$answer->id][$field] = '';
                } elseif (is_string($fields[$field] ?? null) && mb_strlen($fields[$field]) <= $maxLength) {
                    $validatedDraft[$answer->id][$field] = $fields[$field];
                }
            }
        }

        return $redirect->withInput(['review_draft' => $validatedDraft]);
    }
}
