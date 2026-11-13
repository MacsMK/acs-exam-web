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
 * Versión:        2.0.0
 * Descripción:    Controlador delgado (Skinny Controller) del Banco de Reactivos:
 *                 listado con KPIs/materias normalizadas, alta con guardar-y-nuevo,
 *                 edición in-situ vs Copy-On-Write, captura rápida en lote,
 *                 paráfrasis anti-copia, plantillas CSV/XLSX y preview dry-run.
 * ============================================================================
 */

namespace App\Http\Controllers;

use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use App\Http\Requests\QuestionBatchStoreRequest;
use App\Http\Requests\QuestionImportRequest;
use App\Http\Requests\QuestionParaphraseStoreRequest;
use App\Http\Requests\QuestionStoreRequest;
use App\Http\Requests\QuestionUpdateRequest;
use App\Http\Requests\SubjectMergeRequest;
use App\Http\Requests\SubjectSuggestRequest;
use App\Models\Question;
use App\Models\Subject;
use App\Services\QuestionBankService;
use App\Services\SubscriptionLimitService;

class QuestionController extends Controller
{
    public function __construct(
        private readonly QuestionBankService $questionBankService,
        private readonly SubscriptionLimitService $subscriptionLimitService,
    ) {
    }

    /**
     * Listado paginado del banco de reactivos con filtros, KPIs y materias normalizadas.
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'search',
            'question_type',
            'subject_tag',
            'subject_id',
            'paraphrase_status',
            'sort_by',
        ]);

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50], true) ? $perPage : 20;

        $teacher = $request->user();
        $questions = $this->questionBankService->paginateForTeacher($teacher, $filters, $perPage);
        $subjects = $this->questionBankService->getDistinctSubjectsForTeacher($teacher);
        $dashboard = $this->questionBankService->getSubjectsDashboard($teacher);

        return view('questions.index', [
            'questions' => $questions,
            'subjects' => $subjects,
            'subjectsStats' => $dashboard['subjects'],
            'summaryMetrics' => $dashboard['summary'],
            'quotaSummary' => $dashboard['quota'],
            'filters' => $filters,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Formulario de creación de reactivo con autocompletado de materias y valores previos.
     */
    public function create(Request $request): View
    {
        $teacher = $request->user();
        $subjects = $this->questionBankService->getDistinctSubjectsForTeacher($teacher);
        $quotaSummary = $this->subscriptionLimitService->getQuestionQuotaSummary($teacher);

        $requestedType = (string) $request->query('question_type', 'multiple_choice');
        $defaultValues = [
            'subject_tag' => Subject::cleanDisplayName((string) $request->query('subject_tag', '')) ?? '',
            'question_type' => in_array($requestedType, Question::VALID_TYPES, true) ? $requestedType : 'multiple_choice',
            'default_time_seconds' => max(5, min(3600, (int) $request->query('default_time_seconds', 60))),
            'default_points' => is_numeric($request->query('default_points'))
                ? round(max(0.1, min(100.0, (float) $request->query('default_points'))), 2)
                : 2.0,
        ];

        return view('questions.create', compact('subjects', 'quotaSummary', 'defaultValues'));
    }

    /**
     * Persistencia atómica de un nuevo reactivo polimórfico (con soporte guardar-y-nuevo).
     */
    public function store(QuestionStoreRequest $request): RedirectResponse
    {
        $question = $this->questionBankService->createQuestion($request->user(), $request->validated());

        if ($request->boolean('save_and_new')) {
            return redirect()
                ->route('questions.create', [
                    'subject_tag' => $question->subject_tag,
                    'question_type' => $question->question_type,
                    'default_time_seconds' => $question->default_time_seconds,
                    'default_points' => $question->default_points,
                ])
                ->with('status', "Reactivo '{$question->title}' guardado. Continúa capturando el siguiente.");
        }

        return redirect()->route('questions.index')->with('status', 'Reactivo creado exitosamente.');
    }

    /**
     * Formulario de captura rápida en lote.
     */
    public function batchCreate(Request $request): View
    {
        $teacher = $request->user();
        $subjects = $this->questionBankService->getDistinctSubjectsForTeacher($teacher);
        $quotaSummary = $this->subscriptionLimitService->getQuestionQuotaSummary($teacher);
        $maxItems = QuestionBankService::MAX_BATCH_CREATE_ITEMS;

        return view('questions.batch-create', compact('subjects', 'quotaSummary', 'maxItems'));
    }

    /**
     * Persistencia atómica de captura rápida en lote.
     */
    public function batchStore(QuestionBatchStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $created = $this->questionBankService->createQuestionBatch($request->user(), $data['items']);

        return redirect()
            ->route('questions.index')
            ->with('status', "Se registraron {$created->count()} reactivos en lote exitosamente.");
    }

    /**
     * Vista de detalle del reactivo con su variante polimórfica, linaje y paráfrasis.
     */
    public function show(Request $request, Question $question): View
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $question->load(['questionable', 'paraphrases', 'parent', 'children', 'subject']);
        $isLinkedToExam = $this->questionBankService->isQuestionLinkedToExams($question);
        $nextVersionNumber = $isLinkedToExam
            ? $this->questionBankService->getNextVersionNumber($question)
            : 1;

