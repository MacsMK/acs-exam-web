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
use App\Models\Exam;
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

        return redirect()->route('student.groups.index')->with('status', 'Te uniste al grupo '.$group->name.'.');
    }

    public function index(Request $request): View
    {
        $exams = $this->groupEnrollmentService->listAvailableExamsForStudent($request->user());

        return view('exams.student-index', ['exams' => $exams]);
    }

    /**
     * Pantalla previa de instrucciones y condiciones antes de iniciar el cronómetro (RF-EXA-028 / P-83).
     */
    public function instructions(Request $request, Exam $exam): View|RedirectResponse
    {
        $student = $request->user();

        $isEligible = $exam->is_published && $exam->groups()
            ->where('acs_groups.is_closed', false)
            ->whereHas('students', static fn ($q) => $q->where('acs_users.id', $student->id))
            ->exists();

        abort_unless($isEligible, 403, 'No tienes permiso para acceder a este examen.');

        $inProgress = $student->studentAttempts()
            ->where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->first();

        if ($inProgress) {
            return redirect()->route('attempts.show', $inProgress);
        }

        $submitted = $student->studentAttempts()
            ->where('exam_id', $exam->id)
            ->where('status', 'submitted')
            ->first();

        if ($submitted) {
            return redirect()->route('attempts.result', $submitted);
        }

        $exam->loadMissing(['teacher', 'groups']);
        $questionsCount = $exam->examQuestions()->count();

        return view('student.exams.instructions', [
            'exam' => $exam,
            'questionsCount' => $questionsCount,
        ]);
    }
}
