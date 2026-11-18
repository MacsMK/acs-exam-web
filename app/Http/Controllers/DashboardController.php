<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        DashboardController.php
 * Ubicación:      app/Http/Controllers/DashboardController.php
 * Requerimiento:  RF-AUT-002, RF-REP-001, RW-02, ADR-057 (Panel Post-Login)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.2.0
 * Fecha Creación: 08/11/2026
 * Descripción:    Controlador del panel de resumen operativo post-login para
 *                 superadministradores, docentes verificados y alumnos con
 *                 redirección automática a acreditación para docentes pendientes.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Controlador del panel de resumen operativo post-login (Módulo 2, MPW-V2 §7.9).
 *
 * @package App\Http\Controllers
 */
class DashboardController extends Controller
{
    /**
     * Enruta al usuario autenticado a su panel según rol y estado de acreditación.
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
                return redirect()->route('teacher.subscription.index', ['onboarding' => 1]);
            }

            return $this->teacherDashboard($user);
        }

        return $this->studentDashboard($user);
    }

    /**
     * Métricas agregadas y colecciones operativas para el panel docente (§7.9).
     *
     * @param User $teacher Cuenta docente verificada.
     * @return View
     */
    private function teacherDashboard(User $teacher): View
    {
        $normalizedScoreSql = "CASE WHEN acs_exams.grading_scale = '0-100' THEN COALESCE(acs_student_attempts.final_score, acs_student_attempts.objective_score, 0) / 10.0 ELSE COALESCE(acs_student_attempts.final_score, acs_student_attempts.objective_score, 0) END";

        $attemptStats = StudentAttempt::query()
            ->join('acs_exams', 'acs_student_attempts.exam_id', '=', 'acs_exams.id')
            ->where('acs_student_attempts.status', 'submitted')
            ->where('acs_exams.teacher_id', $teacher->id)
            ->selectRaw("
                COUNT(*) as total_count,
                COUNT(CASE WHEN ({$normalizedScoreSql}) >= 6.0 THEN 1 END) as passed_count,
                COUNT(CASE WHEN ({$normalizedScoreSql}) < 6.0 THEN 1 END) as failed_count,
                AVG({$normalizedScoreSql}) as avg_score
            ")
            ->first();

        $totalAttempts = (int) ($attemptStats?->total_count ?? 0);
        $averageScore = $totalAttempts > 0 && $attemptStats?->avg_score !== null
            ? round((float) $attemptStats->avg_score, 1)
            : 0.0;

        $recentExams = $teacher->examsCreated()
            ->with(['groups'])
            ->withCount(['examQuestions', 'studentAttempts'])
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
            'passedCount' => (int) ($attemptStats?->passed_count ?? 0),
            'failedCount' => (int) ($attemptStats?->failed_count ?? 0),
            'averageScore' => $averageScore,
            'recentExams' => $recentExams,
            'recentGroups' => $recentGroups,
        ]);
    }

    /**
     * Métricas agregadas y colecciones operativas para el panel del alumno (§7.9).
     *
     * @param User $student Cuenta de alumno autenticada.
     * @return View
     */
    private function studentDashboard(User $student): View
    {
        $enrolledGroups = $student->groupsAsStudent()
            ->where('acs_groups.is_closed', false)
            ->with(['teacher'])
            ->get();

        $pendingExams = Exam::query()
            ->where('is_published', true)
            ->whereHas('groups', static fn ($query) => $query->where('is_closed', false)->whereHas('students', static fn ($q) => $q->where('acs_users.id', $student->id)))
            ->whereDoesntHave('studentAttempts', static fn ($query) => $query->where('student_id', $student->id)->where('status', 'submitted'))
            ->with(['groups', 'teacher'])
            ->withCount('examQuestions')
            ->latest()
            ->get();

        $recentAttempts = StudentAttempt::query()
            ->where('student_id', $student->id)
            ->whereHas('group', static fn ($query) => $query->where('is_closed', false))
            ->with(['exam.teacher', 'group'])
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard.student', [
            'groupsCount' => $enrolledGroups->count(),
            'enrolledGroups' => $enrolledGroups,
            'pendingExams' => $pendingExams,
            'recentAttempts' => $recentAttempts,
        ]);
    }
}
