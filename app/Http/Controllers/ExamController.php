<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamController.php
 * Ubicación:   app/Http/Controllers/ExamController.php
 * Descripción: Armado de examen (Módulo 6): alta, apartados + sorteo,
 *              asignación a grupos y publicación (RW-12 a RW-24).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\ExamStoreRequest;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Services\ExamAssemblyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $data = $request->validated();

        $exam = Exam::create([
            'teacher_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'grading_scale' => $data['grading_scale'],
            'randomize_question_order' => (bool) ($data['randomize_question_order'] ?? true),
            'result_visibility_mode' => $data['result_visibility_mode'],
            'require_webcam' => (bool) ($data['require_webcam'] ?? false),
            'max_allowed_incidents' => $data['max_allowed_incidents'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'share_token' => Str::random(32),
            'is_published' => false,
        ]);

        if (! empty($data['group_ids'])) {
            $exam->groups()->sync($data['group_ids']);
        }

        return redirect()->route('exams.assemble', $exam)->with('status', 'Examen creado. Ahora arma los apartados.');
    }

    public function assemble(Exam $exam): View
    {
        $this->authorizeOwnership($exam);

        $exam->load(['sections.examQuestions.question']);

        return view('exams.assemble', ['exam' => $exam]);
    }

    public function storeSection(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeOwnership($exam);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'question_type' => ['required', 'in:multiple_choice,matching,open,code'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
        ]);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => $data['title'],
            'question_type' => $data['question_type'],
            'requested_quantity' => $data['requested_quantity'],
            'sort_order' => $exam->sections()->count(),
        ]);

        $this->assemblyService->drawQuestionsForSection($section);

        return redirect()->route('exams.assemble', $exam)->with('status', "Apartado \"{$section->title}\" armado con {$data['requested_quantity']} reactivos.");
    }

    public function reshuffleQuestion(Exam $exam, ExamQuestion $examQuestion): RedirectResponse
    {
        $this->authorizeOwnership($exam);

        $this->assemblyService->reshuffleQuestion($examQuestion);

        return redirect()->route('exams.assemble', $exam)->with('status', 'Reactivo sustituido (nuevo sorteo, RW-14).');
    }

    public function publish(Exam $exam): RedirectResponse
    {
        $this->authorizeOwnership($exam);

        abort_if($exam->examQuestions()->count() === 0, 422, 'El examen no tiene reactivos todavía.');

        $exam->update(['is_published' => true]);

        return redirect()->route('exams.index')->with('status', 'Examen publicado.');
    }

    private function authorizeOwnership(Exam $exam): void
    {
        abort_unless($exam->teacher_id === request()->user()->id, 403);
    }
}
