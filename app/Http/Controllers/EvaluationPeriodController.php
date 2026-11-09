<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     EvaluationPeriodController.php
 * Ubicación:   app/Http/Controllers/EvaluationPeriodController.php
 * Descripción: Gestión de periodos de evaluación y sábana de calificaciones
 *              (RF-CAL-001..005, RF-CAL-009, RF-CAL-012, RF-CAL-017,
 *               RF-CAL-021, RF-CAL-027, RW-28..31, MPW-V2 §5.2.1, §6.1).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\EvaluationPeriodStoreRequest;
use App\Http\Requests\GradebookSaveRequest;
use App\Models\EvaluationPeriod;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\StudentPeriodGrade;
use App\Services\GradeCalculatorService;
use App\Services\GradeSheetExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvaluationPeriodController extends Controller
{
    public function __construct(
        private readonly GradeCalculatorService $gradeCalculator,
        private readonly GradeSheetExportService $gradeSheetExportService,
    ) {
    }

    /**
     * Hub concentrador de periodos y sábanas de calificaciones por grupo.
     */
    public function hub(\Illuminate\Http\Request $request): View
    {
        $teacher = $request->user();

        $groups = Group::where('teacher_id', $teacher->id)
            ->withCount(['students', 'evaluationPeriods', 'exams'])
            ->with(['evaluationPeriods' => function ($query): void {
                $query->orderBy('starts_at');
            }])
            ->latest()
            ->paginate(12);

        return view('teacher.gradebook.hub', [
            'groups' => $groups,
        ]);
    }

    /**
     * Lista los periodos de evaluación configurados para un grupo escolar.
     */
    public function index(Group $group): View
    {
        $this->authorizeTeacher($group);

        $periods = $group->evaluationPeriods()
            ->withCount('studentGrades')
            ->orderBy('starts_at')
            ->get();

        return view('evaluation-periods.index', [
            'group' => $group,
            'periods' => $periods,
        ]);
    }

    /**
     * Registra un nuevo periodo de evaluación con su fórmula ponderada.
     */
    public function store(EvaluationPeriodStoreRequest $request, Group $group): RedirectResponse
    {
        $this->authorizeTeacher($group);

        $wExams = round(((float) $request->input('exams_weight')) / 100, 4);
        $wHw = round(((float) $request->input('homework_weight')) / 100, 4);
        $wProj = round(((float) $request->input('project_weight')) / 100, 4);
        $wOther = round(((float) $request->input('other_weight')) / 100, 4);

        $formula = "exams*{$wExams}+homework*{$wHw}+project*{$wProj}+other*{$wOther}";

        $group->evaluationPeriods()->create([
            'name' => (string) $request->input('name'),
            'formula' => $formula,
            'starts_at' => $request->input('starts_at'),
            'ends_at' => $request->input('ends_at'),
        ]);

        return redirect()
            ->route('groups.periods.index', $group)
            ->with('status', 'Periodo de evaluación creado correctamente.');
    }

    /**
     * Sábana de calificaciones del grupo para un periodo específico.
     */
    public function gradebook(Group $group, EvaluationPeriod $period): View
    {
        $this->authorizeTeacher($group);
        abort_unless($period->group_id === $group->id, 404);

        $students = $group->students()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $savedGrades = $period->studentGrades->keyBy('student_id');

        // Promedio de exámenes reales aplicados en este grupo para pre-llenado sugerido
        $examIds = $group->exams()->pluck('acs_exams.id');
        $examAverages = StudentAttempt::whereIn('exam_id', $examIds)
            ->where('group_id', $group->id)
            ->whereNotNull('final_score')
            ->groupBy('student_id')
            ->selectRaw('student_id, AVG(final_score) as avg_score')
            ->pluck('avg_score', 'student_id')
            ->map(fn ($score) => round((float) $score, 2));

        $weights = $this->parseFormulaWeights($period->formula);

        return view('evaluation-periods.gradebook', [
            'group' => $group,
            'period' => $period,
            'students' => $students,
            'savedGrades' => $savedGrades,
            'examAverages' => $examAverages,
            'weights' => $weights,
        ]);
    }

    /**
     * Guarda masivamente las calificaciones capturadas y recalcula la calificación final.
     */
    public function saveGradebook(GradebookSaveRequest $request, Group $group, EvaluationPeriod $period): RedirectResponse
    {
        $this->authorizeTeacher($group);
        abort_unless($period->group_id === $group->id, 404);

        DB::transaction(function () use ($request, $period): void {
            /** @var array<int, array<string, mixed>> $gradesData */
            $gradesData = $request->input('grades', []);

            foreach ($gradesData as $row) {
                $studentId = (int) $row['student_id'];
                $exams = isset($row['exams_component']) && $row['exams_component'] !== '' ? (float) $row['exams_component'] : null;
                $hw = isset($row['homework_component']) && $row['homework_component'] !== '' ? (float) $row['homework_component'] : null;
                $proj = isset($row['project_component']) && $row['project_component'] !== '' ? (float) $row['project_component'] : null;
                $other = isset($row['other_component']) && $row['other_component'] !== '' ? (float) $row['other_component'] : null;

                $finalGrade = null;
                // Si al menos un componente fue capturado, aplicamos la fórmula aritmética segura
                if ($exams !== null || $hw !== null || $proj !== null || $other !== null) {
                    $components = [
                        'exams' => $exams ?? 0.0,
                        'homework' => $hw ?? 0.0,
                        'project' => $proj ?? 0.0,
                        'other' => $other ?? 0.0,
                    ];
                    $finalGrade = $this->gradeCalculator->calculate($period->formula, $components);
                }

                StudentPeriodGrade::updateOrCreate(
                    [
                        'evaluation_period_id' => $period->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'exams_component' => $exams,
                        'homework_component' => $hw,
                        'project_component' => $proj,
                        'other_component' => $other,
                        'final_grade' => $finalGrade,
                    ],
                );
            }
        });

        return redirect()
            ->route('groups.periods.gradebook', [$group, $period])
            ->with('status', 'Sábana de calificaciones guardada y promedios recalculados con éxito.');
    }

    /**
     * Exporta la sábana de calificaciones a formato ofimático Excel (.xlsx) (RF-REP-014, RF-REP-015, RW-38).
     */
    public function exportGradebook(Group $group, EvaluationPeriod $period): StreamedResponse
    {
        $this->authorizeTeacher($group);
        abort_unless($period->group_id === $group->id, 404);

        return $this->gradeSheetExportService->exportPeriodToXlsx($group, $period, request()->user());
    }

    /**
     * Extrae los porcentajes legibles de una fórmula (ej. exams*0.6+homework*0.4 -> 60%, 40%).
     *
     * @return array<string, float>
     */
    private function parseFormulaWeights(string $formula): array
    {
        $weights = [
            'exams' => 0.0,
            'homework' => 0.0,
            'project' => 0.0,
            'other' => 0.0,
        ];

        foreach (explode('+', str_replace(' ', '', $formula)) as $term) {
            if ($term === '') {
                continue;
            }

            [$component, $weight] = array_pad(explode('*', $term), 2, '0');
            if (isset($weights[$component])) {
                $weights[$component] = round(((float) $weight) * 100, 1);
            }
        }

        return $weights;
    }

    /**
     * Valida la propiedad del grupo frente a ataques IDOR (RNF-SEG-004, MPW-V2 §5.2.1).
     */
    private function authorizeTeacher(Group $group): void
    {
        abort_unless($group->teacher_id === request()->user()->id, 403);
    }
}
