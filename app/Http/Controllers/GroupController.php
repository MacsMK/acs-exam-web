<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupController.php
 * Ubicación:   app/Http/Controllers/GroupController.php
 * Descripción: CRUD de grupos del docente + folio/QR y cierre semestral seguro
 *              (RW-32 a RW-36, RF-GRU-018, MPW-V3 §5.2.1, §6.1).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\GroupStoreRequest;
use App\Http\Requests\GroupUpdateRequest;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\StudentPeriodGrade;
use App\Models\User;
use App\Services\GroupEnrollmentService;
use App\Services\GroupRetentionService;
use App\Support\GradebookCatalogContext;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(private readonly GroupEnrollmentService $groupEnrollmentService)
    {
    }

    public function index(): View
    {
        $groups = request()->user()->groupsAsTeacher()->withCount('students')->latest()->get();

        return view('groups.index', ['groups' => $groups]);
    }

    public function create(Request $request): View
    {
        $catalogQuery = GradebookCatalogContext::fromReturn($request);

        return view('groups.create', [
            'catalogQuery' => $catalogQuery,
            'catalogContext' => $catalogQuery === null ? [] : GradebookCatalogContext::returnQuery($catalogQuery),
        ]);
    }

    public function store(GroupStoreRequest $request): RedirectResponse
    {
        $group = $this->groupEnrollmentService->createGroup($request->user(), $request->validated());

        return to_route('groups.show', [$group, ...GradebookCatalogContext::destinationQuery($request)])
            ->with('status', 'Grupo creado.');
    }

    /**
     * Formulario de modificación de un grupo activo (RF-GRU-003, ACT-WEB-020).
     */
    public function edit(Group $group): View|RedirectResponse
    {
        $this->authorizeOwnership($group);

        if ($group->is_closed) {
            return redirect()
                ->route('groups.show', $group)
                ->withErrors(['group' => 'Solo puedes modificar grupos activos. Reabre el grupo para editarlo.']);
        }

        return view('groups.edit', ['group' => $group]);
    }

    public function update(GroupUpdateRequest $request, Group $group): RedirectResponse
    {
        $this->authorizeOwnership($group);

        try {
            $this->groupEnrollmentService->updateGroup($group, $request->validated());
        } catch (DomainException $e) {
            return redirect()->route('groups.show', $group)->withErrors(['group' => $e->getMessage()]);
        }

        return redirect()->route('groups.show', $group)->with('status', 'Grupo actualizado.');
    }

    public function show(Request $request, Group $group, GroupRetentionService $retention): View
    {
        $this->authorizeOwnership($group);

        $group->load([
            'students',
            'exams' => static function (BelongsToMany $query) use ($group): void {
                $query->withCount([
                    'studentAttempts',
                    'studentAttempts as group_submitted_attempts_count' => static function (Builder $attemptQuery) use ($group): void {
                        $attemptQuery->where('group_id', $group->id)->current()->where('status', 'submitted');
                    },
                ]);
            },
            'evaluationPeriods.previousPeriods',
        ]);

        $attempts = StudentAttempt::query()
            ->where('group_id', $group->id)
            ->whereHas('exam', function ($query) use ($group) {
                $query->where('teacher_id', $group->teacher_id);
            })
            ->with(['student', 'exam'])
            ->latest('started_at')
            ->latest('id')
            ->get();

        $currentAttemptIds = $group->studentAttempts()->current()->pluck('id')->flip();
        $enrolledStudentIds = $group->students->pluck('id')->flip();
        $currentEnrolledAttempts = $attempts->filter(
            static fn (StudentAttempt $attempt): bool => $currentAttemptIds->has($attempt->id)
                && $enrolledStudentIds->has($attempt->student_id)
        );

        $withdrawnPeriodGrades = StudentPeriodGrade::query()
            ->whereIn('evaluation_period_id', $group->evaluationPeriods->modelKeys())
            ->whereNotIn('student_id', $enrolledStudentIds->keys())
            ->with(['student', 'evaluationPeriod'])
            ->orderBy('evaluation_period_id')
            ->get();

        $withdrawnWithoutGrades = AuditLog::query()
            ->where('auditable_type', Group::class)
            ->where('auditable_id', $group->id)
            ->whereIn('action', ['group.student_removed', 'group.left'])
            ->get(['old_values'])
            ->pluck('old_values.student_id')
            ->filter()
            ->unique()
            ->diff($enrolledStudentIds->keys())
            ->diff($attempts->pluck('student_id'))
            ->diff($withdrawnPeriodGrades->pluck('student_id'))
            ->values();

        return view('groups.show', [
            'group' => $group,
            'catalogContext' => GradebookCatalogContext::destinationQuery($request),
            'catalogQuery' => GradebookCatalogContext::fromReturn($request),
            'attempts' => $attempts,
            'currentAttemptIds' => $currentAttemptIds,
            'enrolledStudentIds' => $enrolledStudentIds,
            'currentEnrolledAttempts' => $currentEnrolledAttempts,
            'withdrawnPeriodGrades' => $withdrawnPeriodGrades,
            'withdrawnStudentsWithoutGrades' => User::query()
                ->whereKey($withdrawnWithoutGrades)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'deletionDate' => $retention->deletionDateFor($group),
        ]);
    }

    public function close(Group $group): RedirectResponse
    {
        $this->authorizeOwnership($group);

        try {
            $this->groupEnrollmentService->closeGroup($group);
        } catch (DomainException $e) {
            return redirect()->route('groups.show', $group)->withErrors(['group' => $e->getMessage()]);
        }

        return redirect()->route('groups.show', $group)->with('status', 'Grupo cerrado.');
    }

    /**
     * Retiro de un estudiante por el docente (RF-GRU-011, RF-GRU-016, ACT-WEB-021).
     */
    public function removeStudent(Group $group, User $student): RedirectResponse
    {
        $this->authorizeOwnership($group);

        try {
            $this->groupEnrollmentService->removeStudent($group, $student, auth()->user());
        } catch (DomainException $e) {
            return redirect()->route('groups.show', $group)->withErrors(['student' => $e->getMessage()]);
        }

        return redirect()
            ->route('groups.show', $group)
            ->with('status', "Se retiró a {$student->full_name} del grupo. Sus registros académicos se conservan.");
    }

    public function reopen(Group $group): RedirectResponse
    {
        $this->authorizeOwnership($group);

        $this->groupEnrollmentService->reopenGroup($group);

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'El grupo se ha reabierto correctamente.');
    }

    private function authorizeOwnership(Group $group): void
    {
        abort_unless($group->teacher_id === request()->user()->id, 403);
    }
}
