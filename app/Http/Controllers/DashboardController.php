<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     DashboardController.php
 * Ubicación:   app/Http/Controllers/DashboardController.php
 * Descripción: Panel de resumen operativo post-login (Módulo 2, MPW-V2 §7.9).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role === 'teacher') {
            return $this->teacherDashboard($user);
        }

        return $this->studentDashboard($user);
    }

    /**
     * Métricas agregadas y colecciones operativas para el panel docente (§7.9).
     */
    private function teacherDashboard(User $teacher): View
    {
        $attemptStats = StudentAttempt::query()
            ->where('status', 'submitted')
            ->whereHas('exam', static fn ($query) => $query->where('teacher_id', $teacher->id))
            ->selectRaw('
                COUNT(*) as total_count,
                COUNT(CASE WHEN COALESCE(final_score, objective_score, 0) >= 6.0 THEN 1 END) as passed_count,
                COUNT(CASE WHEN COALESCE(final_score, objective_score, 0) < 6.0 THEN 1 END) as failed_count,
                AVG(COALESCE(final_score, objective_score, 0)) as avg_score
            ')
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
     */
    private function studentDashboard(User $student): View
    {
        $enrolledGroups = $student->groupsAsStudent()
            ->with(['teacher'])
            ->get();

        $pendingExams = Exam::query()
            ->where('is_published', true)
            ->whereHas('groups.students', static fn ($query) => $query->where('acs_users.id', $student->id))
            ->whereDoesntHave('studentAttempts', static fn ($query) => $query->where('student_id', $student->id)->where('status', 'submitted'))
            ->with(['groups', 'teacher'])
            ->withCount('examQuestions')
            ->latest()
            ->get();

        $recentAttempts = StudentAttempt::query()
            ->where('student_id', $student->id)
            ->with(['exam.teacher', 'group'])
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard.student', [
            'groupsCount' => $student->groupsAsStudent()->count(),
            'enrolledGroups' => $enrolledGroups,
            'pendingExams' => $pendingExams,
            'recentAttempts' => $recentAttempts,
        ]);
    }
}
