<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamController.php
 * Ubicación:      app/Http/Controllers/ExamController.php
 * Requerimiento:  RF-EXA (Armado, Configuración y Publicación, RW-12 a RW-27)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Controlador delgado (Skinny Controller) de exámenes docentes:
 *                 tablero con KPIs y filtros SQL, CRUD seguro de borradores,
 *                 armado dual (sorteo con inventario + selección manual),
 *                 edición/borrado de apartados, RW-14, configuración base 100,
 *                 checklist pre-publicación 5/5 y control de ciclo de vida.
 * ============================================================================
 */

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

use App\Http\Requests\ExamConfigurationUpdateRequest;
use App\Http\Requests\ExamManualQuestionsStoreRequest;
use App\Http\Requests\ExamSectionStoreRequest;
use App\Http\Requests\ExamSectionUpdateRequest;
use App\Http\Requests\ExamStoreRequest;
use App\Http\Requests\ExamUpdateRequest;
use App\Http\Requests\QuestionUpdateRequest;
use App\Models\EvaluationPeriod;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Repositories\Contracts\GroupRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Services\ExamAssemblyService;
use App\Services\ExamNotificationService;
use App\Services\QuestionBankService;

/**
 * Controlador web docente para el ciclo completo de exámenes.
 *
 * @package App\Http\Controllers
 */
class ExamController extends Controller
{
    public function __construct(
        private readonly ExamRepositoryInterface $examRepository,
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly ExamAssemblyService $examAssemblyService,
        private readonly ExamNotificationService $examNotifications,
        private readonly QuestionBankService $questionBankService,
    ) {
    }

    /**
     * Listado paginado de exámenes del docente con KPIs y filtros en SQL.
     */
    public function index(Request $request): View|Response
    {
        $teacherId = (int) $request->user()->id;
        $filters = $request->only(['search', 'status', 'group_id']);

        $exams = $this->examRepository->paginateForTeacher($teacherId, 15, $filters);
        $stats = $this->examRepository->getTeacherExamStats($teacherId);
        $groups = $this->groupRepository->getForTeacher($teacherId);

        $view = view('exams.index', [
            'exams' => $exams,
            'stats' => $stats,
            'groups' => $groups,
            'filters' => $filters,
        ]);

        if ($request->header('X-Catalog-Fragment') === 'exam-search-results') {
            return response($view->fragment('exam-search-results'))
                ->header('Cache-Control', 'no-store');
        }

        return $view;
    }

