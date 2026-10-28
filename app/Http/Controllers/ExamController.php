<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamController.php
 * Ubicación:   app/Http/Controllers/ExamController.php
 * Descripción: Armado de examen (Módulo 6): alta, apartados + sorteo,
 *              asignación a grupos y publicación (RW-12 a RW-24, MPW-V2 §6.1).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\ExamSectionStoreRequest;
use App\Http\Requests\ExamStoreRequest;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Services\ExamAssemblyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function __construct(private readonly ExamAssemblyService $assemblyService)
    {
    }

    public function index(Request $request): View
    {
        $exams = $request->user()->examsCreated()->latest()->get();

        return view('exams.index', ['exams' => $exams]);
    }

    public function create(Request $request): View
    {
        $groups = $request->user()->groupsAsTeacher()->get();

        return view('exams.create', ['groups' => $groups]);
    }

    public function store(ExamStoreRequest $request): RedirectResponse
    {
        $exam = $this->assemblyService->createExam($request->user(), $request->validated());

        return redirect()->route('exams.assemble', $exam)->with('status', 'Examen creado. Ahora arma los apartados.');
    }

    public function assemble(Exam $exam): View
    {
        $this->authorizeExam($exam);

        $exam->load(['sections.examQuestions.question']);

        return view('exams.assemble', ['exam' => $exam]);
    }

    public function storeSection(ExamSectionStoreRequest $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        if ($exam->is_published) {
            return back()->withErrors(['exam' => 'No es posible agregar apartados a un examen ya publicado.']);
        }

        $data = $request->validated();
        $section = $this->assemblyService->createSectionAndDraw($exam, $data);

        return redirect()->route('exams.assemble', $exam)->with('status', "Apartado \"{$section->title}\" armado con {$data['requested_quantity']} reactivos.");
    }

    public function reshuffleQuestion(Exam $exam, ExamQuestion $examQuestion): RedirectResponse
    {
        $this->authorizeExam($exam);

        abort_if($examQuestion->exam_id !== $exam->id, 404);

        if ($exam->is_published) {
            return back()->withErrors(['exam' => 'No es posible re-sortear reactivos de un examen ya publicado.']);
        }

        $this->assemblyService->reshuffleQuestion($examQuestion);

        return redirect()->route('exams.assemble', $exam)->with('status', 'Reactivo sustituido (nuevo sorteo, RW-14).');
    }

    public function publish(Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $questionsCount = $exam->examQuestions()->count();
        abort_if($questionsCount === 0, 422, 'El examen no tiene reactivos todavía.');

        $totalPoints = (float) $exam->examQuestions()->sum('points_weight');
        abort_if($totalPoints <= 0, 422, 'La suma de ponderaciones del examen debe ser mayor que cero.');

        $exam->update(['is_published' => true]);

        return redirect()->route('exams.index')->with('status', 'Examen publicado.');
    }

    private function authorizeExam(Exam $exam): void
    {
        abort_unless($exam->teacher_id === request()->user()->id, 403);
    }
}
