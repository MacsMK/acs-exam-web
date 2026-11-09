<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupController.php
 * Ubicación:   app/Http/Controllers/GroupController.php
 * Descripción: CRUD de grupos del docente + folio/QR (RW-32 a RW-36).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\GroupStoreRequest;
use App\Models\Group;
use App\Models\StudentAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function index(): View
    {
        $groups = request()->user()->groupsAsTeacher()->withCount('students')->latest()->get();

        return view('groups.index', ['groups' => $groups]);
    }

    public function create(): View
    {
        return view('groups.create');
    }

    public function store(GroupStoreRequest $request): RedirectResponse
    {
        $group = Group::create([
            'teacher_id' => $request->user()->id,
            'name' => $request->string('name')->toString(),
            'subject' => $request->string('subject')->toString() ?: null,
            'access_folio' => $this->generateUniqueFolio(),
        ]);

        return redirect()->route('groups.show', $group)->with('status', 'Grupo creado.');
    }
    public function show(Group $group): View
    {
        $this->authorizeOwnership($group);

        $group->load([
            'students',
            'exams',
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

        return view('groups.show', [
            'group' => $group,
            'attempts' => $attempts,
        ]);
    }

    public function close(Group $group): RedirectResponse
    {
        $this->authorizeOwnership($group);

        $group->update(['is_closed' => true, 'closed_at' => now()]);

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

    private function generateUniqueFolio(): string
    {
        do {
            $folio = strtoupper(Str::random(8));
        } while (Group::where('access_folio', $folio)->exists());

        return $folio;
    }
}
