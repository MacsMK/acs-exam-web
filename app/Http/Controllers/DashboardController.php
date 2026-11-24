<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        DashboardController.php
 * Ubicación:      app/Http/Controllers/DashboardController.php
 * Requerimiento:  RF-AUT-002, RF-REP-001, RF-SUS-008, RW-02, ADR-057, ADR-060
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 08/11/2026
 * Descripción:    Controlador del panel de resumen operativo post-login para
 *                 superadministradores, docentes verificados (con capa modal
 *                 bloqueante de selección de plan si requiere onboarding) y
 *                 alumnos.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Services\AttemptService;
use App\Services\StudentExamAvailability;
use App\Services\StudentResultService;
use App\Services\TeacherSubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Controlador del panel de resumen operativo post-login (Módulo 2, MPW-V3 §7.9).
 *
 * @package App\Http\Controllers
 */
class DashboardController extends Controller
{
    private const STUDENT_EXAM_PREVIEW_LIMIT = 3;

    private const STUDENT_ATTEMPT_PREVIEW_LIMIT = 5;

    /**
     * Servicios de onboarding docente y consulta académica del estudiante.
     */
    public function __construct(
        private readonly TeacherSubscriptionService $teacherSubscriptionService,
        private readonly StudentExamAvailability $studentExamAvailability,
        private readonly StudentResultService $studentResultService,
        private readonly AttemptService $attemptService,
    ) {
    }

