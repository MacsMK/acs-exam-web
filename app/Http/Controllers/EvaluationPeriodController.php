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
 *               RF-CAL-021, RF-CAL-027, RW-28..31, MPW-V3 §5.2.1, §6.1).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\EvaluationPeriodStoreRequest;
use App\Http\Requests\EvaluationPeriodUpdateRequest;
use App\Http\Requests\GradebookSaveRequest;
use App\Models\EvaluationPeriod;
use App\Models\Exam;
use App\Models\Group;
use App\Services\GradePlanService;
use App\Services\GroupGradeResults;
use App\Services\GradebookEntryService;
use App\Services\GradeSheetExportService;
use App\Support\GradebookCatalogContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Normalizer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvaluationPeriodController extends Controller
{
    public function __construct(
        private readonly GradebookEntryService $entryService,
        private readonly GroupGradeResults $results,
        private readonly GradeSheetExportService $gradeSheetExportService,
    ) {
    }

    /**
     * Hub concentrador de periodos y sábanas de calificaciones por grupo.
     */
    public function hub(Request $request): View|Response|RedirectResponse
    {
        $teacherId = (int) $request->user()->id;
        $filters = GradebookCatalogContext::filters($request);
        $teacherGroups = Group::forTeacher($teacherId);
        $matchingGroups = clone $teacherGroups;

        $this->filterGradebookGroups($matchingGroups, $filters);

        $groups = (clone $matchingGroups)
            ->withCount(['students', 'evaluationPeriods', 'exams'])
            ->with(['evaluationPeriods' => fn ($query) => $query->orderBy('starts_at')->orderBy('id')])
            ->latest()
            ->orderByDesc('id')
            ->paginate(12)
            ->appends(GradebookCatalogContext::query($filters));

        if ($groups->currentPage() > $groups->lastPage()) {
            return to_route('teacher.gradebook.hub', GradebookCatalogContext::query($filters, $groups->lastPage()));
        }

        $matchingGroupIds = $matchingGroups->select('acs_groups.id')->toBase();
        $stats = [
            'periods' => EvaluationPeriod::whereIn('group_id', $matchingGroupIds)->count(),
            'examAssignments' => DB::table('acs_exam_group')->whereIn('group_id', $matchingGroupIds)
                ->whereIn('exam_id', Exam::forTeacher($teacherId)->select('acs_exams.id'))->count(),
        ];

        $view = view('teacher.gradebook.hub', [
            'groups' => $groups,
            'hasGroups' => $groups->total() > 0 || $teacherGroups->exists(),
            'filters' => $filters,
            'subjects' => $teacherGroups->whereNotNull('subject')->where('subject', '<>', '')
                ->distinct()->orderBy('subject')->pluck('subject'),
            'stats' => $stats,
            'returnQuery' => GradebookCatalogContext::returnQuery(
                GradebookCatalogContext::query($filters, $groups->currentPage()),
            ),
        ]);

        if ($request->header('X-Catalog-Fragment') === 'teacher-gradebook-results') {
            return response($view->fragment('teacher-gradebook-results'))->header('Cache-Control', 'no-store');
        }

        return $view;
    }

    /** @param array{search: string, status: string, subject: string} $filters */
    private function filterGradebookGroups(EloquentBuilder $groups, array $filters): void
    {
        if ($filters['status'] !== 'all') {
            $groups->where('is_closed', $filters['status'] === 'closed');
        }

        if ($filters['subject'] !== '') {
            $groups->where('subject', $filters['subject']);
        }

        $normalized = Normalizer::normalize($filters['search'], Normalizer::FORM_D) ?: $filters['search'];
        $search = mb_strtolower(preg_replace('/[\x{0300}-\x{036F}]/u', '', $normalized) ?? $normalized, 'UTF-8');
        if ($search === '') {
            return;
        }

        $groups->where(function (EloquentBuilder $query) use ($search): void {
            foreach (['name', 'subject', 'access_folio'] as $column) {
                $folded = "regexp_replace(lower(normalize(coalesce({$column}, ''), NFD)) COLLATE \"C\", U&'[\\0300-\\036F]', '', 'g')";
                $query->orWhereRaw("strpos({$folded}, ?) > 0", [$search]);
            }
        });
    }

    /**
     * Lista los periodos de evaluación configurados para un grupo escolar.
     */
    public function index(Request $request, Group $group): View
    {
        $this->authorizeTeacher($group);

        $periods = $group->evaluationPeriods()
            ->with('previousPeriods')
            ->addSelect([
                'required_exams_count' => DB::table('acs_period_required_exams')->selectRaw('COUNT(*)')
                    ->whereColumn('period_id', 'acs_evaluation_periods.id'),
                'weighted_exams_count' => DB::table('acs_period_exam_weights')->selectRaw('COUNT(*)')
                    ->whereColumn('period_id', 'acs_evaluation_periods.id'),
            ])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $gradedByPeriod = collect($this->results->calculate($group)['rows'])
            ->map(fn (array $rows) => collect($rows)->filter(fn ($grade) => $grade->final_grade !== null)->keys());

        return view('evaluation-periods.index', [
            'group' => $group,
            'periods' => $periods,
            'gradedByPeriod' => $gradedByPeriod->map->count(),
            'evaluatedStudentsTotal' => $gradedByPeriod->flatten()->unique()->count(),
            'examApplications' => $group->is_closed ? collect() : $this->examApplicationOptions($group),
            'catalogContext' => GradebookCatalogContext::destinationQuery($request),
            'catalogQuery' => GradebookCatalogContext::fromReturn($request),
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

        $catalogContext = GradebookCatalogContext::destinationQuery($request);
        $destination = route('groups.periods.index', [$group, ...$catalogContext]);
        try {
            DB::transaction(function () use ($request, $group, $formula): void {
                $group = Group::whereKey($group->id)->lockForUpdate()->firstOrFail();
                $this->authorizeTeacher($group);
                abort_if($group->is_closed, 403, 'No es posible registrar periodos de evaluación en un grupo escolar cerrado.');

                $period = $group->evaluationPeriods()->create([
                    'name' => (string) $request->input('name'),
                    'formula' => $formula,
                    'starts_at' => $request->input('starts_at'),
                    'ends_at' => $request->input('ends_at'),
                ]);
                $this->saveExamSelection($request, $group, $period);
            });
        } catch (ValidationException $exception) {
            if ($catalogContext !== []) {
                $exception->redirectTo($destination);
            }

            throw $exception;
        }

        return redirect($destination)
            ->with('status', 'Periodo de evaluación creado correctamente.');
    }

    public function edit(Group $group, EvaluationPeriod $period): View
    {
        $this->authorizeTeacher($group);
        abort_unless($period->group_id === $group->id, 404);
        abort_if($group->is_closed, 403);

        $selected = $period->requiredExamApplicationIds();

        return view('evaluation-periods.edit', [
            'group' => $group,
            'period' => $period,
            'selectedExamApplicationIds' => $selected,
            'examApplications' => $this->examApplicationOptions($group, $selected),
            'periodHasWeightedExams' => DB::table('acs_period_exam_weights')->where('period_id', $period->id)->exists(),
        ]);
    }

    public function update(
        EvaluationPeriodUpdateRequest $request,
        Group $group,
        EvaluationPeriod $period,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $group, $period): void {
            $group = Group::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $this->authorizeTeacher($group);
            abort_if($group->is_closed, 403);
            $period = $group->evaluationPeriods()->whereKey($period->id)->firstOrFail();
            $details = $request->safe()->only(['name', 'starts_at', 'ends_at']);
            $start = $details['starts_at'];

            if ($start !== $period->starts_at->toDateString()) {
                if ($start < EvaluationPeriodStoreRequest::earliestStartDate()->toDateString()) {
                    throw ValidationException::withMessages([
                        'starts_at' => $request->messages()['starts_at.after_or_equal'],
                    ]);
                }

                $source = $period->previousPeriods()->where('acs_evaluation_periods.starts_at', '>=', $start)
                    ->value('acs_evaluation_periods.name');
                if ($source !== null) {
                    throw ValidationException::withMessages([
                        'starts_at' => "El periodo debe empezar después de «{$source}» porque usa sus calificaciones",
                    ]);
                }

                $dependent = $group->evaluationPeriods()->where('starts_at', '<=', $start)
                    ->whereHas('previousPeriods', fn ($sources) => $sources->whereKey($period->id))
                    ->value('name');
                if ($dependent !== null) {
                    throw ValidationException::withMessages([
                        'starts_at' => "El periodo debe empezar antes de «{$dependent}» porque ese periodo usa sus calificaciones",
                    ]);
                }
            } else {
                unset($details['starts_at']);
            }

            if ($details['ends_at'] === $period->ends_at->toDateString()) {
                unset($details['ends_at']);
            }

            $period->update($details);
            $this->saveExamSelection($request, $group, $period);
        });

        return to_route('groups.periods.index', $group)->with('status', 'Periodo de evaluación actualizado');
    }

    private function saveExamSelection(FormRequest $request, Group $group, EvaluationPeriod $period): void
    {
        if (! $request->boolean('uses_exam_selection')) {
            return;
        }

        if (DB::table('acs_period_exam_weights')->where('period_id', $period->id)->exists()) {
            throw ValidationException::withMessages([
                'required_exam_ids' => 'El periodo ya usa aplicaciones ponderadas. Edita esas aplicaciones desde Ponderaciones',
            ]);
        }

        $requested = array_map('intval', $request->validated('required_exam_ids') ?? []);
        $current = $period->requiredExamApplicationIds();
        $available = $this->selectableExamApplications($group, $current)
            ->whereIn('a.id', $requested)->lock('for key share of a')->pluck('a.id')->all();

        if (array_diff($requested, $available) !== []) {
            throw ValidationException::withMessages([
                'required_exam_ids' => 'Selecciona aplicaciones publicadas de este grupo. Las aplicaciones ya seleccionadas se pueden conservar',
            ]);
        }

        DB::table('acs_period_required_exams')->where('period_id', $period->id)
            ->whereNotIn('application_id', $requested)->delete();
        $added = array_diff($requested, $current);
        if ($added !== []) {
            DB::table('acs_period_required_exams')->insert(array_map(
                static fn (int $applicationId): array => ['period_id' => $period->id, 'application_id' => $applicationId],
                array_values($added),
            ));
        }
        $period->forceFill(['uses_exam_selection' => true])->save();
    }

    /** @param list<int> $retained IDs ya seleccionados que se pueden conservar aunque se despubliquen. */
    private function selectableExamApplications(Group $group, array $retained): Builder
    {
        return DB::table('acs_exam_group as a')->join('acs_exams as e', 'e.id', '=', 'a.exam_id')
            ->where('a.group_id', $group->id)->where('e.teacher_id', $group->teacher_id)
            ->where(fn (Builder $query) => $query->where('e.is_published', true)->orWhereIn('a.id', $retained));
    }

    /** @param list<int> $retained IDs ya seleccionados en la edición. */
    private function examApplicationOptions(Group $group, array $retained = []): Collection
    {
        return $this->selectableExamApplications($group, $retained)
            ->orderBy('e.starts_at')->orderBy('a.id')
            ->get(['a.id', 'e.title', 'e.starts_at', 'e.is_published']);
    }

    /**
     * Sábana de calificaciones del grupo para un periodo específico.
     */
    public function gradebook(Request $request, Group $group, EvaluationPeriod $period): View|RedirectResponse
    {
        $this->authorizeTeacher($group);
        abort_unless($period->group_id === $group->id, 404);

        $catalogContext = GradebookCatalogContext::destinationQuery($request);
        $students = $this->entryService->students($group)->paginate(GradebookEntryService::PAGE_SIZE)->appends($catalogContext);
        if ($students->currentPage() > $students->lastPage()) {
            $request->session()->keep(['errors', '_old_input']);

            return to_route('groups.periods.gradebook', [$group, $period, ...$catalogContext, 'page' => $students->lastPage()]);
        }

        $results = $this->results->calculate($group, breakdownPeriodId: $period->id);
        $savedGrades = collect($results['rows'][$period->id] ?? []);
        $weightedExams = in_array($period->id, $results['weightedPeriods'], true);

        $weights = array_replace(array_fill_keys(GradePlanService::COMPONENTS, 0), $period->parsed_weights);
        $period->load('previousPeriods');
        $examDelivery = $weights['exams'] > 0 && $period->hasStarted() && $students->isNotEmpty()
            ? $this->results->examDeliveryProgress($group, $period, $students->getCollection()->modelKeys(), $weightedExams)
            : ['requiredCount' => 0, 'missingByStudent' => [], 'suggestedScores' => collect()];

        return view('evaluation-periods.gradebook', [
            'group' => $group,
            'period' => $period,
            'students' => $students,
            'savedGrades' => $savedGrades,
            'examAverages' => $examDelivery['suggestedScores'],
            'examDelivery' => $examDelivery,
            'periodBreakdowns' => $results['periodBreakdown'],
            'weights' => $weights,
            'weightedExams' => $weightedExams,
            'finals' => $results['finals'],
            'catalogContext' => $catalogContext,
            'catalogQuery' => GradebookCatalogContext::fromReturn($request),
        ]);
    }

    /**
     * Guarda masivamente las calificaciones capturadas y recalcula la calificación final.
     */
    public function saveGradebook(GradebookSaveRequest $request, Group $group, EvaluationPeriod $period): RedirectResponse
    {
        $this->authorizeTeacher($group);
        abort_unless($period->group_id === $group->id, 404);

        $page = $request->validated('gradebook_page');
        $catalogContext = GradebookCatalogContext::destinationQuery($request);
        $parameters = [$group, $period, ...$catalogContext];
        if ($page !== null) {
            $parameters['page'] = $page;
        }
        $destination = route('groups.periods.gradebook', $parameters);

        try {
            $this->entryService->save($group, $period, $request->validated('grades'), $request->user()->id, $page === null ? null : (int) $page);
        } catch (ValidationException $exception) {
            if ($catalogContext !== []) {
                $exception->redirectTo($destination);
            }

            throw $exception;
        }

        return redirect($destination)
            ->with('status', 'Calificaciones guardadas y promedios actualizados.');
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
     * Valida la propiedad del grupo frente a ataques IDOR (RNF-SEG-004, MPW-V3 §5.2.1).
     */
    private function authorizeTeacher(Group $group): void
    {
        abort_unless($group->teacher_id === request()->user()->id, 403);
    }
}
