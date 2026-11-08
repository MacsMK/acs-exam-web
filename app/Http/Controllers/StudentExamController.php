<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentExamController.php
 * Ubicación:   app/Http/Controllers/StudentExamController.php
 * Descripción: Versión web (sesión) del lado alumno: unirse a un grupo por
 *              folio y ver los exámenes publicados asignados (MPW-V2 §6.1).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\GroupJoinRequest;
use App\Services\GroupEnrollmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentExamController extends Controller
{
    public function __construct(private readonly GroupEnrollmentService $groupEnrollmentService)
    {
    }

    public function joinForm(): View
    {
        return view('groups.join');
    }

    public function join(GroupJoinRequest $request): RedirectResponse
    {
        $group = $this->groupEnrollmentService->joinByFolio(
            $request->user(),
            $request->resolvedFolio(),
            'access_folio',
        );

        return redirect()->route('student.exams.index')->with('status', 'Te uniste al grupo '.$group->name.'.');
    }

    public function index(Request $request): View
    {
        $exams = $this->groupEnrollmentService->listAvailableExamsForStudent($request->user());

        return view('exams.student-index', ['exams' => $exams]);
    }
}