    /**
     * Enruta al usuario autenticado a su panel según rol, acreditación y onboarding.
     *
     * @param Request $request Solicitud HTTP del usuario autenticado.
     * @return Response|RedirectResponse|View
     */
    public function __invoke(Request $request): Response|RedirectResponse|View
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role === 'super_admin') {
            return redirect()->route('admin.teachers.index');
        }

        if ($user->role === 'teacher') {
            if (! $user->isVerifiedTeacher()) {
                return redirect()->route('teacher.verification.status');
            }

            if ($user->needsPlanOnboarding()) {
                return view('dashboard.teacher', [
                    'groupsCount' => 0,
                    'questionsCount' => 0,
                    'examsCount' => 0,
                    'activeExamsCount' => 0,
                    'passedCount' => 0,
                    'failedCount' => 0,
                    'gradedCount' => 0,
                    'pendingCount' => 0,
                    'outOfScaleCount' => 0,
                    'averageScore' => null,
                    'recentExams' => collect(),
                    'recentGroups' => collect(),
                    'showPlanOnboardingOverlay' => true,
                    'onboardingOverlay' => $this->teacherSubscriptionService->getOnboardingOverlayData($user),
                ]);
            }

            return $this->teacherDashboard($user);
        }

        return $this->studentDashboard($user);
    }

    /**
     * Métricas agregadas y colecciones operativas para el panel docente (§7.9).
     *
     * @param User $teacher Cuenta docente verificada con onboarding completado.
     * @return View
     */
    private function teacherDashboard(User $teacher): View
    {
        $normalizedScoreSql = "CASE WHEN acs_exams.grading_scale = '0-100'
            THEN acs_student_attempts.final_score / 10.0
            ELSE acs_student_attempts.final_score END";
        $minimumSql = "CASE WHEN acs_groups.grading_scale = '0-100'
            THEN acs_groups.passing_score / 10.0
            ELSE COALESCE(acs_groups.passing_score, 6.0) END";
        $submittedScores = StudentAttempt::query()
            ->current()
            ->join('acs_exams', 'acs_student_attempts.exam_id', '=', 'acs_exams.id')
            ->leftJoin('acs_groups', 'acs_student_attempts.group_id', '=', 'acs_groups.id')
            ->where('acs_student_attempts.status', 'submitted')
            ->where('acs_exams.teacher_id', $teacher->id)
            ->selectRaw("{$normalizedScoreSql} as score, {$minimumSql} as passing_score");

        // Los intentos históricos pueden conservar puntos brutos fuera de la escala declarada.
        $attemptStats = DB::query()
            ->fromSub($submittedScores, 'submitted_scores')
            ->selectRaw('
                COUNT(CASE WHEN score BETWEEN 0 AND 10 THEN 1 END) as graded_count,
                COUNT(CASE WHEN score IS NULL THEN 1 END) as pending_count,
                COUNT(CASE WHEN score NOT BETWEEN 0 AND 10 THEN 1 END) as out_of_scale_count,
                COUNT(CASE WHEN score BETWEEN 0 AND 10 AND score >= passing_score THEN 1 END) as passed_count,
                COUNT(CASE WHEN score BETWEEN 0 AND 10 AND score < passing_score THEN 1 END) as failed_count,
                AVG(CASE WHEN score BETWEEN 0 AND 10 THEN score END) as avg_score
            ')
            ->first();

        $averageScore = $attemptStats?->avg_score !== null
            ? round((float) $attemptStats->avg_score, 1)
            : null;

        $recentExams = $teacher->examsCreated()
            ->with(['groups'])
            ->withCount([
                'examQuestions',
                'studentAttempts',
                'studentAttempts as submitted_attempts_count' => static function ($query): void {
                    $query->current()->where('status', 'submitted');
                },
            ])
            ->latest()
            ->limit(5)
            ->get();

        $recentGroups = $teacher->groupsAsTeacher()
            ->withCount('students')
            ->latest()
            ->limit(4)
            ->get();

        return view('dashboard.teacher', [
            'groupsCount' => $teacher->groupsAsTeacher()->count(),
            'questionsCount' => $teacher->questionsAuthored()->count(),
            'examsCount' => $teacher->examsCreated()->count(),
            'activeExamsCount' => Exam::query()
                ->forTeacherFilter((int) $teacher->id, ['status' => Exam::STATUS_ACTIVE])
                ->count(),
            'passedCount' => (int) ($attemptStats?->passed_count ?? 0),
            'failedCount' => (int) ($attemptStats?->failed_count ?? 0),
            'gradedCount' => (int) ($attemptStats?->graded_count ?? 0),
            'pendingCount' => (int) ($attemptStats?->pending_count ?? 0),
            'outOfScaleCount' => (int) ($attemptStats?->out_of_scale_count ?? 0),
            'averageScore' => $averageScore,
            'recentExams' => $recentExams,
            'recentGroups' => $recentGroups,
            'showPlanOnboardingOverlay' => false,
            'onboardingOverlay' => null,
        ]);
    }

    /**
     * Exámenes por ventana, intentos vigentes y grupos del estudiante (§7.9).
     *
     * @param User $student Cuenta de alumno autenticada.
     * @return View
     */
    private function studentDashboard(User $student): View
    {
        $enrolledGroups = $student->groupsAsStudent()
            ->where('acs_groups.is_closed', false)
            ->with(['teacher'])
            ->orderBy('acs_groups.name')
            ->orderBy('acs_groups.id')
            ->get();

        $examRows = $this->studentExamAvailability->forStudent($student);
        $activeExams = $examRows->whereIn('state', ['in_progress', 'active']);
        $scheduledExams = $examRows->where('state', 'scheduled');

        $recentAttempts = StudentAttempt::query()
            ->current()
            ->where('student_id', $student->id)
            ->inCurrentGroupsOfStudent($student->id)
            ->with(['exam', 'group'])
            ->latest()
            ->orderByDesc('id')
            ->limit(self::STUDENT_ATTEMPT_PREVIEW_LIMIT)
            ->get();

        $deadlines = $this->studentExamAvailability->deadlinesFor($recentAttempts);
        $now = now();

        $attemptRows = $recentAttempts->map(function (StudentAttempt $attempt) use ($deadlines, $now): array {
            $deadline = $deadlines->get($attempt->id);
            $awaitsClosure = $attempt->status === 'in_progress'
                && $this->attemptService->hasReachedDeadline($attempt, $deadline, $now);
            $isResumable = $attempt->status === 'in_progress' && ! $awaitsClosure;

            [$statusLabel, $statusVariant] = match (true) {
                $isResumable => ['En progreso', 'warning'],
                $awaitsClosure && $attempt->free_close_at !== null => ['Cerrado', 'neutral'],
                $awaitsClosure || $attempt->status === 'expired' => ['Expirado', 'neutral'],
                $attempt->status === 'cancelled_by_incident' => ['Cancelado', 'danger'],
                $attempt->status === 'submitted' => ['Finalizado', 'accent'],
                default => ['Registrado', 'neutral'],
            };

            $grade = $this->studentResultService->buildWebGrade($attempt, $isResumable);
            if ($awaitsClosure && $attempt->free_close_at !== null && $grade['status'] === 'unsubmitted') {
                $grade['status'] = 'pending';
                $grade['label'] = StudentResultService::PENDING_MESSAGE;
                $grade['variant'] = 'warning';
            }

            $hasSubmittedAt = $attempt->status === 'submitted' && $attempt->submitted_at !== null;

            return [
                'attempt' => $attempt,
                'statusLabel' => $statusLabel,
                'statusVariant' => $statusVariant,
                'grade' => $grade,
                'actionLabel' => $isResumable ? 'Continuar' : 'Ver detalles',
                'actionHref' => route($isResumable ? 'attempts.show' : 'attempts.result', $attempt),
                'timestamp' => $hasSubmittedAt ? $attempt->submitted_at : ($attempt->started_at ?? $attempt->created_at),
                'timestampLabel' => match (true) {
                    $hasSubmittedAt => 'Entrega',
                    $attempt->started_at !== null => 'Inicio',
                    default => 'Registro',
                },
            ];
        });

        return view('dashboard.student', [
            'enrolledGroups' => $enrolledGroups,
            'examRows' => $activeExams->take(self::STUDENT_EXAM_PREVIEW_LIMIT)
                ->concat($scheduledExams->take(self::STUDENT_EXAM_PREVIEW_LIMIT)),
            'activeExamCount' => $activeExams->where('state', 'active')->count(),
            'inProgressExamCount' => $activeExams->where('state', 'in_progress')->count(),
            'scheduledExamCount' => $scheduledExams->count(),
            'examTimezone' => Exam::timezoneLabel(),
            'attemptRows' => $attemptRows,
            'attemptLimit' => self::STUDENT_ATTEMPT_PREVIEW_LIMIT,
        ]);
    }
}
