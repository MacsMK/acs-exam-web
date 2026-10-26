<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentExamController.php
 * Ubicación:   app/Http/Controllers/StudentExamController.php
 * Descripción: Versión web (sesión) del lado alumno: unirse a un grupo por
 *              folio y ver los exámenes publicados asignados, para que el
 *              profesor pueda probar el flujo completo del alumno dando
 *              clic en el navegador, sin necesitar la app móvil.
 *              Misma lógica que Api/V1/GroupController::join() y
 *              Api/V1/ExamController::assignedToMe().
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Group;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentExamController extends Controller
{
    public function joinForm(): View
    {
        return view('groups.join');
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'access_folio' => ['required', 'string'],
        ]);

        $group = Group::where('access_folio', strtoupper($data['access_folio']))->first();

        if (! $group) {
            throw ValidationException::withMessages(['access_folio' => ['Folio no encontrado.']]);
        }

        if ($group->is_closed) {
            throw ValidationException::withMessages(['access_folio' => ['Este grupo ya cerró el ciclo (RW-35).']]);
        }

        $group->students()->syncWithoutDetaching([
            $request->user()->id => ['joined_at' => now()],
        ]);

        return redirect()->route('student.exams.index')->with('status', 'Te uniste al grupo '.$group->name.'.');
    }

    public function index(Request $request): View
    {
        $student = $request->user();

        $exams = Exam::where('is_published', true)
            ->whereHas('groups.students', fn ($q) => $q->where('acs_users.id', $student->id))
            ->with('sections')
            ->get();

        return view('exams.student-index', ['exams' => $exams]);
    }
}
