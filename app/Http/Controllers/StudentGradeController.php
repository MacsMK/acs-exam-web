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
use App\Services\GroupGradeResults;
use App\Services\StudentResultService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentGradeController extends Controller
{
    public function __construct(
        private readonly GroupGradeResults $results,
        private readonly StudentResultService $studentResults,
    ) {
    }

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

        foreach ($groups as $group) {
            $this->applyResults($group, $student->id);
        }
        $recentAttempts = $this->submittedAttempts($student->id)
            ->inCurrentGroupsOfStudent($student->id)
            ->with(['exam', 'group.teacher'])
            ->get();

        $attemptGrades = $recentAttempts->mapWithKeys(fn (StudentAttempt $attempt): array => [
            $attempt->id => $this->studentResults->buildWebGrade($attempt),
        ]);
        $published = $attemptGrades->where('status', 'published');
        $withinScale = $published->whereNotNull('isPassed');
        $average = $withinScale->avg(static fn (array $grade): float => $grade['score'] * 10 / $grade['scaleMax']);

        return view('student.grades.index', [
            'groups' => $groups,
            'notices' => DB::table('acs_grade_notices')->where('student_id', $student->id)
                ->orderByDesc('id')->paginate(15, ['new_grade'], 'avisos'),
            'recentAttempts' => $recentAttempts,
            'attemptGrades' => $attemptGrades,
            'averageScore' => $average !== null ? round($average, 1) : null,
            'passedCount' => $withinScale->whereStrict('isPassed', true)->count(),
            'totalEvaluations' => $published->count(),
            'outOfScaleCount' => $published->count() - $withinScale->count(),
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

        $groupAttempts = $this->submittedAttempts($student->id)
            ->where('group_id', $group->id)
            ->with('exam')
            ->get();
        $attemptGrades = $groupAttempts->mapWithKeys(fn (StudentAttempt $attempt): array => [
            $attempt->id => $this->studentResults->buildWebGrade($attempt->setRelation('group', $group)),
        ]);

        $final = $this->applyResults($group, $student->id);

        return view('student.grades.show', [
            'group' => $group,
            'groupAttempts' => $groupAttempts,
            'attemptGrades' => $attemptGrades,
            'final' => $final,
        ]);
    }

    /** @return Builder<StudentAttempt> */
    private function submittedAttempts(int $studentId): Builder
    {
        return StudentAttempt::query()
            ->current()
            ->where('student_id', $studentId)
            ->where('status', 'submitted')
            ->whereHas('exam', static fn ($query) => $query->where('result_visibility_mode', '!=', 'hidden'))
            ->latest('submitted_at')
            ->orderByDesc('id');
    }

    /**
     * Carga las notas consultables del estudiante y su final grupal.
     *
     * @param Group $group Grupo inscrito y visible.
     * @param int $studentId Identificador del estudiante.
     * @return string|null Final exacta, o nulo si sigue pendiente.
     */
    private function applyResults(Group $group, int $studentId): ?string
    {
        $results = $this->results->calculate($group, $studentId, publishedOnly: true);
        foreach ($group->evaluationPeriods as $period) {
            $row = $results['rows'][$period->id][$studentId] ?? null;
            $period->setRelation('studentGrades', collect($row ? [$row] : []));
        }

        return $results['finals'][$studentId] ?? null;
    }
}
