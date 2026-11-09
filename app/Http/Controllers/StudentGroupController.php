<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentGroupController.php
 * Ubicación:   app/Http/Controllers/StudentGroupController.php
 * Descripción: Gestión web de asignaturas del estudiante: consulta de grupos
 *              matriculados (RF-GRU-010 / P-21) y abandono voluntario seguro
 *              (RF-GRU-012 / P-23, MPW-V2 §6.1).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\GroupEnrollmentService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentGroupController extends Controller
{
    public function __construct(private readonly GroupEnrollmentService $groupEnrollmentService)
    {
    }

    /**
     * Consulta de grupos activos en los que el estudiante se encuentra inscrito (RF-GRU-010).
     */
    public function index(Request $request): View
    {
        $groups = $request->user()->groupsAsStudent()
            ->where('acs_groups.is_closed', false)
            ->with(['teacher'])
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return view('student.groups.index', ['groups' => $groups]);
    }

    /**
     * Abandono voluntario del estudiante de un grupo escolar (RF-GRU-012, RF-GRU-017).
     */
    public function leave(Request $request, Group $group): RedirectResponse
    {
        try {
            $this->groupEnrollmentService->leaveGroup($request->user(), $group);
        } catch (ValidationException|DomainException $e) {
            $message = $e instanceof ValidationException
                ? ($e->validator->errors()->first() ?? 'No fue posible abandonar el grupo.')
                : $e->getMessage();

            return redirect()->route('student.groups.index')->withErrors(['group' => $message]);
        }

        return redirect()->route('student.groups.index')
            ->with('status', 'Has salido del grupo ' . $group->name . ' correctamente.');
    }
}
