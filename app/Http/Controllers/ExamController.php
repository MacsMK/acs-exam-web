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

use App\Http\Requests\ExamConfigurationUpdateRequest;
use App\Http\Requests\ExamSectionStoreRequest;
use App\Http\Requests\ExamStoreRequest;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Question;
use App\Services\ExamAssemblyService;
use App\Services\ExamNotificationService;
use App\Services\SubscriptionLimitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamAssemblyService $assemblyService,
        private readonly SubscriptionLimitService $subscriptionLimitService,
    ) {
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
        $this->subscriptionLimitService?->ensureCanCreateExam($request->user());

        $exam = $this->assemblyService->createExam($request->user(), $request->validated());

        return redirect()->route('exams.assemble', $exam)->with('status', 'Examen creado. Ahora arma los apartados.');
    }

    public function assemble(Exam $exam): View
    {
        $this->authorizeExam($exam);

        $exam->load(['sections.examQuestions.question']);

        $sessionKey = "exam_section_proposal_{$exam->id}";
        $proposal = request()->session()->get($sessionKey);

        $proposedQuestions = collect();

        if (is_array($proposal) && ! empty($proposal['question_ids'])) {
            $questions = Question::where('teacher_id', $exam->teacher_id)
                ->whereIn('id', $proposal['question_ids'])
                ->get()
                ->keyBy('id');

            $proposedQuestions = collect($proposal['question_ids'])
                ->map(fn ($id) => $questions->get($id))
                ->filter();
        }

        // Temas disponibles en el banco privado del docente.
        $subjectTags = Question::where('teacher_id', $exam->teacher_id)
            ->whereNotNull('subject_tag')
            ->where('subject_tag', '!=', '')
            ->distinct()
            ->orderBy('subject_tag')
            ->pluck('subject_tag');

        return view('exams.assemble', [
            'exam' => $exam,
            'proposal' => $proposal,
            'proposedQuestions' => $proposedQuestions,
            'subjectTags' => $subjectTags,
        ]);
    }

    public function generateSectionProposal(ExamSectionStoreRequest $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        abort_if(
            $exam->is_published,
            403,
            'No se puede modificar un examen publicado.'
        );

        $data = $request->validated();

        $sessionKey = "exam_section_proposal_{$exam->id}";
        $request->session()->forget($sessionKey);

        $questionIds = $this->assemblyService->generateProposal(
            $exam,
            $data['question_type'],
            (int) $data['requested_quantity'],
            $data['subject_tag'] ?? null
        );

        $request->session()->put($sessionKey, [
            'title' => $data['title'],
            'question_type' => $data['question_type'],
            'subject_tag' => $data['subject_tag'] ?? null,
            'requested_quantity' => (int) $data['requested_quantity'],
            'question_ids' => $questionIds,
        ]);

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Propuesta generada. Revísala antes de aceptarla.');
    }

    public function acceptSectionProposal(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        abort_if(
            $exam->is_published,
            403,
            'No se puede modificar un examen publicado.'
        );

        $sessionKey = "exam_section_proposal_{$exam->id}";
        $proposal = $request->session()->get($sessionKey);

        if (! is_array($proposal)) {
            return redirect()
                ->route('exams.assemble', $exam)
                ->withErrors([
                    'proposal' => 'No hay una propuesta pendiente de aceptación.',
                ]);
        }

        $this->assemblyService->acceptProposal(
            $exam,
            $proposal['title'],
            $proposal['question_type'],
            $proposal['question_ids'],
            $proposal['subject_tag'] ?? null
        );

        $request->session()->forget($sessionKey);

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Apartado y reactivos guardados correctamente.');
    }

    public function discardSectionProposal(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $request->session()->forget("exam_section_proposal_{$exam->id}");

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Propuesta descartada.');
    }

    public function configuration(Exam $exam): View
    {
        $this->authorizeExam($exam);

        $exam->load(['groups', 'sections.examQuestions.question']);

        return view('exams.configuration', compact('exam'));
    }

    public function updateConfiguration(ExamConfigurationUpdateRequest $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        abort_if(
            $exam->is_published,
            403,
            'No se puede modificar un examen publicado.'
        );

        $data = $request->validated();

        DB::transaction(function () use ($exam, $data) {
            $examQuestions = $exam->examQuestions()->lockForUpdate()->get()->keyBy('id');
            $submitted = $data['questions'];

            $expectedIds = $examQuestions->keys()->map(fn ($id) => (string) $id)->sort()->values()->all();
            $receivedIds = collect(array_keys($submitted))->map(fn ($id) => (string) $id)->sort()->values()->all();

            if ($expectedIds !== $receivedIds) {
                throw ValidationException::withMessages([
                    'questions' => 'La lista de preguntas cambió. Recarga la página y vuelve a intentarlo.',
                ]);
            }

            $totalCents = 0;
            foreach ($submitted as $values) {
                $totalCents += (int) round((float) $values['points_weight'] * 100);
            }

            if ($totalCents !== 10000) {
                throw ValidationException::withMessages([
                    'questions' => 'La suma de las ponderaciones debe ser exactamente 100 puntos.',
                ]);
            }

            $exam->update(collect($data)->except(['questions', 'group_ids'])->all());

            if (isset($data['group_ids'])) {
                $exam->groups()->sync($data['group_ids']);
            }

            foreach ($submitted as $id => $values) {
                $examQuestions->get((int) $id)->update([
                    'points_weight' => $values['points_weight'],
                    'time_limit_seconds' => $values['time_limit_seconds'],
                ]);
            }
        });

        return redirect()
            ->route('exams.review', $exam)
            ->with('status', 'Configuración guardada correctamente.');
    }

    public function review(Exam $exam): View
    {
        $this->authorizeExam($exam);

        $exam->load([
            'groups',
            'sections.examQuestions.question',
        ]);

        return view('exams.review', compact('exam'));
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

    public function publish(Exam $exam, ExamNotificationService $notifications): RedirectResponse
    {
        $this->authorizeExam($exam);

        $this->subscriptionLimitService?->ensureCanPublishExam(request()->user(), $exam);

        $questionsCount = $exam->examQuestions()->count();
        abort_if($questionsCount === 0, 422, 'El examen no tiene reactivos todavía.');

        $totalPoints = (float) $exam->examQuestions()->sum('points_weight');
        abort_if($totalPoints <= 0, 422, 'La suma de ponderaciones del examen debe ser mayor que cero.');

        $exam->update(['is_published' => true]);

        $notifications->examPublished($exam);

        return redirect()->route('exams.index')->with('status', 'Examen publicado.');
    }

    private function authorizeExam(Exam $exam): void
    {
        abort_unless($exam->teacher_id === request()->user()->id, 403);
    }
}
