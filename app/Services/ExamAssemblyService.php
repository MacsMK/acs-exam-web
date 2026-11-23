<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamAssemblyService.php
 * Ubicación:   app/Services/ExamAssemblyService.php
 * Descripción: Orquestación transaccional del ciclo de vida y armado dual
 *              de exámenes (RW-12 a RW-27): creación con cuota SaaS, edición
 *              segura, sorteo con linaje Copy-On-Write, asignación manual,
 *              re-sorteo puntual (RW-14), checklist 5/5, publicación
 *              idempotente y secuencia ordenada por apartados.
 * ============================================================================
 */

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\Question;
use App\Models\Scopes\TenantTeacherScope;
use App\Models\StudentAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;

class ExamAssemblyService
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly ExamRepositoryInterface $examRepository,
        private readonly SubscriptionLimitService $subscriptionLimitService,
    ) {
    }

    /**
     * Crea un examen en borrador bajo bloqueo pesimista de cuota SaaS del docente
     * y sincroniza sus grupos asignados dentro de una transacción.
     *
     * @param array<string, mixed> $data
     */
    public function createExam(User $teacher, array $data): Exam
    {
        return DB::transaction(function () use ($teacher, $data): Exam {
            $lockedTeacher = User::query()
                ->with('subscription.subscriptionPackage')
                ->whereKey($teacher->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->subscriptionLimitService->ensureCanCreateExam($lockedTeacher);

            $groupIds = array_values(array_unique(array_filter(
                array_map('intval', $data['group_ids'] ?? []),
                static fn (int $id): bool => $id > 0
            )));

            return $this->examRepository->createForTeacher(
                (int) $lockedTeacher->id,
                [
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'grading_scale' => $data['grading_scale'],
                    'randomize_question_order' => (bool) ($data['randomize_question_order'] ?? true),
                    'result_visibility_mode' => $data['result_visibility_mode'],
                    'require_webcam' => (bool) ($data['require_webcam'] ?? false),
                    'max_allowed_incidents' => (int) $data['max_allowed_incidents'],
                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                ],
                $groupIds
            );
        });
    }

    /**
     * Actualiza metadatos, ventana horaria y grupos de un examen protegiendo
     * inmutabilidad de escala/orden cuando ya está publicado o tiene intentos.
     *
     * @param array<string, mixed> $data
     */
    public function updateDraftExam(Exam $exam, array $data): Exam
    {
        return DB::transaction(function () use ($exam, $data): Exam {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedExam->is_published) {
                if (isset($data['grading_scale']) && (string) $data['grading_scale'] !== (string) $lockedExam->grading_scale) {
                    throw ValidationException::withMessages([
                        'grading_scale' => ['No puedes cambiar la escala de calificación de un examen publicado'],
                    ]);
                }

                if (array_key_exists('randomize_question_order', $data)
                    && (bool) $data['randomize_question_order'] !== (bool) $lockedExam->randomize_question_order
                ) {
                    throw ValidationException::withMessages([
                        'randomize_question_order' => ['No puedes cambiar el orden estructural de un examen publicado'],
                    ]);
                }

                if ($lockedExam->isWindowClosed()) {
                    throw ValidationException::withMessages([
                        'exam' => ['No es posible modificar un examen cuya ventana de aplicación ya concluyó'],
                    ]);
                }

                if ($lockedExam->hasStartedWindow()) {
                    unset($data['starts_at']);
                }

                unset($data['grading_scale'], $data['randomize_question_order']);
            } elseif ($lockedExam->studentAttempts()->exists()
                && isset($data['grading_scale'])
                && (string) $data['grading_scale'] !== (string) $lockedExam->grading_scale
            ) {
                throw ValidationException::withMessages([
                    'grading_scale' => ['No puedes cambiar la escala de calificación porque el examen ya tiene intentos registrados'],
                ]);
            }

            $groupIds = array_values(array_unique(array_filter(
                array_map('intval', $data['group_ids'] ?? []),
                static fn (int $id): bool => $id > 0
            )));

            $this->assertSelectableGroupsForExam($lockedExam, $groupIds);

            $payload = collect($data)->except(['group_ids'])->all();
            if (array_key_exists('require_webcam', $payload)) {
                $payload['require_webcam'] = (bool) ($payload['require_webcam'] ?? false);
            }

            return $this->examRepository->updateExam($lockedExam, $payload, $groupIds);
        });
    }

    /**
     * Elimina transaccionalmente un borrador de examen que no haya sido
     * publicado ni tenga intentos registrados.
     */
    public function deleteDraftExam(Exam $exam): bool
    {
        return DB::transaction(function () use ($exam): bool {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedExam->is_published) {
                throw ValidationException::withMessages([
                    'exam' => ['No es posible eliminar un examen publicado; despublícalo primero si aún no tiene intentos'],
                ]);
            }

            if ($lockedExam->studentAttempts()->exists()) {
                throw ValidationException::withMessages([
                    'exam' => ['No es posible eliminar un examen que ya tiene intentos registrados'],
                ]);
            }

            return $this->examRepository->deleteDraftExam($lockedExam);
        });
    }

    /**
     * Evalúa las 5 reglas obligatorias de publicación previa del examen.
     *
     * @return array{
     *     can_publish: bool,
     *     passed_count: int,
     *     total_checks: int,
     *     has_sections: bool,
     *     no_empty_sections: bool,
     *     exact_weight: bool,
     *     has_open_group: bool,
     *     has_future_window: bool,
     *     checks: array{has_sections: bool, no_empty_sections: bool, exact_weight: bool, has_open_group: bool, has_future_window: bool},
     *     total_weight: float,
     *     sections_count: int,
     *     open_groups_count: int,
     *     messages: list<string>
     * }
     */
    public function evaluatePublishReadiness(Exam $exam): array
    {
        $exam->loadMissing(['groups', 'sections.examQuestions.question.questionable']);

        $sectionsCount = $exam->sections->count();
        $emptySections = $exam->sections->filter(static fn (ExamSection $section): bool => $section->examQuestions->isEmpty());
        // RF-EXA-019: cada pregunta necesita tiempo y respuesta de referencia válidos
        $incompleteQuestions = $exam->sections->flatMap->examQuestions
            ->reject(fn (ExamQuestion $examQuestion): bool => $this->examQuestionIsComplete($examQuestion));
        $totalWeight = round((float) $exam->sections->flatMap->examQuestions->sum('points_weight'), 2);
        $openGroupsCount = $exam->groups->where('is_closed', false)->count();
        $hasFutureWindow = $exam->ends_at !== null && $exam->ends_at->gt(now());

        $checks = [
            'has_sections' => $sectionsCount >= 1,
            'no_empty_sections' => $sectionsCount >= 1 && $emptySections->isEmpty() && $incompleteQuestions->isEmpty(),
            'exact_weight' => abs($totalWeight - 100.0) <= 0.01,
            'has_open_group' => $openGroupsCount >= 1,
            'has_future_window' => $hasFutureWindow,
        ];

        $messages = [];
        if (! $checks['has_sections']) {
            $messages[] = 'Agrega al menos un apartado con reactivos en la etapa Armar';
        } elseif (! $checks['no_empty_sections']) {
            $emptyTitles = $emptySections->pluck('title')->implode(', ');
            $messages[] = "Elimina o asigna reactivos a los apartados vacíos ({$emptyTitles})";
        }

        if ($incompleteQuestions->isNotEmpty()) {
            $incompleteTitles = $incompleteQuestions
                ->map(static fn (ExamQuestion $examQuestion): string => (string) ($examQuestion->question?->title ?? 'Reactivo no disponible'))
                ->implode(', ');
            $messages[] = "Revisa el tiempo y la respuesta de referencia de: {$incompleteTitles}";
        }

        if (! $checks['exact_weight']) {
            $formattedWeight = number_format($totalWeight, 2, '.', '');
            $messages[] = "La suma de ponderaciones actual es {$formattedWeight} pts y debe ser exactamente 100.00 pts";
        }

        if (! $checks['has_open_group']) {
            $messages[] = 'Asigna al menos un grupo escolar abierto al examen antes de publicar';
        }

        if (! $checks['has_future_window']) {
            $messages[] = 'La fecha de cierre del examen debe ser posterior al momento actual';
        }

        $passedCount = count(array_filter($checks));

        return [
            'can_publish' => $passedCount === 5,
            'passed_count' => $passedCount,
            'total_checks' => 5,
            'has_sections' => $checks['has_sections'],
            'no_empty_sections' => $checks['no_empty_sections'],
            'exact_weight' => $checks['exact_weight'],
            'has_open_group' => $checks['has_open_group'],
            'has_future_window' => $checks['has_future_window'],
            'checks' => $checks,
            'total_weight' => $totalWeight,
            'sections_count' => $sectionsCount,
            'open_groups_count' => $openGroupsCount,
            'messages' => $messages,
        ];
    }

    /**
     * Publica un examen de forma idempotente bajo bloqueos pesimistas de examen y
     * docente, validando cuota SaaS, checklist 5/5 y enviando notificaciones push
     * únicamente tras confirmar la transacción (DB::afterCommit).
     */
    public function publishExam(Exam $exam, ExamNotificationService $notifications): Exam
    {
        $publishedExam = DB::transaction(function () use ($exam, $notifications): ?Exam {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedExam->is_published) {
                return null;
            }

            $lockedTeacher = User::query()
                ->with('subscription.subscriptionPackage')
                ->whereKey($lockedExam->teacher_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->subscriptionLimitService->ensureCanPublishExam($lockedTeacher, $lockedExam);

            $readiness = $this->evaluatePublishReadiness($lockedExam);
            if (! $readiness['can_publish']) {
                throw ValidationException::withMessages([
                    'publish' => $readiness['messages'],
                ]);
            }

            $published = $this->examRepository->publish($lockedExam);

            DB::afterCommit(static function () use ($notifications, $published): void {
                $notifications->examPublished($published);
            });

            return $published;
        });

        return $publishedExam ?? $exam->fresh();
    }

    /**
     * Revierte la publicación del examen a estado borrador únicamente si tiene 0 intentos.
     */
    public function unpublishExam(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam): Exam {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedExam->is_published) {
                return $lockedExam;
            }

            if ($lockedExam->studentAttempts()->exists()) {
                throw ValidationException::withMessages([
                    'exam' => ['No puedes despublicar un examen que ya tiene intentos registrados de estudiantes'],
                ]);
            }

            return $this->examRepository->unpublish($lockedExam);
        });
    }

    /**
     * Cierra anticipadamente la ventana de un examen publicado y vigente.
     */
    public function closeExamEarly(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam): Exam {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedExam->is_published) {
                throw ValidationException::withMessages([
                    'exam' => ['Solo puedes cerrar anticipadamente un examen publicado'],
                ]);
            }

            if ($lockedExam->isWindowClosed()) {
                throw ValidationException::withMessages([
                    'exam' => ['La ventana de aplicación de este examen ya ha concluido'],
                ]);
            }

            $closed = $this->examRepository->closeEarly($lockedExam);

            // RF-EXA-024: detener la aplicación finaliza los intentos en curso conservando
            // sus respuestas; sin esto seguirían abiertos por la regla de RF-EXA-050.
            $stoppedIds = StudentAttempt::query()
                ->where('exam_id', $closed->id)
                ->where('status', 'in_progress')
                ->pluck('id');

            StudentAttempt::query()
                ->whereIn('id', $stoppedIds)
                ->update(['status' => 'expired', 'submitted_at' => now()]);

            // RF-EXA-024: la causa de finalización queda en la bitácora de cada intento
            foreach ($stoppedIds as $attemptId) {
                AuditLog::query()->create([
                    'user_id' => auth()->id(),
                    'action' => StudentAttempt::ACTION_STOPPED_BY_TEACHER,
                    'auditable_type' => StudentAttempt::class,
                    'auditable_id' => $attemptId,
                    'new_values' => ['status' => 'expired', 'cause' => 'Detención del examen por el docente'],
                ]);
            }

            return $closed;
        });
    }

    /**
     * Libera los resultados ocultos del examen cambiando su modo a calificación final visible.
     */
    public function releaseHiddenResults(Exam $exam): Exam
    {
        return DB::transaction(static function () use ($exam): Exam {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedExam->update(['result_visibility_mode' => 'final_score']);

            return $lockedExam->fresh();
        });
    }

    /**
     * Una pregunta del examen está completa si tiene tiempo positivo y su
     * respuesta de referencia según el tipo (RF-EXA-019).
     */
    private function examQuestionIsComplete(ExamQuestion $examQuestion): bool
    {
        $question = $examQuestion->question;
        $item = $question?->questionable;

        if ($item === null || (int) $examQuestion->time_limit_seconds <= 0) {
            return false;
        }

        return match ($question->question_type) {
            'multiple_choice' => $item->correct_option_index !== null && ! empty($item->options_payload),
            'matching' => ! empty($item->pairs_payload),
            'open' => trim((string) $item->reference_answer) !== '',
            'code' => trim((string) $item->reference_solution) !== '',
            default => false,
        };
    }

    /**
     * Modifica una pregunta del examen sin cambiar la del banco (RF-EXA-005):
     * se guarda como nueva versión (RF-BAN-010) y este examen pasa a usarla.
     * Solo mientras la estructura sea editable (antes de iniciar la aplicación
     * y sin intentos) y conservando la validez si ya está programado.
     *
     * @param array<string, mixed> $data
     */
    public function editExamQuestion(
        Exam $exam,
        ExamQuestion $examQuestion,
        User $teacher,
        array $data,
        QuestionBankService $questionBank,
    ): ExamQuestion {
        return DB::transaction(function () use ($exam, $examQuestion, $teacher, $data, $questionBank): ExamQuestion {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureStructureMutable($lockedExam);

            $result = $questionBank->updateQuestion($teacher, $examQuestion->question, $data);

            if ($result['version_created']) {
                $examQuestion->update(['question_id' => $result['question']->id]);
            }

            $this->ensureStillPublishable($lockedExam);

            return $examQuestion->fresh(['question']);
        });
    }

    /**
     * Si el examen ya está programado, comprueba de nuevo que siga cumpliendo
     * las condiciones de publicación antes de guardar un cambio; si no, el
     * cambio se revierte (RF-EXA-020).
     *
     * @throws ValidationException
     */
    private function ensureStillPublishable(Exam $exam): void
    {
        $fresh = Exam::query()->withoutGlobalScope(TenantTeacherScope::class)->find($exam->id);

        if ($fresh === null || ! $fresh->is_published) {
            return;
        }

        $readiness = $this->evaluatePublishReadiness($fresh);

        if (! $readiness['can_publish']) {
            throw ValidationException::withMessages([
                'exam' => ['El examen ya está programado y este cambio lo dejaría incompleto: '
                    . implode('; ', $readiness['messages'])
                    . '. Despublícalo para reorganizarlo o ajusta el cambio.'],
            ]);
        }
    }

    /**
     * Verifica que la estructura del examen (apartados y reactivos) admita edición.
     *
     * @throws ValidationException
     */
    public function ensureStructureMutable(Exam $exam): void
    {
        if ($exam->studentAttempts()->exists()) {
            throw ValidationException::withMessages([
                'exam' => ['No es posible modificar la estructura de un examen que ya tiene intentos registrados'],
            ]);
        }

        if ($exam->is_published && ($exam->hasStartedWindow() || $exam->isWindowClosed())) {
            throw ValidationException::withMessages([
                'exam' => ['No es posible modificar la estructura de un examen cuya aplicación ya inició o concluyó'],
            ]);
        }
    }

    /**
     * Crea un apartado con su filtro opcional de materia normalizada y sortea
     * sus reactivos dentro de una transacción.
     *
     * @param array<string, mixed> $data
     */
    public function createSectionAndDraw(Exam $exam, array $data): ExamSection
    {
        return DB::transaction(function () use ($exam, $data): ExamSection {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureStructureMutable($lockedExam);

            [$resolvedSubjectId, $resolvedSubjectTag] = $this->resolveSectionSubject(
                (int) $lockedExam->teacher_id,
                $data['subject_tag'] ?? null,
                isset($data['subject_id']) ? (int) $data['subject_id'] : null
            );

            $section = $this->examRepository->createSection($lockedExam, [
                'title' => $data['title'],
                'question_type' => $data['question_type'],
                'subject_tag' => $resolvedSubjectTag,
                'subject_id' => $resolvedSubjectId,
                'requested_quantity' => (int) $data['requested_quantity'],
            ]);

            $section->setRelation('exam', $lockedExam);
            $this->drawQuestionsForSection($section);

            $this->ensureStillPublishable($lockedExam);

            return $section;
        });
    }

    /**
     * Genera una propuesta sin guardar apartados ni reactivos.
     *
     * @return array<int, int> IDs de preguntas propuestas.
     */
    public function generateProposal(
        Exam $exam,
        string $questionType,
        int $quantity,
        ?string $subjectTag = null,
        ?int $subjectId = null,
    ): array {
        $this->ensureStructureMutable($exam);

        $alreadyUsedIds = ExamQuestion::where('exam_id', $exam->id)
            ->pluck('question_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $resolvedSubjectId = ($subjectId !== null && $subjectId > 0) ? $subjectId : null;

        $candidates = $this->questionRepository->getRandomCandidatesForSection(
            (int) $exam->teacher_id,
            $questionType,
            $alreadyUsedIds,
            $quantity,
            $subjectTag,
            $resolvedSubjectId
        );

        if ($candidates->count() < $quantity) {
            throw ValidationException::withMessages([
                'requested_quantity' => [
                    "El banco solo tiene {$candidates->count()} reactivos disponibles para los filtros seleccionados y se solicitaron {$quantity}",
                ],
            ]);
        }

        return $candidates->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Acepta una propuesta validando propiedad docente, tipo de reactivo [MEDIA-02],
     * linaje Copy-On-Write y persistiendo la materia normalizada del apartado.
     *
     * @param array<int, int> $questionIds
     */
    public function acceptProposal(
        Exam $exam,
        string $title,
        string $questionType,
        array $questionIds,
        ?string $subjectTag = null,
        ?int $subjectId = null,
    ): ExamSection {
        $questionIds = array_values(array_map('intval', $questionIds));
        if ($questionIds === [] || count($questionIds) !== count(array_unique($questionIds))) {
            throw ValidationException::withMessages([
                'question_ids' => ['La propuesta está vacía o contiene reactivos repetidos'],
            ]);
        }

        return DB::transaction(function () use (
            $exam,
            $title,
            $questionType,
            $questionIds,
            $subjectTag,
            $subjectId,
        ): ExamSection {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureStructureMutable($lockedExam);

            $alreadyUsedIds = ExamQuestion::where('exam_id', $lockedExam->id)
                ->pluck('question_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $availableIds = $this->questionRepository->filterAvailableQuestionIds(
                (int) $lockedExam->teacher_id,
                $questionIds,
                $alreadyUsedIds
            );

            if (count($availableIds) !== count($questionIds)) {
                throw ValidationException::withMessages([
                    'question_ids' => ['Uno o más reactivos de la propuesta ya forman parte del examen, comparten linaje Copy-On-Write o fueron eliminados'],
                ]);
            }

            $questions = Question::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->where('teacher_id', $lockedExam->teacher_id)
                ->where('question_type', $questionType)
                ->whereIn('id', $availableIds)
                ->get()
                ->keyBy('id');

            if ($questions->count() !== count($availableIds)) {
                throw ValidationException::withMessages([
                    'question_ids' => ['Algunos reactivos ya no están disponibles o no corresponden al tipo del apartado'],
                ]);
            }

            [$resolvedSubjectId, $resolvedSubjectTag] = $this->resolveSectionSubject(
                (int) $lockedExam->teacher_id,
                $subjectTag,
                $subjectId
            );

            $maxSectionSort = $lockedExam->sections()->max('sort_order');
            $nextSectionSort = $maxSectionSort !== null ? ((int) $maxSectionSort) + 1 : 0;

            $section = ExamSection::create([
                'exam_id' => $lockedExam->id,
                'title' => $title,
                'question_type' => $questionType,
                'subject_tag' => $resolvedSubjectTag,
                'subject_id' => $resolvedSubjectId,
                'requested_quantity' => count($availableIds),
                'sort_order' => $nextSectionSort,
            ]);

            $maxQuestionSort = $section->examQuestions()->max('sort_order');
            $nextQuestionSort = $maxQuestionSort !== null ? ((int) $maxQuestionSort) + 1 : 0;

            foreach ($availableIds as $questionId) {
                $question = $questions->get($questionId);

                ExamQuestion::create([
                    'exam_id' => $lockedExam->id,
                    'exam_section_id' => $section->id,
                    'question_id' => $question->id,
                    'time_limit_seconds' => $question->default_time_seconds,
                    'points_weight' => $question->default_points,
                    'sort_order' => $nextQuestionSort++,
                ]);
            }

            $this->ensureStillPublishable($lockedExam);

            return $section->fresh(['examQuestions.question']);
        });
    }

    /**
     * Agrega manualmente reactivos activos del banco hacia un apartado existente
     * o hacia un nuevo apartado homogéneo, validando colisiones Copy-On-Write.
     *
     * @param array<string, mixed> $payload
     */
    public function addManualQuestions(Exam $exam, User $teacher, array $payload): ExamSection
    {
        return DB::transaction(function () use ($exam, $teacher, $payload): ExamSection {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $lockedExam->teacher_id === (int) $teacher->id, 403);
            $this->ensureStructureMutable($lockedExam);

            $requestedIds = array_values(array_unique(array_filter(
                array_map('intval', $payload['question_ids'] ?? []),
                static fn (int $id): bool => $id > 0
            )));

            if ($requestedIds === []) {
                throw ValidationException::withMessages([
                    'question_ids' => ['Debes seleccionar al menos un reactivo del banco de preguntas'],
                ]);
            }

            $alreadyUsedIds = ExamQuestion::where('exam_id', $lockedExam->id)
                ->pluck('question_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $availableIds = $this->questionRepository->filterAvailableQuestionIds(
                (int) $teacher->id,
                $requestedIds,
                $alreadyUsedIds
            );

            if (count($availableIds) !== count($requestedIds)) {
                throw ValidationException::withMessages([
                    'question_ids' => ['Uno o más reactivos seleccionados ya forman parte del examen (o de su linaje Copy-On-Write) o no están disponibles'],
                ]);
            }

            $questions = Question::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->where('teacher_id', $teacher->id)
                ->whereIn('id', $availableIds)
                ->get()
                ->keyBy('id');

            if ($questions->count() !== count($availableIds)) {
                throw ValidationException::withMessages([
                    'question_ids' => ['Uno o más reactivos seleccionados no pertenecen a tu banco activo'],
                ]);
            }

            $distinctTypes = $questions->pluck('question_type')->unique()->values();
            if ($distinctTypes->count() > 1) {
                throw ValidationException::withMessages([
                    'question_ids' => ['Todos los reactivos asignados a un mismo apartado deben ser del mismo tipo'],
                ]);
            }

            $selectedType = (string) $distinctTypes->first();
            $targetSectionId = isset($payload['exam_section_id']) ? (int) $payload['exam_section_id'] : 0;

            if ($targetSectionId > 0) {
                $section = ExamSection::query()
                    ->where('exam_id', $lockedExam->id)
                    ->lockForUpdate()
                    ->find($targetSectionId);

                if ($section === null) {
                    throw ValidationException::withMessages([
                        'exam_section_id' => ['El apartado seleccionado no pertenece a este examen'],
                    ]);
                }

                if ((string) $section->question_type !== $selectedType) {
                    throw ValidationException::withMessages([
                        'question_ids' => ['El tipo de los reactivos seleccionados no coincide con el tipo del apartado destino'],
                    ]);
                }
            } else {
                $newTitle = trim((string) ($payload['new_section_title'] ?? ''));
                if ($newTitle === '') {
                    throw ValidationException::withMessages([
                        'new_section_title' => ['El título del nuevo apartado es obligatorio'],
                    ]);
                }

                $firstQuestion = $questions->get($availableIds[0]);
                $maxSectionSort = $lockedExam->sections()->max('sort_order');

                $section = ExamSection::create([
                    'exam_id' => $lockedExam->id,
                    'title' => $newTitle,
                    'question_type' => $selectedType,
                    'subject_tag' => $firstQuestion?->subject_tag,
                    'subject_id' => $firstQuestion?->subject_id,
                    'requested_quantity' => count($availableIds),
                    'sort_order' => $maxSectionSort !== null ? ((int) $maxSectionSort) + 1 : 0,
                ]);
            }

            $maxQuestionSort = $section->examQuestions()->max('sort_order');
            $nextSort = $maxQuestionSort !== null ? ((int) $maxQuestionSort) + 1 : 0;

            foreach ($availableIds as $questionId) {
                $question = $questions->get($questionId);

                ExamQuestion::create([
                    'exam_id' => $lockedExam->id,
                    'exam_section_id' => $section->id,
                    'question_id' => $question->id,
                    'time_limit_seconds' => $question->default_time_seconds,
                    'points_weight' => $question->default_points,
                    'sort_order' => $nextSort++,
                ]);
            }

            if ($targetSectionId > 0) {
                $section->update([
                    'requested_quantity' => $section->examQuestions()->count(),
                ]);
            }

            $this->ensureStillPublishable($lockedExam);

            return $section->fresh(['examQuestions.question']);
        });
    }

    /**
     * Actualiza título y materia de un apartado verificando mutabilidad estructural.
     *
     * @param array<string, mixed> $data
     */
    public function updateSection(Exam $exam, ExamSection $section, array $data): ExamSection
    {
        return DB::transaction(function () use ($exam, $section, $data): ExamSection {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureStructureMutable($lockedExam);

            [$resolvedSubjectId, $resolvedSubjectTag] = $this->resolveSectionSubject(
                (int) $lockedExam->teacher_id,
                $data['subject_tag'] ?? null,
                isset($data['subject_id']) ? (int) $data['subject_id'] : null
            );

            return $this->examRepository->updateSection($section, [
                'title' => $data['title'],
                'subject_tag' => $resolvedSubjectTag,
                'subject_id' => $resolvedSubjectId,
            ]);
        });
    }

    /**
     * Elimina un apartado y reordena los apartados restantes del examen.
     */
    public function deleteSection(Exam $exam, ExamSection $section): void
    {
        DB::transaction(function () use ($exam, $section): void {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureStructureMutable($lockedExam);

            $this->examRepository->deleteSection($section);
            $this->ensureStillPublishable($lockedExam);
        });
    }

    /**
     * Remueve un reactivo asignado de su apartado y sincroniza requested_quantity.
     */
    public function removeExamQuestion(Exam $exam, ExamQuestion $examQuestion): void
    {
        DB::transaction(function () use ($exam, $examQuestion): void {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureStructureMutable($lockedExam);

            $sectionId = (int) $examQuestion->exam_section_id;
            $this->examRepository->removeExamQuestion($examQuestion);

            $section = ExamSection::query()->find($sectionId);
            if ($section !== null) {
                $section->update([
                    'requested_quantity' => $section->examQuestions()->count(),
                ]);
            }

            $this->ensureStillPublishable($lockedExam);
        });
    }

    /**
     * Sortea requested_quantity preguntas del tipo y materia del apartado del banco
     * del propio docente, evitando repetir reactivos o linajes COW ya usados.
     */
    public function drawQuestionsForSection(ExamSection $section): void
    {
        $section->loadMissing('exam');
        $exam = $section->exam;

        $alreadyUsedIds = ExamQuestion::where('exam_id', $exam->id)
            ->pluck('question_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $candidates = $this->questionRepository->getRandomCandidatesForSection(
            (int) $exam->teacher_id,
            (string) $section->question_type,
            $alreadyUsedIds,
            (int) $section->requested_quantity,
            $section->subject_tag,
            $section->subject_id
        );

        if ($candidates->count() < $section->requested_quantity) {
            throw ValidationException::withMessages([
                'requested_quantity' => [
                    "El banco solo tiene {$candidates->count()} reactivos de tipo {$section->question_type} disponibles para los filtros seleccionados y se pidieron {$section->requested_quantity}",
                ],
            ]);
        }

        DB::transaction(static function () use ($candidates, $exam, $section): void {
            $maxSort = $section->examQuestions()->max('sort_order');
            $nextSort = $maxSort !== null ? ((int) $maxSort) + 1 : 0;

            foreach ($candidates->values() as $question) {
                ExamQuestion::create([
                    'exam_id' => $exam->id,
                    'exam_section_id' => $section->id,
                    'question_id' => $question->id,
                    'time_limit_seconds' => $question->default_time_seconds,
                    'points_weight' => $question->default_points,
                    'sort_order' => $nextSort++,
                ]);
            }
        });
    }

    /**
     * Nuevo sorteo de un reactivo puntual dentro de su apartado (RW-14):
     * lo sustituye por otro candidato del mismo tipo y materia preservando
     * points_weight y time_limit_seconds del reactivo reemplazado.
     */
    public function reshuffleQuestion(ExamQuestion $examQuestion): ExamQuestion
    {
        return DB::transaction(function () use ($examQuestion): ExamQuestion {
            $lockedExamQuestion = ExamQuestion::query()
                ->whereKey($examQuestion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedExamQuestion->loadMissing(['examSection', 'exam']);
            $section = $lockedExamQuestion->examSection;
            $exam = $lockedExamQuestion->exam;

            $this->ensureStructureMutable($exam);

            $alreadyUsedIds = ExamQuestion::where('exam_id', $exam->id)
                ->pluck('question_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $replacement = $this->questionRepository->getRandomCandidatesForSection(
                (int) $exam->teacher_id,
                (string) $section->question_type,
                $alreadyUsedIds,
                1,
                $section->subject_tag,
                $section->subject_id
            )->first();

            if (! $replacement) {
                throw ValidationException::withMessages([
                    'question_id' => ['No hay otro reactivo del mismo tipo y materia disponible en el banco para sustituir este'],
                ]);
            }

            // RW-14: preservar ponderación y tiempo límite configurados en el examen.
            $lockedExamQuestion->update([
                'question_id' => $replacement->id,
            ]);

            return $lockedExamQuestion->fresh();
        });
    }

    /**
     * Persiste transaccionalmente la configuración de ponderaciones (base 100.00),
     * tiempos, ventana, grupos y visibilidad del examen.
     *
     * @param array<string, mixed> $data
     */
    public function updateConfiguration(Exam $exam, array $data): Exam
    {
        return DB::transaction(function () use ($exam, $data): Exam {
            $lockedExam = Exam::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedExam->is_published && ($lockedExam->hasStartedWindow() || $lockedExam->isWindowClosed())) {
                throw ValidationException::withMessages([
                    'exam' => ['No es posible reconfigurar ponderaciones o estructura de un examen cuya aplicación ya inició o concluyó'],
                ]);
            }

            if ($lockedExam->studentAttempts()->exists()
                && isset($data['grading_scale'])
                && (string) $data['grading_scale'] !== (string) $lockedExam->grading_scale
            ) {
                throw ValidationException::withMessages([
                    'grading_scale' => ['No puedes cambiar la escala de calificación porque el examen ya tiene intentos registrados'],
                ]);
            }

            $examQuestions = $lockedExam->examQuestions()->lockForUpdate()->get()->keyBy('id');
            $submitted = $data['questions'] ?? [];

            $expectedIds = $examQuestions->keys()->map(static fn ($id): string => (string) $id)->sort()->values()->all();
            $receivedIds = collect(array_keys($submitted))->map(static fn ($id): string => (string) $id)->sort()->values()->all();

            if ($expectedIds !== $receivedIds) {
                throw ValidationException::withMessages([
                    'questions' => ['La lista de preguntas cambió; recarga la página e inténtalo de nuevo'],
                ]);
            }

            $totalCents = 0;
            foreach ($submitted as $values) {
                $totalCents += (int) round((float) $values['points_weight'] * 100);
            }

            if (abs($totalCents - 10000) > 1) {
                throw ValidationException::withMessages([
                    'questions' => ['La suma de las ponderaciones debe ser exactamente 100.00 puntos'],
                ]);
            }

            $effectiveStart = $data['starts_at'] ?? $lockedExam->starts_at;
            $effectiveEnd = $data['ends_at'] ?? $lockedExam->ends_at;
            if ($effectiveStart !== null && $effectiveEnd !== null) {
                $startCarbon = $effectiveStart instanceof Carbon ? $effectiveStart : Carbon::parse((string) $effectiveStart);
                $endCarbon = $effectiveEnd instanceof Carbon ? $effectiveEnd : Carbon::parse((string) $effectiveEnd);

                if ($endCarbon->lte($startCarbon)) {
                    throw ValidationException::withMessages([
                        'ends_at' => ['La fecha de cierre debe ser posterior a la fecha de inicio'],
                    ]);
                }
            }

            if (array_key_exists('group_ids', $data)) {
                $groupIds = array_values(array_unique(array_filter(
                    array_map('intval', $data['group_ids'] ?? []),
                    static fn (int $id): bool => $id > 0
                )));
                $this->assertSelectableGroupsForExam($lockedExam, $groupIds);
            } else {
                $groupIds = $lockedExam->groups()
                    ->pluck('acs_groups.id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();
            }

            $updatable = collect($data)->except(['questions', 'group_ids'])->all();
            if (array_key_exists('require_webcam', $updatable)) {
                $updatable['require_webcam'] = (bool) ($updatable['require_webcam'] ?? false);
            }

            $updatedExam = $this->examRepository->updateExam($lockedExam, $updatable, $groupIds);

            foreach ($submitted as $id => $values) {
                $examQuestions->get((int) $id)?->update([
                    'points_weight' => round((float) $values['points_weight'], 2),
                    'time_limit_seconds' => (int) $values['time_limit_seconds'],
                ]);
            }

            $this->ensureStillPublishable($updatedExam);

            return $updatedExam->fresh(['groups', 'sections.examQuestions']);
        });
    }

    /**
     * Arma el orden de preguntas que verá un alumno: ordenado primero por sección
     * y luego por orden interno. Con randomize_question_order el orden se mezcla
     * por estudiante dentro de cada apartado, respetando el orden de los apartados
     * (RF-EXA-015, RW-20).
     *
     * @return array<int, int> ids de acs_exam_question en el orden a mostrar
     */
    public function buildQuestionSequence(Exam $exam): array
    {
        $examQuestions = ExamQuestion::query()
            ->leftJoin('acs_exam_sections', 'acs_exam_sections.id', '=', 'acs_exam_question.exam_section_id')
            ->where('acs_exam_question.exam_id', $exam->id)
            ->orderByRaw('COALESCE(acs_exam_sections.sort_order, 0) ASC')
            ->orderBy('acs_exam_sections.id')
            ->orderBy('acs_exam_question.sort_order')
            ->orderBy('acs_exam_question.id')
            ->get(['acs_exam_question.id', 'acs_exam_question.exam_section_id']);

        if (! $exam->randomize_question_order) {
            return $examQuestions->map(static fn (ExamQuestion $row): int => (int) $row->id)->all();
        }

        return $examQuestions
            ->groupBy(static fn (ExamQuestion $row): string => (string) ($row->exam_section_id ?? 'sin-apartado'))
            ->flatMap(static fn ($rows) => $rows->pluck('id')->map(static fn (mixed $id): int => (int) $id)->shuffle())
            ->values()
            ->all();
    }

    /**
     * Resuelve el par [subject_id, subject_tag] para un apartado a partir de ID o etiqueta.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function resolveSectionSubject(int $teacherId, mixed $rawTag, ?int $subjectId): array
    {
        if ($subjectId !== null && $subjectId > 0) {
            $subject = Subject::query()
                ->where('teacher_id', $teacherId)
                ->find($subjectId);

            if ($subject === null) {
                throw ValidationException::withMessages([
                    'subject_id' => ['La materia seleccionada no pertenece a tu catálogo docente'],
                ]);
            }

            return [(int) $subject->id, (string) $subject->name];
        }

        $cleanSubject = Subject::cleanDisplayName(is_string($rawTag) ? $rawTag : null);
        if ($cleanSubject === null) {
            return [null, null];
        }

        $subject = $this->questionRepository->resolveOrCreateSubject($teacherId, $cleanSubject);

        return [$subject?->id, $subject?->name ?? $cleanSubject];
    }

    /**
     * Verifica propiedad docente y vigencia (abierto o ya asignado) sobre los grupos enviados.
     *
     * @param array<int, int> $groupIds
     */
    private function assertSelectableGroupsForExam(Exam $exam, array $groupIds): void
    {
        if ($groupIds === []) {
            return;
        }

        $groups = Group::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $exam->teacher_id)
            ->whereIn('id', $groupIds)
            ->get()
            ->keyBy('id');

        if ($groups->count() !== count($groupIds)) {
            throw ValidationException::withMessages([
                'group_ids' => ['Alguno de los grupos seleccionados no pertenece a tu catálogo docente'],
            ]);
        }

        $alreadyAssignedIds = $exam->groups()
            ->pluck('acs_groups.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        foreach ($groupIds as $groupId) {
            $group = $groups->get($groupId);
            if ($group !== null && $group->is_closed && ! in_array($groupId, $alreadyAssignedIds, true)) {
                throw ValidationException::withMessages([
                    'group_ids' => ['No puedes asignar un grupo cerrado que no pertenecía previamente al examen'],
                ]);
            }
        }
    }
}