    /**
     * Formulario de creación de examen con grupos abiertos seleccionables.
     */
    public function create(Request $request): View
    {
        $teacherId = (int) $request->user()->id;
        $groups = $this->groupRepository->getSelectableForTeacher($teacherId);
        $groupId = filter_var($request->query('group_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $defaultGroupIds = ! $request->session()->hasOldInput() && $groupId !== false ? [$groupId] : [];
        $selectedGroupIds = array_values(array_intersect(
            array_map('intval', (array) old('group_ids', $defaultGroupIds)),
            $groups->modelKeys(),
        ));
        $hasOpenGroups = $groups->where('is_closed', false)->isNotEmpty();

        return view('exams.create', [
            'groups' => $groups,
            'selectedGroupIds' => $selectedGroupIds,
            'hasOpenGroups' => $hasOpenGroups,
            'examGroupScheduling' => $this->examGroupScheduling($groups),
        ]);
    }

    /**
     * Los periodos abarcan días completos en la zona de la aplicación.
     *
     * @param Collection<int, Group> $groups
     * @return array<int, array{scale: string|null, periods: list<array{id: int, name: string, startsAt: string, endsAt: string}>}>
     */
    private function examGroupScheduling(Collection $groups): array
    {
        $groups->load(['evaluationPeriods' => static fn ($query) => $query
            ->select('id', 'group_id', 'name', 'starts_at', 'ends_at')
            ->where('ends_at', '>=', today())
            ->orderBy('starts_at')
            ->orderBy('id')]);

        return $groups->mapWithKeys(static fn (Group $group): array => [$group->id => [
            'scale' => in_array($group->grading_scale, ['0-10', '0-100'], true) ? $group->grading_scale : null,
            'periods' => $group->evaluationPeriods->map(static fn (EvaluationPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'startsAt' => $period->starts_at->toDateString() . 'T00:00',
                'endsAt' => $period->ends_at->toDateString() . 'T23:59',
            ])->all(),
        ]])->all();
    }

    /**
     * Alta transaccional de examen en borrador con control de cuota SaaS.
     */
    public function store(ExamStoreRequest $request): RedirectResponse
    {
        $exam = $this->examAssemblyService->createExam($request->user(), $request->validated());

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Examen creado. Ahora arma los apartados.');
    }

    /**
     * Formulario de edición de metadatos, ventana y grupos del examen.
     */
    public function edit(Exam $exam): View
    {
        $this->authorizeExam($exam);

        $detailedExam = $this->examRepository->findForTeacherWithDetails((int) $exam->id, (int) $exam->teacher_id) ?? $exam;
        $assignedGroupIds = $detailedExam->groups
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $groups = $this->groupRepository->getSelectableForTeacher((int) $detailedExam->teacher_id, $assignedGroupIds);
        $selectedGroupIds = array_map('intval', (array) old('group_ids', $assignedGroupIds));
        $hasOpenGroups = $groups->where('is_closed', false)->isNotEmpty();

        return view('exams.edit', [
            'exam' => $detailedExam,
            'groups' => $groups,
            'selectedGroupIds' => $selectedGroupIds,
            'hasOpenGroups' => $hasOpenGroups,
            'assemblyBlockReason' => $this->assemblyBlockReason($detailedExam),
        ]);
    }

    /**
     * Actualiza los datos base, ventana y grupos de un examen.
     */
    public function update(ExamUpdateRequest $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $this->examAssemblyService->updateDraftExam($exam, $request->validated());

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Datos del examen actualizados correctamente.');
    }

    /**
     * Elimina de forma atómica un examen en borrador sin intentos registrados.
     */
    public function destroy(Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $this->examAssemblyService->deleteDraftExam($exam);

        return redirect()
            ->route('exams.index')
            ->with('status', 'Borrador de examen eliminado correctamente.');
    }

    /**
     * Vista de armado dual (sorteo con inventario real + selección manual desde banco).
     */
    public function assemble(Request $request, Exam $exam): View|Response
    {
        $this->authorizeExam($exam);

        $detailedExam = $this->examRepository->findForTeacherWithDetails((int) $exam->id, (int) $exam->teacher_id) ?? $exam;
        $excludedIds = $detailedExam->examQuestions
            ->pluck('question_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $catalogFragment = $request->header('X-Catalog-Fragment') === 'manual-candidate-results';
        $bankInventory = $catalogFragment ? [] : $this->questionRepository->getAvailableInventoryForTeacher(
            (int) $detailedExam->teacher_id,
            $excludedIds,
        );

        $sessionKey = "exam_section_proposal_{$detailedExam->id}";
        $proposal = $request->session()->get($sessionKey);
        $proposedQuestions = collect();
        $proposalWarning = null;

        if (! $catalogFragment && is_array($proposal) && ! empty($proposal['question_ids'])) {
            $sanitizedIds = $this->questionRepository->filterAvailableQuestionIds(
                (int) $detailedExam->teacher_id,
                array_map('intval', $proposal['question_ids']),
                $excludedIds
            );

            if ($sanitizedIds === []) {
                $request->session()->forget($sessionKey);
                $proposal = null;
                $proposalWarning = 'Ningún reactivo de la propuesta sigue disponible. Crea otra propuesta o usa la selección manual.';
            } else {
                $requestedQuantity = (int) ($proposal['requested_quantity'] ?? count($proposal['question_ids']));
                if (count($sanitizedIds) < $requestedQuantity) {
                    $availableQuantity = count($sanitizedIds);
                    $proposalWarning = "Quedan {$availableQuantity} de {$requestedQuantity} reactivos de la propuesta. Si la aceptas, solo se agregarán los que aparecen en la lista.";
                }
                $proposal['question_ids'] = $sanitizedIds;
                $request->session()->put($sessionKey, $proposal);

                $questions = $this->questionBankService->getQuestionsByIdsForTeacher(
                    (int) $detailedExam->teacher_id,
                    $sanitizedIds
                );

                $proposedQuestions = collect($sanitizedIds)
                    ->map(static fn (int $id) => $questions->get($id))
                    ->filter()
                    ->values();
            }
        }

        $manualFilters = $request->only(['question_type', 'subject_id', 'subject_tag', 'search', 'sort_by']);
        $manualCandidates = $this->questionRepository->getAvailableCandidatesPaginated(
            (int) $detailedExam->teacher_id,
            $excludedIds,
            $manualFilters,
            12
        );

        $subjects = $catalogFragment ? [] : $this->questionRepository->getDistinctSubjectsForTeacher((int) $detailedExam->teacher_id);
        $canEditStructure = $detailedExam->supportsStructuralEdit();

        $view = view('exams.assemble', [
            'exam' => $detailedExam,
            'bankInventory' => $bankInventory,
            'proposal' => $proposal,
            'proposalWarning' => $proposalWarning,
            'proposedQuestions' => $proposedQuestions,
            'manualCandidates' => $manualCandidates,
            'subjects' => $subjects,
            'subjectTags' => $subjects,
            'manualFilters' => $manualFilters,
            'canEditStructure' => $canEditStructure,
            'assemblyBlockReason' => $this->assemblyBlockReason($detailedExam),
        ]);

        if ($catalogFragment) {
            return response($view->fragment('assembly-manual-results'))->header('Cache-Control', 'no-store');
        }

        return $view;
    }

    /**
     * Genera una nueva propuesta aleatoria preservando cualquier propuesta previa
     * en sesión si la generación actual falla por falta de reactivos.
     */
    public function generateSectionProposal(ExamSectionStoreRequest $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $data = $request->validated();
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;

        $questionIds = $this->examAssemblyService->generateProposal(
            $exam,
            (string) $data['question_type'],
            (int) $data['requested_quantity'],
            $data['subject_tag'] ?? null,
            $subjectId
        );

        $sessionKey = "exam_section_proposal_{$exam->id}";
        $request->session()->put($sessionKey, [
            'title' => $data['title'],
            'question_type' => $data['question_type'],
            'subject_tag' => $data['subject_tag'] ?? null,
            'subject_id' => $subjectId,
            'requested_quantity' => (int) $data['requested_quantity'],
            'question_ids' => $questionIds,
        ]);

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Propuesta generada. Revísala antes de aceptarla.');
    }

    /**
     * Persiste la propuesta pendiente en sesión como un nuevo apartado del examen.
     */
    public function acceptSectionProposal(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $sessionKey = "exam_section_proposal_{$exam->id}";
        $proposal = $request->session()->get($sessionKey);

        if (! is_array($proposal) || empty($proposal['question_ids'])) {
            return redirect()
                ->route('exams.assemble', $exam)
                ->withErrors([
                    'proposal' => 'No hay una propuesta pendiente de aceptación.',
                ]);
        }

        $this->examAssemblyService->acceptProposal(
            $exam,
            (string) $proposal['title'],
            (string) $proposal['question_type'],
            $proposal['question_ids'],
            $proposal['subject_tag'] ?? null,
            isset($proposal['subject_id']) ? (int) $proposal['subject_id'] : null
        );

        $request->session()->forget($sessionKey);

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Apartado y reactivos guardados correctamente.');
    }

    /**
     * Descarta la propuesta pendiente almacenada en sesión.
     */
    public function discardSectionProposal(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $request->session()->forget("exam_section_proposal_{$exam->id}");

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Propuesta descartada.');
    }

    /**
     * Crea un apartado y sortea automáticamente sus reactivos en una sola acción.
     */
    public function storeSection(ExamSectionStoreRequest $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $data = $request->validated();
        $section = $this->examAssemblyService->createSectionAndDraw($exam, $data);

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', "Apartado \"{$section->title}\" armado con {$data['requested_quantity']} reactivos.");
    }

    /**
     * Actualiza el título o materia de un apartado existente del examen.
     */
    public function updateSection(ExamSectionUpdateRequest $request, Exam $exam, ExamSection $examSection): RedirectResponse
    {
        $this->authorizeExam($exam);
        abort_if((int) $examSection->exam_id !== (int) $exam->id, 404);

        $this->examAssemblyService->updateSection($exam, $examSection, $request->validated());

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Apartado actualizado correctamente.');
    }

    /**
     * Elimina un apartado completo del examen y reordena los restantes.
     */
    public function destroySection(Exam $exam, ExamSection $examSection): RedirectResponse
    {
        $this->authorizeExam($exam);
        abort_if((int) $examSection->exam_id !== (int) $exam->id, 404);

        $this->examAssemblyService->deleteSection($exam, $examSection);

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Apartado eliminado correctamente.');
    }

    /**
     * Agrega manualmente reactivos seleccionados del banco hacia un apartado.
     */
    public function storeManualQuestions(ExamManualQuestionsStoreRequest $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $section = $this->examAssemblyService->addManualQuestions(
            $exam,
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', "Reactivos agregados correctamente al apartado \"{$section->title}\".")
            ->with('manual_selection_saved', true);
    }

    /**
     * Sustituye puntualmente un reactivo del apartado preservando peso y tiempo (RW-14).
     */
    public function reshuffleQuestion(Exam $exam, ExamQuestion $examQuestion): RedirectResponse
    {
        $this->authorizeExam($exam);
        abort_if((int) $examQuestion->exam_id !== (int) $exam->id, 404);

        $this->examAssemblyService->reshuffleQuestion($examQuestion);

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Reactivo sustituido. Se conservaron sus puntos y su tiempo límite.');
    }

    /**
     * Remueve un reactivo asignado de su apartado.
     */
    public function destroyQuestion(Exam $exam, ExamQuestion $examQuestion): RedirectResponse
    {
        $this->authorizeExam($exam);
        abort_if((int) $examQuestion->exam_id !== (int) $exam->id, 404);

        $this->examAssemblyService->removeExamQuestion($exam, $examQuestion);

        return redirect()
            ->route('exams.assemble', $exam)
            ->with('status', 'Reactivo removido del apartado correctamente.');
    }

    /**
     * Etapa 3: configuración de ponderaciones base 100.00, tiempos, grupos y reglas.
     */
    public function configuration(Exam $exam): View|RedirectResponse
    {
        $this->authorizeExam($exam);

        $detailedExam = $this->examRepository->findForTeacherWithDetails((int) $exam->id, (int) $exam->teacher_id) ?? $exam;
        if ($redirect = $this->redirectIfAssemblyBlocked($detailedExam)) {
            return $redirect;
        }

        $assignedGroupIds = $detailedExam->groups
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $groups = $this->groupRepository->getSelectableForTeacher((int) $detailedExam->teacher_id, $assignedGroupIds);
        $totalPoints = round((float) ($detailedExam->total_points_weight ?? $detailedExam->examQuestions->sum('points_weight')), 2);
        $hasAttempts = ((int) ($detailedExam->student_attempts_count ?? $detailedExam->studentAttempts()->count())) > 0;

        return view('exams.configuration', [
            'exam' => $detailedExam,
            'groups' => $groups,
            'totalPoints' => $totalPoints,
            'hasAttempts' => $hasAttempts,
            'allowsFreeNavigation' => app(\App\Services\SubscriptionLimitService::class)->allowsFreeNavigation(request()->user()),
        ]);
    }

    /**
     * Guarda la configuración transaccional de ponderaciones, tiempos y grupos.
     */
    public function updateConfiguration(ExamConfigurationUpdateRequest $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        if ($redirect = $this->redirectIfAssemblyBlocked($exam)) {
            return $redirect;
        }

        $this->examAssemblyService->updateConfiguration($exam, $request->validated());

        return redirect()
            ->route('exams.review', $exam)
            ->with('status', 'Configuración guardada correctamente.');
    }

    /**
     * Etapa 4: checklist de verificación 5/5 previa a la publicación del examen.
     */
    public function review(Exam $exam): View|RedirectResponse
    {
        $this->authorizeExam($exam);

        $detailedExam = $this->examRepository->findForTeacherWithDetails((int) $exam->id, (int) $exam->teacher_id) ?? $exam;
        if ($redirect = $this->redirectIfAssemblyBlocked($detailedExam)) {
            return $redirect;
        }

        $publishChecklist = $this->examAssemblyService->evaluatePublishReadiness($detailedExam);
        $totalPoints = (float) $publishChecklist['total_weight'];

        return view('exams.review', [
            'exam' => $detailedExam,
            'publishChecklist' => $publishChecklist,
            'totalPoints' => $totalPoints,
        ]);
    }

    /**
     * Publica el examen de manera idempotente validando cuota SaaS y checklist 5/5.
     */
    public function publish(Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        if ($redirect = $this->redirectIfAssemblyBlocked($exam, 'publish')) {
            return $redirect;
        }

        $this->examAssemblyService->publishExam($exam, $this->examNotifications);

        return redirect()
            ->route('exams.index')
            ->with('status', 'Examen publicado.');
    }

    /**
     * Devuelve un examen publicado a estado borrador si aún no tiene intentos.
     */
    public function unpublish(Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $this->examAssemblyService->unpublishExam($exam);

        return redirect()
            ->route('exams.index')
            ->with('status', 'Examen despublicado y devuelto a borrador.');
    }

    /**
     * Cierra anticipadamente la ventana de aplicación de un examen publicado.
     */
    public function close(Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $this->examAssemblyService->closeExamEarly($exam);

        return redirect()
            ->route('exams.index')
            ->with('status', 'Examen cerrado anticipadamente.');
    }

    /**
     * Libera los resultados ocultos del examen para consulta del alumno.
     */
    public function releaseResults(Exam $exam): RedirectResponse
    {
        $this->authorizeExam($exam);

        $this->examAssemblyService->releaseHiddenResults($exam);

        return back()->with('status', 'Resultados liberados para los estudiantes.');
    }

    /**
     * Verifica aislamiento multi-docente sobre la instancia del examen (RNF-SEG-004).
     */
    /**
     * Formulario para modificar una pregunta solo dentro de este examen (RF-EXA-005).
     */
    public function editQuestion(Request $request, Exam $exam, ExamQuestion $examQuestion): View
    {
        $this->authorizeExam($exam);
        abort_if((int) $examQuestion->exam_id !== (int) $exam->id, 404);
        $question = $examQuestion->question;
        abort_if($question === null || $question->trashed(), 404);

        $this->examAssemblyService->ensureStructureMutable($exam);

        $state = $this->questionBankService->buildEditFormState($question, $request->old());
        $returnToReview = $request->query('return_to') === 'review';

        return view('questions.edit', [
            'question' => $question,
            'subjects' => $this->questionBankService->getDistinctSubjectsForTeacher($request->user()),
            'initialOptions' => $state['initialOptions'],
            'initialCorrectOption' => $state['initialCorrectOption'],
            'initialPairs' => $state['initialPairs'],
            'initialIsFindError' => $state['initialIsFindError'],
            'nextVersionNumber' => $state['nextVersionNumber'],
            'isLinkedToExam' => true,
            'isLinkedToExams' => true,
            'examContext' => $exam,
            'formAction' => route('exams.questions.update', [
                'exam' => $exam,
                'examQuestion' => $examQuestion,
                'return_to' => $returnToReview ? 'review' : null,
            ]),
            'cancelUrl' => route($returnToReview ? 'exams.review' : 'exams.assemble', $exam),
        ]);
    }

    /**
     * Guarda la pregunta modificada como nueva versión usada por este examen;
     * la del banco no cambia (RF-EXA-005).
     */
    public function updateQuestion(QuestionUpdateRequest $request, Exam $exam, ExamQuestion $examQuestion): RedirectResponse
    {
        $this->authorizeExam($exam);
        abort_if((int) $examQuestion->exam_id !== (int) $exam->id, 404);

        $this->examAssemblyService->editExamQuestion(
            $exam,
            $examQuestion,
            $request->user(),
            $request->validated(),
            $this->questionBankService,
        );

        return redirect()
            ->route($request->query('return_to') === 'review' ? 'exams.review' : 'exams.assemble', $exam)
            ->with('status', 'Reactivo actualizado solo para este examen; la versión del banco no cambió.');
    }

    private function authorizeExam(Exam $exam): void
    {
        abort_unless((int) $exam->teacher_id === (int) request()->user()->id, 403);
    }

    private function assemblyBlockReason(Exam $exam): ?string
    {
        if ($exam->is_published) {
            return null;
        }

        $proposal = request()->session()->get("exam_section_proposal_{$exam->id}");
        if (is_array($proposal) && ! empty($proposal['question_ids'])) {
            return 'Acepta o cancela la propuesta de sorteo pendiente antes de continuar.';
        }

        $exam->loadMissing('sections.examQuestions');
        if ($exam->sections->isEmpty() || $exam->sections->every(fn (ExamSection $section): bool => $section->examQuestions->isEmpty())) {
            return 'Agrega al menos un reactivo a un apartado antes de continuar.';
        }

        if ($exam->sections->contains(fn (ExamSection $section): bool => $section->examQuestions->isEmpty())) {
            return 'Completa o elimina los apartados vacíos antes de continuar.';
        }

        return null;
    }

    private function redirectIfAssemblyBlocked(Exam $exam, string $errorKey = 'assembly'): ?RedirectResponse
    {
        $reason = $this->assemblyBlockReason($exam);
        if ($reason === null) {
            return null;
        }

        if (request()->expectsJson()) {
            throw ValidationException::withMessages([$errorKey => $reason]);
        }

        return redirect()->route('exams.assemble', $exam)->withErrors([$errorKey => $reason]);
    }
}
