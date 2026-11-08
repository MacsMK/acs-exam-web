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
use App\Services\GroupEnrollmentService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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

    public function create(): View
    {
        return view('groups.create');
    }

    public function store(GroupStoreRequest $request): RedirectResponse
    {
        $group = $this->groupEnrollmentService->createGroup($request->user(), $request->validated());

        return redirect()->route('groups.show', $group)->with('status', 'Grupo creado.');
    }

    public function show(Group $group): View
    {
        $this->authorizeOwnership($group);

        $group->load('students');

        return view('groups.show', ['group' => $group]);
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

    private function authorizeOwnership(Group $group): void
    {
        abort_unless($group->teacher_id === request()->user()->id, 403);
    }
}
