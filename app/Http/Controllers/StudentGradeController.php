<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentGradeController.php
 * Ubicación:   app/Http/Controllers/StudentGradeController.php
 * Descripción: Consulta de calificaciones del estudiante: sábanas por periodo
 *              (RF-REP-007 / P-174), calificación final ponderada (RF-REP-008
 *              / P-175) y estadísticas personales (RF-REP-012 / P-180).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\StudentAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StudentGradeController extends Controller
{
    /**
     * Resumen general de asignaturas, evaluaciones e historial de notas publicadas.
     */
    public function index(Request $request): View
    {
        $student = $request->user();

        $groups = $student->groupsAsStudent()
            ->where('acs_groups.is_closed', false)
            ->with([
                'teacher',
                'evaluationPeriods' => static fn ($query) => $query->with([
                    'studentGrades' => static fn ($sq) => $sq->where('student_id', $student->id),
                ]),
            ])
            ->get();

        $recentAttempts = StudentAttempt::query()
            ->where('student_id', $student->id)
            ->where('status', 'submitted')
            ->whereNotNull('final_score')
            ->whereHas('group', static fn ($query) => $query->where('is_closed', false))
            ->with(['exam', 'group.teacher'])
            ->latest('submitted_at')
            ->get();

        $averageScore = $recentAttempts->count() > 0
            ? round((float) $recentAttempts->avg('final_score'), 1)
            : 0.0;

        $passedCount = $recentAttempts->filter(static fn ($a) => (float) $a->final_score >= 6.0)->count();

        return view('student.grades.index', [
            'groups' => $groups,
            'recentAttempts' => $recentAttempts,
            'averageScore' => $averageScore,
            'passedCount' => $passedCount,
            'totalEvaluations' => $recentAttempts->count(),
        ]);
    }

    /**
     * Desglose detallado de calificaciones y rubros por periodo para una asignatura específica.
     */
    public function show(Request $request, Group $group): View
    {
        $student = $request->user();

        abort_unless(
            !$group->is_closed && $group->students()->where('acs_users.id', $student->id)->exists(),
            403,
            'No tienes acceso a las calificaciones de este grupo.'
        );

        $group->loadMissing([
            'teacher',
            'evaluationPeriods' => static fn ($query) => $query->with([
                'studentGrades' => static fn ($sq) => $sq->where('student_id', $student->id),
            ]),
        ]);

        $groupAttempts = StudentAttempt::query()
            ->where('student_id', $student->id)
            ->where('group_id', $group->id)
            ->where('status', 'submitted')
            ->whereNotNull('final_score')
            ->with(['exam'])
            ->latest('submitted_at')
            ->get();

        return view('student.grades.show', [
            'group' => $group,
            'groupAttempts' => $groupAttempts,
        ]);
    }
}
