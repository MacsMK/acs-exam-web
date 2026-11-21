<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupController.php
 * Ubicación:   app/Http/Controllers/GroupController.php
 * Descripción: CRUD de grupos del docente + folio/QR y cierre semestral seguro
 *              (RW-32 a RW-36, RF-GRU-018, MPW-V2 §5.2.1, §6.1).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\GroupStoreRequest;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Services\GroupEnrollmentService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class GroupController extends Controller
{
    public function __construct(private readonly GroupEnrollmentService $groupEnrollmentService)
    {
    }

    public function index(): View|JsonResponse
    {
        $groups = request()->user()->groupsAsTeacher()->withCount('students')->latest()->get();

        if (request()->wantsJson()) {
            return response()->json([
                'total_groups' => $groups->count(),
                'active_groups' => $groups->where('is_closed', false)->count(),
                'closed_groups' => $groups->where('is_closed', true)->count(),
                'total_students' => (int) $groups->sum('students_count'),
                'groups' => $groups->map(static fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'subject' => $group->subject ?? 'Sin asignatura',
                    'students_count' => (int) $group->students_count,
                    'is_closed' => (bool) $group->is_closed,
                    'folio' => $group->access_folio,
                    'url' => route('groups.show', $group),
                    'periods_url' => route('groups.periods.index', $group),
                ])->values(),
            ]);
        }

        return view('groups.index', ['groups' => $groups]);
    }

    public function create(): View
    {
        return view('groups.create');
    }

    public function store(GroupStoreRequest $request): RedirectResponse
    {
        $group = $this->groupEnrollmentService->createGroup($request->user(), $request->validated());

        return redirect()->route('groups.show', $group)->with('status', 'Grupo creado.');
    }

    public function show(Group $group): View|JsonResponse
    {
        $this->authorizeOwnership($group);

        $group->load([
            'students',
            'exams' => static function (BelongsToMany $query) use ($group): void {
                $query->withCount([
                    'studentAttempts as group_attempts_count' => static function (Builder $attemptQuery) use ($group): void {
                        $attemptQuery->where('group_id', $group->id);
                    },
                ]);
            },
            'evaluationPeriods',
        ]);

        $attempts = StudentAttempt::query()
            ->where('group_id', $group->id)
            ->whereHas('exam', function ($query) use ($group) {
                $query->where('teacher_id', $group->teacher_id);
            })
            ->whereHas('student', function ($query) use ($group) {
                $query->whereHas('groupsAsStudent', function ($groups) use ($group) {
                    $groups->whereKey($group->id);
                });
            })
            ->with(['student', 'exam'])
            ->latest('started_at')
            ->get();

        if (request()->wantsJson()) {
            return response()->json([
                'students_count' => $group->students->count(),
                'attempts_count' => $attempts->count(),
                'submitted_count' => $attempts->where('status', 'submitted')->count(),
                'in_progress_count' => $attempts->where('status', 'in_progress')->count(),
                'students' => $group->students->map(static fn ($student) => [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'initial' => strtoupper(substr($student->first_name ?? $student->full_name, 0, 1)),
                    'email' => $student->email,
                    'joined_at' => $student->pivot->joined_at
                        ? \Carbon\Carbon::parse($student->pivot->joined_at)->format('d/m/Y H:i')
                        : '—',
                    'haystack' => mb_strtolower($student->full_name . ' ' . $student->email),
                ])->values(),
            ]);
        }

        return view('groups.show', [
            'group' => $group,
            'attempts' => $attempts,
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

        return redirect()->route('groups.show', $group)->with('status', 'Grupo cerrado (cierre semestral, RW-35).');
    }

    public function reopen(Group $group): RedirectResponse
    {
        $this->authorizeOwnership($group);

        abort_unless($group->is_closed, 409, 'El grupo ya está activo.');

        $group->update([
            'is_closed' => false,
            'closed_at' => null,
        ]);

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'El grupo se ha reabierto correctamente.');
    }

    private function authorizeOwnership(Group $group): void
    {
        abort_unless($group->teacher_id === request()->user()->id, 403);
    }
}
