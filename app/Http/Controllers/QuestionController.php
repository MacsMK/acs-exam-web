<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionController.php
 * Ubicación:      app/Http/Controllers/QuestionController.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, MPW-V2 §6.1, §6.3)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.3.0
 * Descripción:    Controlador delgado (Skinny Controller) del Banco de Reactivos:
 *                 filtrado y paginación en SQL, edición in-situ vs copy-on-write,
 *                 duplicación, paráfrasis anti-copia, exportación/importación CSV.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\QuestionImportRequest;
use App\Http\Requests\QuestionParaphraseStoreRequest;
use App\Http\Requests\QuestionStoreRequest;
use App\Http\Requests\QuestionUpdateRequest;
use App\Models\ExamQuestion;
use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Services\QuestionBankService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QuestionController extends Controller
{
    public function __construct(private readonly QuestionBankService $questionBankService)
    {
    }

    /**
     * Listado paginado del banco de reactivos con filtros avanzados.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'question_type', 'subject_tag', 'paraphrase_status', 'sort_by']);

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50], true) ? $perPage : 20;

        $questions = $this->questionBankService->paginateForTeacher($request->user(), $filters, $perPage);
        $subjects = $this->questionBankService->getDistinctSubjectsForTeacher($request->user());

        return view('questions.index', compact('questions', 'subjects', 'filters', 'perPage'));
    }

    /**
     * Formulario de creación de reactivo.
     */
    public function create(): View
    {
        return view('questions.create');
    }

    /**
     * Persistencia atómica de un nuevo reactivo polimórfico.
     */
    public function store(QuestionStoreRequest $request): RedirectResponse
    {
        $this->questionBankService->createQuestion($request->user(), $request->validated());

        return redirect()->route('questions.index')->with('status', 'Reactivo creado exitosamente.');
    }

    /**
     * Vista de detalle del reactivo con su variante polimórfica y paráfrasis.
     */
    public function show(Request $request, Question $question): View
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $question->load(['questionable', 'paraphrases', 'parent', 'children']);
        $isLinkedToExams = method_exists($question, 'examQuestions')
            ? $question->examQuestions()->exists()
            : ExamQuestion::query()->where('question_id', $question->id)->exists();

        return view('questions.show', compact('question', 'isLinkedToExams'));
    }

    /**
     * Formulario de edición del reactivo.
     */
    public function edit(Request $request, Question $question): View
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $question->load(['questionable', 'paraphrases']);
        $isLinkedToExams = method_exists($question, 'examQuestions')
            ? $question->examQuestions()->exists()
            : ExamQuestion::query()->where('question_id', $question->id)->exists();

        return view('questions.edit', compact('question', 'isLinkedToExams'));
    }

    /**
     * Actualización de reactivo (in-situ o Copy-On-Write si está vinculado a exámenes históricos).
     */
    public function update(QuestionUpdateRequest $request, Question $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $wasLinked = method_exists($question, 'examQuestions')
            ? $question->examQuestions()->exists()
            : ExamQuestion::query()->where('question_id', $question->id)->exists();

        $target = $this->questionBankService->updateQuestion($request->user(), $question, $request->validated());

        $message = $wasLinked
            ? 'Se generó una nueva versión (v2) del reactivo debido a que el original ya está vinculado a exámenes históricos.'
            : 'Reactivo actualizado exitosamente.';

        return redirect()->route('questions.show', $target)->with('status', $message);
    }

    /**
     * Eliminación segura (Soft Delete) de un reactivo.
     */
    public function destroy(Request $request, Question $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $this->questionBankService->deleteQuestion($request->user(), $question);

        return redirect()->route('questions.index')->with('status', 'Reactivo eliminado del banco exitosamente.');
    }

    /**
     * Duplica un reactivo existente del docente.
     */
    public function duplicate(Request $request, Question $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $clone = $this->questionBankService->duplicateQuestion($request->user(), $question);

        return redirect()->route('questions.index')->with('status', "Reactivo duplicado exitosamente como '{$clone->title}'.");
    }

    /**
     * Registra una paráfrasis anti-copia (RF-BAN-010).
     */
    public function storeParaphrase(QuestionParaphraseStoreRequest $request, Question $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $data = $request->validated();
        $this->questionBankService->createParaphrase($question, (string) $data['stem']);

        return back()->with('status', 'Paráfrasis guardada.');
    }

    /**
     * Elimina una paráfrasis anti-copia.
     */
    public function destroyParaphrase(Request $request, Question $question, QuestionParaphrase $paraphrase): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $this->questionBankService->deleteParaphrase($request->user(), $question, $paraphrase);

        return redirect()->route('questions.show', $question)->with('status', 'Paráfrasis eliminada.');
    }

    /**
     * Descarga la plantilla institucional CSV para importación masiva.
     */
    public function downloadTemplate(): Response
    {
        $csv = $this->questionBankService->generateCsvTemplate();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_reactivos_acode.csv"',
        ]);
    }

    /**
     * Importa reactivos masivamente desde un archivo CSV.
     */
    public function import(QuestionImportRequest $request): RedirectResponse
    {
        $content = $request->file('csv_file')?->get() ?? '';
        $result = $this->questionBankService->importBatchFromCsv($request->user(), $content);

        if ($result['imported'] === 0 && ! empty($result['errors'])) {
            return back()->withErrors([
                'csv_file' => 'No se pudo importar ningún reactivo: ' . implode(' | ', array_slice($result['errors'], 0, 3)),
            ]);
        }

        $status = "Se importaron {$result['imported']} reactivos exitosamente.";
        if (! empty($result['errors'])) {
            $status .= ' (Se detectaron ' . count($result['errors']) . ' errores en otras filas).';
        }

        return redirect()->route('questions.index')->with('status', $status);
    }
}
