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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

            // Valores iniciales del borrador.
            // El docente los ajustará en Configuración.
            'grading_scale' => '0-10',
            'randomize_question_order' => true,
            'result_visibility_mode' => 'hidden',
            'require_webcam' => false,
            'max_allowed_incidents' => 3,

            // Valores provisionales para satisfacer
            // las columnas obligatorias de la BD.
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],

            'share_token' => Str::random(32),
            'is_published' => false,
        ]);

        return redirect()
            ->route('exams.assemble', $exam)
            ->with(
                'status',
                'Borrador creado correctamente. Continúa con las preguntas.'
            );
    }

    public function assemble(Exam $exam): View
    {
        $this->authorizeOwnership($exam);

        // Cargar los apartados y reactivos ya guardados.
        $exam->load(['sections.examQuestions.question']);

        // Recuperar la propuesta temporal de este examen.
        $sessionKey = "exam_section_proposal_{$exam->id}";
        $proposal = request()->session()->get($sessionKey);

        $proposedQuestions = collect();

        if (is_array($proposal) && !empty($proposal['question_ids'])) {
            $questions = \App\Models\Question::where('teacher_id', $exam->teacher_id)
                ->whereIn('id', $proposal['question_ids'])
                ->get()
                ->keyBy('id');

            // Mantener el orden de la propuesta original.
            $proposedQuestions = collect($proposal['question_ids'])
                ->map(fn ($id) => $questions->get($id))
                ->filter()
                ->values();
        }

        // Temas disponibles en el banco privado del docente.
        $subjectTags = \App\Models\Question::where('teacher_id', $exam->teacher_id)
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

    public function storeSection(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeOwnership($exam);

        abort_if(
            $exam->is_published,
            403,
            'No se pueden agregar apartados a un examen publicado.'
        );

        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'question_type' => ['required', 'in:multiple_choice,matching,open,code'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($exam, $data) {
            $section = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => $data['title'],
                'question_type' => $data['question_type'],
                'requested_quantity' => $data['requested_quantity'],
                'sort_order' => $exam->sections()->count(),
            ]);

            $this->assemblyService->drawQuestionsForSection($section);
        });

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Apartado creado correctamente con sus reactivos.');
    }

    /**
     * Genera una propuesta provisional de preguntas del banco.
     * No crea apartados ni asignaciones en la base de datos.
     */
    public function generateSectionProposal(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeOwnership($exam);

        abort_if(
            $exam->is_published,
            403,
            'No se puede modificar un examen publicado.'
        );

        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'question_type' => ['required', 'in:multiple_choice,matching,open,code'],
            'subject_tag' => ['nullable', 'string', 'max:255'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
        ]);

        $sessionKey = "exam_section_proposal_{$exam->id}";

        // Una propuesta anterior no debe sobrevivir a un intento
        // fallido de generar otra con filtros diferentes.
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

    /**
     * Acepta la propuesta almacenada en la sesión del docente.
     */
    public function acceptSectionProposal(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeOwnership($exam);

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

    /**
     * Descarta una propuesta sin modificar el examen.
     */
    public function discardSectionProposal(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeOwnership($exam);

        $request->session()->forget("exam_section_proposal_{$exam->id}");

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Propuesta descartada correctamente.');
    }

    public function reshuffleQuestion(Exam $exam, ExamQuestion $examQuestion): RedirectResponse
    {
        $this->authorizeOwnership($exam);

        abort_if($exam->is_published, 403, 'No se puede modificar un examen publicado.');

        abort_unless(
            $examQuestion->exam_id === $exam->id,
            404,
            'El reactivo no pertenece a este examen.'
        );

        $this->assemblyService->reshuffleQuestion($examQuestion);

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Reactivo sustituido correctamente.');
    }

    public function publish(Exam $exam): RedirectResponse
    {
        $this->authorizeOwnership($exam);

        abort(403, 'La publicación estará disponible cuando se completen las validaciones del examen.');
    }

    public function configuration(Exam $exam): View
    {
        $this->authorizeOwnership($exam);

        $exam->load(['groups', 'sections.examQuestions.question']);

        return view('exams.configuration', compact('exam'));
    }

    public function updateConfiguration(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeOwnership($exam);

        abort_if(
            $exam->is_published,
            403,
            'No se puede modificar un examen publicado.'
        );

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'grading_scale' => ['required', 'in:0-10,0-100'],
            'result_visibility_mode' => [
                'required',
                'in:final_score,estimated_score,objective_only_pending,hidden',
            ],
            'max_allowed_incidents' => ['required', 'integer', 'min:0'],
            'randomize_question_order' => ['required', 'boolean'],
            'require_webcam' => ['required', 'boolean'],
            'questions' => ['required', 'array'],
            'questions.*.points_weight' => ['required','numeric','min:0','max:100', $request->input('grading_scale') === '0-100' ? 'integer': 'decimal:0,2'],
            'questions.*.time_limit_seconds' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($exam, $data) {
            $examQuestions = $exam->examQuestions()->lockForUpdate()->get()->keyBy('id');
            $submitted = $data['questions'];

            // No aceptar reactivos de otros exámenes, omitidos o duplicados.
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

            $exam->update(collect($data)->except('questions')->all());

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
        $this->authorizeOwnership($exam);

        $exam->load([
            'groups',
            'sections.examQuestions.question',
        ]);

        return view('exams.review', compact('exam'));
    }

    private function authorizeOwnership(Exam $exam): void
    {
        abort_unless($exam->teacher_id === request()->user()->id, 403);
    }
}