        return view('questions.show', [
            'question' => $question,
            'isLinkedToExam' => $isLinkedToExam,
            'isLinkedToExams' => $isLinkedToExam,
            'nextVersionNumber' => $nextVersionNumber,
        ]);
    }

    /**
     * Formulario de edición del reactivo con estructuras precalculadas desde servicio.
     */
    public function edit(Request $request, Question $question): View
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $state = $this->questionBankService->buildEditFormState($question, $request->old());
        $subjects = $this->questionBankService->getDistinctSubjectsForTeacher($request->user());

        return view('questions.edit', [
            'question' => $question,
            'subjects' => $subjects,
            'initialOptions' => $state['initialOptions'],
            'initialCorrectOption' => $state['initialCorrectOption'],
            'initialPairs' => $state['initialPairs'],
            'initialIsFindError' => $state['initialIsFindError'],
            'nextVersionNumber' => $state['nextVersionNumber'],
            'isLinkedToExam' => $state['isLinkedToExam'],
            'isLinkedToExams' => $state['isLinkedToExam'],
        ]);
    }

    /**
     * Actualización de reactivo (in-situ o Copy-On-Write si está vinculado a exámenes históricos).
     */
    public function update(QuestionUpdateRequest $request, Question $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $result = $this->questionBankService->updateQuestion($request->user(), $question, $request->validated());
        $target = $result['question'];
        $versionNumber = (int) $result['version_number'];

        $message = $result['version_created']
            ? "Se generó una nueva versión (v{$versionNumber}) del reactivo debido a que el original ya está vinculado a exámenes históricos."
            : 'Reactivo actualizado exitosamente.';

        return redirect()->route('questions.show', $target)->with('status', $message);
    }

    /**
     * Eliminación segura (Soft Delete) protegiendo exámenes publicados o con intentos.
     */
    public function destroy(Request $request, Question $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        try {
            $this->questionBankService->deleteQuestion($request->user(), $question);
        } catch (DomainException $e) {
            return back()->withErrors(['question' => $e->getMessage()]);
        }

        return redirect()->route('questions.index')->with('status', 'Reactivo eliminado del banco exitosamente.');
    }

    /**
     * Duplica un reactivo existente del docente y permite edición rápida de la copia.
     */
    public function duplicate(Request $request, Question $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $clone = $this->questionBankService->duplicateQuestion($request->user(), $question);

        return redirect()
            ->route('questions.index')
            ->with('status', "Reactivo duplicado exitosamente como '{$clone->title}'.")
            ->with('duplicated_question_id', $clone->id);
    }

    /**
     * Registra una paráfrasis anti-copia (RF-BAN-010).
     */
    public function storeParaphrase(QuestionParaphraseStoreRequest $request, Question $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $data = $request->validated();
        $this->questionBankService->createParaphrase($request->user(), $question, (string) $data['stem']);

        return back()->with('status', 'Paráfrasis guardada.');
    }

    /**
     * Elimina una paráfrasis anti-copia verificando pertenencia estricta al reactivo [MEDIA-02].
     */
    public function destroyParaphrase(Request $request, Question $question, int $paraphrase): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        try {
            $this->questionBankService->deleteParaphrase($request->user(), $question, $paraphrase);
        } catch (DomainException $e) {
            return back()->withErrors(['paraphrase' => $e->getMessage()]);
        }

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
     * Descarga la plantilla institucional Excel (.xlsx) para importación masiva.
     */
    public function downloadExcelTemplate(): Response
    {
        $xlsx = $this->questionBankService->generateExcelTemplate();

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="plantilla_reactivos_acode.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Previsualización Dry-Run de archivo CSV/TXT/XLSX vía JSON antes de confirmar carga.
     */
    public function previewImport(QuestionImportRequest $request): JsonResponse
    {
        $file = $request->file('csv_file');
        $preview = $this->questionBankService->previewBatchImport($request->user(), $file);

        return response()->json($preview);
    }

    /**
     * Importa reactivos masivamente de forma atómica desde CSV, TXT o Excel (.xlsx).
     */
    public function import(QuestionImportRequest $request): RedirectResponse
    {
        $file = $request->file('csv_file');
        $result = $this->questionBankService->commitBatchImport($request->user(), $file);

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

    /**
     * Fusiona materias duplicadas del docente bajo un nombre canónico.
     */
    public function mergeSubjects(SubjectMergeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $merged = $this->questionBankService->mergeSubjects(
            $request->user(),
            $data['source_subject_ids'],
            (string) $data['target_name']
        );

        return redirect()
            ->route('questions.index', ['subject_id' => $merged->id])
            ->with('status', "Materias fusionadas correctamente bajo '{$merged->name}'.");
    }

    /**
     * Sugerencias JSON de materias similares para evitar duplicados tipográficos.
     */
    public function suggestSubjects(SubjectSuggestRequest $request): JsonResponse
    {
        $data = $request->validated();
        $suggestions = $this->questionBankService
            ->suggestSubjectsForTeacher($request->user(), (string) $data['name'])
            ->map(static fn (Subject $subject): array => [
                'id' => $subject->id,
                'name' => $subject->name,
            ])
            ->values();

        return response()->json(['suggestions' => $suggestions]);
    }
}
