<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptService.php
 * Ubicación:   app/Services/AttemptService.php
 * Descripción: Lógica transaccional de aplicación de examen (Módulo 7)
 *              compartida entre el panel web y la API móvil v1 (MPW-V3 §5.6,
 *              §6.4): validación server-side de ventana y cronómetro,
 *              calificación proporcional en relación de columnas (RF-EVA-002),
 *              omisión de IA en respuestas en blanco (RF-EVA-008) y exposición
 *              de paráfrasis activa (RF-BAN-010).
 * ============================================================================
 */

namespace App\Services;

use App\Events\ExamAttemptIncidentReported;
use App\Events\ExamAttemptStarted;
use App\Events\ExamAttemptSubmitted;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Group;
use App\Models\QuestionParaphrase;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AttemptService
{
    /**
     * Tolerancia técnica de red, en segundos, que se suma al límite de cada
     * pregunta antes de considerar una respuesta extemporánea (MPW-V3 §5.6.1
     * y MPM-V3 §5.4.1: tres segundos).
     */
    public const QUESTION_TIME_TOLERANCE_SECONDS = 3;

    public function __construct(
        private readonly ExamAssemblyService $assemblyService,
        private readonly ExamNotificationService $examNotifications,
        private readonly GradeCalculatorService $gradeCalculator,
    ) {
    }

    public function start(Exam $exam, User $student): StudentAttempt
    {
        return $this->startOrResumeAttempt($exam, $student);
    }

    /**
     * Intento vigente (el más reciente) del alumno en el examen, o null si no
     * lo ha iniciado (RF-EXA-052).
     */
    public function latestAttemptFor(Exam $exam, User $student): ?StudentAttempt
    {
        return StudentAttempt::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->orderByDesc('attempt_number')
            ->first();
    }

    /**
     * Indica si el docente puede autorizar un intento adicional a partir de
     * este intento: debe ser el vigente, estar terminado, no tener ya una
     * autorización y el examen seguir abierto (decisión del 22/11/2026: la
     * reaplicación solo se presenta dentro de la ventana de acceso).
     */
    public function canAuthorizeReapplication(StudentAttempt $attempt): bool
    {
        return $this->reapplicationBlocker($attempt) === null;
    }

    /**
     * Autoriza un intento adicional individual (RF-EXA-052). El intento
     * actual se conserva con sus resultados; el alumno presenta el siguiente
     * al volver a iniciar el examen y la calificación vigente pasa a ser la
     * del nuevo intento.
     *
     * @throws DomainException Si el intento no admite reaplicación.
     */
    public function authorizeReapplication(StudentAttempt $attempt, User $teacher): StudentAttempt
    {
        return DB::transaction(function () use ($attempt, $teacher): StudentAttempt {
            $locked = StudentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('exam', $attempt->exam ?? $locked->exam()->firstOrFail());

            $blocker = $this->reapplicationBlocker($locked);
            if ($blocker !== null) {
                throw new DomainException($blocker);
            }

            $locked->update(['reapplication_authorized_at' => now()]);

            AuditLog::query()->create([
                'user_id' => $teacher->id,
                'action' => StudentAttempt::ACTION_REAPPLICATION_AUTHORIZED,
                'auditable_type' => StudentAttempt::class,
                'auditable_id' => $locked->id,
                'new_values' => [
                    'student_id' => (int) $locked->student_id,
                    'attempt_number' => (int) $locked->attempt_number,
                    'next_attempt_number' => (int) $locked->attempt_number + 1,
                ],
            ]);

            return $locked;
        });
    }

    private function reapplicationBlocker(StudentAttempt $attempt): ?string
    {
        $exam = $attempt->exam;

        if ($attempt->reapplication_authorized_at !== null) {
            return 'Este alumno ya tiene una reaplicación autorizada.';
        }

        if ($attempt->status === 'in_progress') {
            return 'El alumno aún está presentando este intento.';
        }

        $isLatest = ! StudentAttempt::where('exam_id', $attempt->exam_id)
            ->where('student_id', $attempt->student_id)
            ->where('attempt_number', '>', $attempt->attempt_number)
            ->exists();

        if (! $isLatest) {
            return 'Solo se puede autorizar la reaplicación desde el intento más reciente.';
        }

        if (! $exam->is_published || ($exam->ends_at !== null && now()->gte($exam->ends_at))) {
            return 'La reaplicación solo se autoriza mientras el examen sigue abierto.';
        }

        return null;
    }

    /**
     * Inicia o recupera el intento activo del alumno validando publicación,
     * ventana horaria (starts_at / ends_at) y pertenencia a grupo asignado.
     *
     * @throws DomainException
     */
    public function startOrResumeAttempt(Exam $exam, User $student): StudentAttempt
    {
        $attempt = DB::transaction(function () use ($exam, $student): StudentAttempt {
            // Comparte el bloqueo del examen con su edición y cierre docente.
            $exam = Exam::query()->whereKey($exam->id)->sharedLock()->firstOrFail();
            abort_unless($exam->is_published, 404);

            $window = $this->examWindowState($exam);
            if ($window !== Exam::STATUS_ACTIVE) {
                throw new DomainException($window === Exam::STATUS_SCHEDULED
                    ? 'El examen aún no se encuentra dentro de su ventana de aplicación.'
                    : 'La ventana de aplicación del examen ha concluido.');
            }

            $preferredGroup = $this->latestAttemptFor($exam, $student)?->group_id;
            $groupId = $exam->groups()->active()
                ->whereHas('students', fn ($query) => $query->whereKey($student->id))
                ->orderByRaw('CASE WHEN acs_groups.id = ? THEN 0 ELSE 1 END', [$preferredGroup])
                ->orderBy('acs_groups.id')
                ->value('acs_groups.id');
            $group = Group::query()->whereKey($groupId)->lockForUpdate()->first();

            // Una baja puede retirar el vínculo mientras el inicio espera el bloqueo.
            abort_unless(
                $group && ! $group->is_closed && $group->students()->whereKey($student->id)->exists(),
                403,
                'No perteneces a ningún grupo activo asignado a este examen.'
            );

            $existing = StudentAttempt::where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->orderByDesc('attempt_number')
                ->lockForUpdate()
                ->first();

            // RF-EXA-051: un intento por alumno; RF-EXA-052: solo una reaplicación
            // autorizada por el docente abre el siguiente, conservando el anterior.
            if ($existing !== null && ($existing->reapplication_authorized_at === null || $existing->status === 'in_progress')) {
                abort_unless($existing->group_id === $group->id, 403);

                return $existing;
            }

            $attemptNumber = $existing === null ? 1 : (int) $existing->attempt_number + 1;

            $sequence = $this->assemblyService->buildQuestionSequence($exam);
            $paraphraseSnapshot = $this->buildParaphraseSnapshot($sequence, (int) $student->id);

            $startedAt = now();
            $visibleDeadline = $exam->free_duration_minutes !== null
                ? $startedAt->copy()->addMinutes((int) $exam->free_duration_minutes) : null;
            $grace = config('exams.free_submission_grace_seconds');
            if ($visibleDeadline !== null && ! in_array($grace, [0, 60], true)) {
                throw new DomainException('Falta autorizar la regla de cierre de la modalidad libre.');
            }

            return StudentAttempt::firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $student->id, 'attempt_number' => $attemptNumber],
                [
                    'group_id' => $group->id,
                    'question_sequence_payload' => $sequence,
                    'paraphrase_snapshot_payload' => $paraphraseSnapshot,
                    'current_question_index' => 0,
                    'started_at' => $startedAt,
                    'free_edit_until' => $visibleDeadline,
                    'free_close_at' => $visibleDeadline?->copy()->addSeconds($grace ?? 0),
                    'status' => 'in_progress',
                ],
            );
        });

        // Solo el primer inicio de cada alumno cuenta; reanudar no avisa.
        if ($attempt->wasRecentlyCreated) {
            $this->examNotifications->examStarted($exam, $attempt);

            $studentUser = $attempt->relationLoaded('student') ? $attempt->student : $attempt->student()->first();
            $this->broadcastSafely(static fn () => ExamAttemptStarted::dispatch(
                (int) $exam->id,
                (int) $attempt->id,
                (int) $student->id,
                $studentUser ? $studentUser->full_name : 'Estudiante #' . $student->id,
                now()->format('H:i:s'),
                'in_progress'
            ));
        }

        return $attempt;
    }

    public function examWindowState(Exam $exam, ?CarbonInterface $now = null): string
    {
        $now ??= now();

        if ($exam->ends_at !== null && $now->gt($exam->ends_at)) {
            return Exam::STATUS_CLOSED;
        }

        return $exam->starts_at !== null && $now->lt($exam->starts_at)
            ? Exam::STATUS_SCHEDULED
            : Exam::STATUS_ACTIVE;
    }

    /**
     * Resuelve el reactivo activo o el índice elegido en la navegación libre.
     *
     * @param StudentAttempt $attempt Intento y secuencia fijada al inicio.
     * @param int|null $freeIndex Índice solicitado en modalidad libre.
     * @return array<string, mixed>|null Reactivo y tiempo restante, o nulo al salir de la secuencia.
     */
    public function currentQuestion(StudentAttempt $attempt, ?int $freeIndex = null): ?array
    {
        $sequence = $attempt->question_sequence_payload ?? [];

        $index = $attempt->free_close_at !== null ? ($freeIndex ?? 0) : $attempt->current_question_index;
        if ($index < 0 || $index >= count($sequence)) {
            return null;
        }

        $examQuestionId = $sequence[$index];
        $examQuestion = ExamQuestion::with(['question.questionable', 'question.paraphrases'])->findOrFail($examQuestionId);

        return [
            'exam_question_id' => $examQuestion->id,
            'index' => $index,
            'total' => count($sequence),
            'time_limit_seconds' => $examQuestion->time_limit_seconds,
            'remaining_seconds' => $attempt->free_edit_until !== null
                ? max(0, $attempt->free_edit_until->getTimestamp() - now()->getTimestamp())
                : $this->remainingSeconds($attempt, (int) $examQuestion->time_limit_seconds),
            'points_weight' => $examQuestion->points_weight,
            'question' => $this->questionPayloadWithoutAnswer($examQuestion, $attempt),
        ];
    }

    /**
     * Momento en que el servidor entregó la pregunta activa: la respuesta
     * anterior (cada respuesta devuelve la siguiente pregunta) o, para la
     * primera pregunta, el inicio del intento. No requiere columnas nuevas.
     */
    public function questionServedAt(StudentAttempt $attempt): CarbonInterface
    {
        $lastAnswer = StudentAnswer::query()
            ->where('student_attempt_id', $attempt->id)
            ->whereNotNull('answered_at')
            ->latest('answered_at')
            ->first();

        return $lastAnswer?->answered_at ?? $attempt->started_at ?? now();
    }

    /**
     * Segundos transcurridos en la pregunta activa según el reloj del
     * servidor (MPW-V3 §5.6.1: el cliente nunca es la autoridad del tiempo).
     */
    public function elapsedOnActiveQuestion(StudentAttempt $attempt): float
    {
        return max(0.0, (float) $this->questionServedAt($attempt)->diffInSeconds(now(), false));
    }

    /**
     * Segundos que le quedan al alumno en la pregunta activa, entre 0 y el
     * límite de la pregunta. Reabrir la app o volver a consultar el examen ya
     * no devuelve el tiempo completo (MPM-V3 §5.4.1).
     */
    public function remainingSeconds(StudentAttempt $attempt, int $timeLimitSeconds): int
    {
        if ($timeLimitSeconds <= 0) {
            return 0;
        }

        $remaining = ceil($timeLimitSeconds - $this->elapsedOnActiveQuestion($attempt));

        return (int) max(0, min($timeLimitSeconds, $remaining));
    }

    /**
     * Indica si la recepción de una respuesta excede el plazo de la pregunta más su tolerancia.
     *
     * @param StudentAttempt $attempt Intento que conserva la hora del reactivo.
     * @param int $timeLimitSeconds Duración del reactivo.
     * @param CarbonInterface|null $receivedAt Hora registrada por el servidor.
     * @return bool Indica si la respuesta se recibió fuera de tiempo.
     */
    public function isAnswerLate(StudentAttempt $attempt, int $timeLimitSeconds, ?CarbonInterface $receivedAt = null): bool
    {
        if ($timeLimitSeconds <= 0) {
            return false;
        }

        $elapsed = $this->questionServedAt($attempt)->diffInSeconds($receivedAt ?? now(), false);

        return $elapsed > $timeLimitSeconds + self::QUESTION_TIME_TOLERANCE_SECONDS;
    }

    /**
     * Alias semántico de currentQuestion() para verificar la pregunta activa
     * aún sin responder en la secuencia del intento.
     *
     * @return array<string, mixed>|null
     */
    public function currentUnansweredQuestion(StudentAttempt $attempt): ?array
    {
        return $this->currentQuestion($attempt);
    }

    /**
     * Registra y califica la respuesta de una pregunta validando estado activo
     * y tiempo del lado servidor: la ventana del examen (con margen de 60
     * segundos) y el límite de la propia pregunta (con tolerancia de 3
     * segundos, MPW-V3 §5.6.1). Una respuesta extemporánea se conserva para
     * la revisión docente, pero se califica con cero puntos y sin evaluador
     * de IA; el intento avanza igual a la siguiente pregunta.
     *
     * @param StudentAttempt $attempt Intento en aplicación.
     * @param int $examQuestionId Reactivo enviado.
     * @param mixed $answerPayload Respuesta validada.
     * @param string|null $holder Sesión que presentó y envió la respuesta.
     * @return bool Indica si la respuesta llegó fuera de tiempo.
     *
     * @throws DomainException
     */
    public function submitAnswer(StudentAttempt $attempt, int $examQuestionId, mixed $answerPayload, ?string $holder = null): bool
    {
        $receivedAt = now()->toImmutable();
        $isLate = DB::transaction(function () use ($attempt, $examQuestionId, $answerPayload, $holder, $receivedAt): ?bool {
            $locked = StudentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->free_close_at !== null) {
                throw new DomainException('Continúa el examen de modalidad libre en su vista web.');
            }
            if ($locked->status !== 'in_progress') {
                throw new DomainException('El intento ya no se encuentra activo.');
            }
            $this->requireSession($locked, $holder);
            if ($this->expireSequentialIfDue($locked)) {
                return null;
            }

            $activeQuestion = $locked->question_sequence_payload[$locked->current_question_index] ?? null;
            if ((int) $activeQuestion !== $examQuestionId) {
                throw new DomainException('La pregunta enviada no corresponde al reactivo activo en secuencia.');
            }
            $question = $this->attemptQuestion($locked, $examQuestionId);
            $isLate = $this->isAnswerLate($locked, (int) $question->time_limit_seconds, $receivedAt);
            $this->persistSequentialAnswer($locked, $question, $answerPayload, $receivedAt, $isLate);
            $locked->increment('current_question_index');
            $this->forgetDraft($locked, $examQuestionId);

            return $isLate;
        });

        if ($isLate === null) {
            throw new DomainException('El tiempo límite del examen ha expirado.');
        }

        return $isLate;
    }

    /** Busca el reactivo dentro del examen del intento. */
    private function attemptQuestion(StudentAttempt $attempt, int $id): ExamQuestion
    {
        return ExamQuestion::with(['question.questionable', 'question.paraphrases'])
            ->where('exam_id', $attempt->exam_id)->findOrFail($id);
    }

    /** Registra la respuesta y la variante servida, manteniendo la calificación vigente. */
    private function persistSequentialAnswer(
        StudentAttempt $attempt,
        ExamQuestion $examQuestion,
        mixed $payload,
        ?CarbonInterface $receivedAt,
        ?bool $isLate,
    ): void {
        $question = $examQuestion->question;
        $stem = $this->resolvedStemData($examQuestion, $attempt);
        $graded = match ($isLate) {
            null => [],
            true => ['is_correct' => false, 'points_awarded' => 0.0],
            false => $this->gradeAnswer($question->question_type, $question->questionable, $payload, (float) $examQuestion->points_weight),
        };

        StudentAnswer::firstOrCreate([
            'student_attempt_id' => $attempt->id,
            'exam_question_id' => $examQuestion->id,
        ], $graded + [
            'answer_payload' => $payload,
            'question_paraphrase_id' => $question->paraphrases->contains('id', $stem['paraphrase_id']) ? $stem['paraphrase_id'] : null,
            'served_stem_snapshot' => $stem['stem'],
            // El borrador conserva zona horaria. answered_at usa la hora local del esquema histórico.
            'answered_at' => $receivedAt?->toImmutable()->setTimezone(config('app.timezone')),
        ]);
    }

    /** Incorpora el último borrador confirmado sin avanzar ni alterar respuestas consolidadas. */
    private function preserveCurrentDraft(StudentAttempt $attempt, bool $score): void
    {
        if ($attempt->free_close_at !== null) {
            return;
        }
        $id = $attempt->question_sequence_payload[$attempt->current_question_index] ?? null;
        if ($id === null) {
            return;
        }
        $draft = $this->confirmedDraft($attempt, (int) $id);
        if ($draft === null) {
            return;
        }

        [$payload, $savedAt] = $draft;
        $question = $this->attemptQuestion($attempt, (int) $id);
        $isLate = ($score && $savedAt !== null) ? $this->isAnswerLate($attempt, (int) $question->time_limit_seconds, $savedAt) : null;
        $this->persistSequentialAnswer($attempt, $question, $payload, $savedAt, $isLate);
        $this->forgetDraft($attempt, (int) $id);
    }

    /**
     * Guarda una respuesta libre verificando el vencimiento y la revisión.
     *
     * @param StudentAttempt $attempt Intento del estudiante.
     * @param int $questionId Identificador del reactivo de la secuencia.
     * @param mixed $payload Respuesta normalizada por la ruta de captura.
     * @param int $revision Última revisión confirmada al estudiante.
     * @param string|null $holder Identificador de la sesión activa en ruta web.
     * @return int Revisión persistida de la respuesta.
     * @throws DomainException Si ha vencido el intento, la pregunta no pertenece a él o la revisión está desactualizada.
     */
    public function saveFreeAnswer(StudentAttempt $attempt, int $questionId, mixed $payload, int $revision, ?string $holder = null): int
    {
        $this->closeFreeIfDue($attempt);

        return DB::transaction(function () use ($attempt, $questionId, $payload, $revision, $holder): int {
            $locked = StudentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'in_progress' || $locked->free_close_at === null || now()->gte($locked->free_close_at)) {
                throw new DomainException('El intento ya no admite modificaciones.');
            }
            $this->requireSession($locked, $holder);
            if (! in_array($questionId, array_map('intval', $locked->question_sequence_payload), true)) {
                throw new DomainException('Selecciona una pregunta de este intento.');
            }
            $question = $this->attemptQuestion($locked, $questionId);
            $answer = StudentAnswer::firstOrNew(['student_attempt_id' => $locked->id, 'exam_question_id' => $questionId]);
            $currentRevision = (int) $answer->free_revision;
            if ($answer->exists && $answer->answer_payload === $payload) {
                return $currentRevision;
            }
            if ($currentRevision !== $revision) {
                throw new DomainException('La respuesta cambió. Recarga la pregunta para ver la versión guardada.');
            }
            $stem = $this->resolvedStemData($question, $locked);
            $graded = $this->gradeAnswer($question->question->question_type, $question->question->questionable,
                $payload, (float) $question->points_weight);
            $answer->fill($graded + ['answer_payload' => $payload, 'answered_at' => now(), 'free_revision' => $revision + 1,
                'question_paraphrase_id' => $question->question->paraphrases->contains('id', $stem['paraphrase_id']) ? $stem['paraphrase_id'] : null,
                'served_stem_snapshot' => $stem['stem']])->save();

            return $revision + 1;
        });
    }

    /**
     * Entrega los intentos libres que alcanzan el límite registrado.
     *
     * @param StudentAttempt $attempt Intento observado por una petición o por el programador.
     * @return StudentAttempt Estado resultante del intento.
     */
    public function closeFreeIfDue(StudentAttempt $attempt): StudentAttempt
    {
        if ($attempt->status === 'in_progress' && $attempt->free_close_at !== null
            && $this->hasReachedDeadline($attempt, $attempt->free_close_at)) {
            return $this->submitFinal($attempt);
        }

        return $attempt;
    }

    public function closeIfDue(StudentAttempt $attempt): StudentAttempt
    {
        if ($attempt->status !== 'in_progress') {
            return $attempt;
        }

        if ($attempt->free_close_at !== null) {
            return $this->closeFreeIfDue($attempt);
        }

        if ($this->isInProgress($attempt)) {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt): StudentAttempt {
            $locked = StudentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'in_progress' && $locked->free_close_at === null) {
                $this->expireSequentialIfDue($locked);
            }

            return $locked;
        });
    }

    /**
     * Guarda en el servidor el borrador de la pregunta activa mientras el alumno
     * responde (RF-EXA-040). Solo acepta la pregunta activa de un intento en
     * curso. La consolidación usa esta misma fila sin avanzar la secuencia.
     *
     * @param StudentAttempt $attempt Intento al que corresponde el borrador.
     * @param int $examQuestionId Identificador del reactivo activo.
     * @param mixed $payload Respuesta ya validada por la ruta.
     * @param string|null $holder Sesión del estudiante.
     * @return CarbonInterface Hora confirmada por el servidor.
     * @throws DomainException Si el intento, su reactivo o su sesión dejaron de estar activos.
     */
    public function saveDraft(StudentAttempt $attempt, int $examQuestionId, mixed $payload, ?string $holder = null): CarbonInterface
    {
        return DB::transaction(function () use ($attempt, $examQuestionId, $payload, $holder): CarbonInterface {
            $locked = StudentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->free_close_at !== null || ! $this->isInProgress($locked)) {
                throw new DomainException('El intento ya no se encuentra activo.');
            }
            $this->requireSession($locked, $holder);
            $activeQuestion = $locked->question_sequence_payload[$locked->current_question_index] ?? null;
            if ((int) $activeQuestion !== $examQuestionId) {
                throw new DomainException('La pregunta ya no es la activa; vuelve a consultar el examen.');
            }

            $savedAt = now()->toImmutable();
            DB::table('acs_attempt_drafts')->updateOrInsert(
                ['student_attempt_id' => $locked->id],
                [
                    'exam_question_id' => $examQuestionId,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'saved_at' => $savedAt->format('Y-m-d H:i:s.uP'),
                ],
            );

            return $savedAt;
        });
    }

    /** Recupera el último borrador de la pregunta activa para reanudar. */
    public function draftFor(StudentAttempt $attempt, int $examQuestionId): mixed
    {
        return $this->confirmedDraft($attempt, $examQuestionId)[0] ?? null;
    }

    /** Lee el contenido confirmado y la fecha del servidor, si el registro la conserva. */
    private function confirmedDraft(StudentAttempt $attempt, int $examQuestionId): ?array
    {
        $draft = DB::table('acs_attempt_drafts')->where('student_attempt_id', $attempt->id)
            ->where('exam_question_id', $examQuestionId)->first();
        if ($draft !== null) {
            return [json_decode($draft->payload, true, 512, JSON_THROW_ON_ERROR), CarbonImmutable::parse($draft->saved_at)];
        }

        // Los borradores anteriores contienen el valor confirmado, pero no su hora.
        $legacy = Cache::get($this->draftKey($attempt, $examQuestionId));

        return $legacy === null ? null : [$legacy, null];
    }

    /** Retira el borrador solo después de guardar su respuesta o avanzar de reactivo. */
    private function forgetDraft(StudentAttempt $attempt, int $examQuestionId): void
    {
        DB::table('acs_attempt_drafts')->where('student_attempt_id', $attempt->id)->delete();
        DB::afterCommit(fn () => Cache::forget($this->draftKey($attempt, $examQuestionId)));
    }

    /** Segundos sin presencia tras los que el estudiante puede cambiar de sesión. */
    public const SESSION_IDLE_SECONDS = 45;

    /** Adquiere o renueva bajo bloqueo la única sesión que presenta el intento. */
    public function acquireSession(StudentAttempt $attempt, string $holder): bool
    {
        return DB::transaction(function () use ($attempt, $holder): bool {
            $locked = StudentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            return $locked->status !== 'in_progress' || $this->claimSession($locked, $holder);
        });
    }

    /** Informa si otra sesión mantiene la reserva en este momento. */
    public function sessionAvailable(StudentAttempt $attempt, string $holder): bool
    {
        return $this->canClaimSession($attempt->refresh(), $holder);
    }

    /** Consulta la reserva observada dentro del bloqueo del intento cuando se va a renovar. */
    private function canClaimSession(StudentAttempt $attempt, string $holder): bool
    {
        if ($attempt->presenter_hash !== null && $attempt->presenter_seen_at !== null) {
            return hash_equals($attempt->presenter_hash, hash('sha256', $holder))
                || CarbonImmutable::parse($attempt->presenter_seen_at)->addSeconds(self::SESSION_IDLE_SECONDS)->lte(now());
        }

        $legacy = Cache::get($this->sessionKey($attempt));

        return ! is_array($legacy)
            || ($legacy['holder'] ?? null) === $holder
            || now()->getTimestamp() - (int) ($legacy['seen'] ?? 0) >= self::SESSION_IDLE_SECONDS;
    }

    /** Actualiza la reserva dentro del mismo bloqueo que protege las respuestas y el cierre. */
    private function claimSession(StudentAttempt $attempt, string $holder): bool
    {
        if (! $this->canClaimSession($attempt, $holder)) {
            return false;
        }

        StudentAttempt::withoutTimestamps(fn () => $attempt->forceFill([
            'presenter_hash' => hash('sha256', $holder),
            'presenter_seen_at' => now()->format('Y-m-d H:i:s.uP'),
        ])->save());

        return true;
    }

    /** Verifica la sesión de una operación del estudiante. Los cierres del sistema carecen de sesión. */
    private function requireSession(StudentAttempt $attempt, ?string $holder): void
    {
        if ($holder !== null && ! $this->claimSession($attempt, $holder)) {
            throw new DomainException('Este intento está abierto en otra sesión o dispositivo. Ciérralo ahí o espera un minuto e inténtalo de nuevo.');
        }
    }

    private function sessionKey(StudentAttempt $attempt): string
    {
        return sprintf('attempt-session:%d', $attempt->id);
    }

    private function draftKey(StudentAttempt $attempt, int $examQuestionId): string
    {
        return sprintf('attempt-draft:%d:%d', $attempt->id, $examQuestionId);
    }

    /**
     * El monitoreo en tiempo real (Reverb) es auxiliar: si no responde, el
     * examen continúa y solo se registra el error, igual que con los avisos push.
     */
    private function broadcastSafely(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (Throwable $e) {
            Log::warning('No se pudo emitir el evento de monitoreo en tiempo real.', ['reason' => $e->getMessage()]);
        }
    }

    /**
     * Registra un incidente de sospecha anti-trampa, incrementa el contador
     * y emite el evento en vivo por WebSockets Reverb (RW-12, RW-16, ADR-019).
     *
     * @param StudentAttempt $attempt Intento que estaba en curso.
     * @param string $incidentType Tipo de incidente.
     * @param string $description Detalle proporcionado por el cliente.
     * @param string|null $holder Identificador de la sesión presentadora del cliente.
     * @return StudentAttempt Intento actualizado.
     * @throws DomainException Si otra sesión presenta el intento.
     */
    public function reportIncident(StudentAttempt $attempt, string $incidentType, string $description = '', ?string $holder = null): StudentAttempt
    {
        [$attempt, $recorded, $isCancelled] = DB::transaction(function () use ($attempt, $holder): array {
            $locked = StudentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'in_progress' || ($locked->free_close_at !== null && now()->gte($locked->free_close_at))) {
                return [$locked, false, false];
            }
            $this->requireSession($locked, $holder);
            $locked->incidents_count++;
            $limit = $locked->exam?->max_allowed_incidents;
            $cancelled = $limit !== null && $locked->incidents_count > $limit;
            if ($cancelled) {
                $locked->fill(['status' => 'cancelled_by_incident', 'submitted_at' => now(), 'final_score' => 0.0]);
            }
            $locked->save();

            return [$locked, true, $cancelled];
        });

        if (! $recorded) {
            return $this->closeFreeIfDue($attempt);
        }
        $student = $attempt->student;
        $this->broadcastSafely(static fn () => ExamAttemptIncidentReported::dispatch(
            (int) $attempt->exam_id,
            (int) $attempt->id,
            (int) $attempt->student_id,
            $student ? $student->full_name : 'Estudiante #' . $attempt->student_id,
            $incidentType,
            $description,
            (int) $attempt->incidents_count,
            now()->format('H:i:s'),
            $isCancelled
        ));

        return $attempt;
    }

    /**
     * Entrega el intento una vez y conserva el borrador confirmado bajo bloqueo.
     *
     * @param StudentAttempt $attempt Intento por entregar.
     * @param string|null $holder Sesión del estudiante, cuando la entrega no viene del sistema.
     * @return StudentAttempt Intento finalizado.
     * @throws DomainException Si el intento no se puede entregar en su estado o sesión.
     */
    public function submitFinal(StudentAttempt $attempt, ?string $holder = null): StudentAttempt
    {
        if ($attempt->status !== 'in_progress') {
            throw new DomainException('El intento ya no se encuentra activo.');
        }

        $submitted = DB::transaction(function () use ($attempt, $holder): ?StudentAttempt {
            $attempt = StudentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status !== 'in_progress') {
                return null;
            }
            $this->requireSession($attempt, $holder);
            if ($attempt->free_close_at === null && $this->expireSequentialIfDue($attempt)) {
                return $attempt;
            }
            $this->preserveCurrentDraft($attempt, score: true);
            $attempt->loadMissing('exam');
            $exam = $attempt->exam;
            $answers = $attempt->answers()->with('examQuestion.question')->get();

            $objectiveScore = (float) $answers
                ->filter(static fn (StudentAnswer $a): bool => in_array($a->examQuestion?->question?->question_type, ['multiple_choice', 'matching'], true))
                ->sum('points_awarded');
            $aiEstimatedScore = (float) $answers
                ->whereNotNull('ai_similarity_score')
                ->sum('points_awarded');

            $qualitativeAnswers = $answers->filter(
                static fn (StudentAnswer $a): bool => in_array($a->examQuestion?->question?->question_type, ['open', 'code'], true)
            );

            // Bajo RF-IAR-001 (evaluación IA bajo demanda), las respuestas cualitativas con texto
            // quedan pendientes de evaluación/recoteje docente; las respuestas en blanco (ai_model_used = 'none')
            // ya fueron resueltas con puntaje 0 sin consumo de IA (RF-EVA-008).
            $pendingAiCount = $qualitativeAnswers
                ->filter(static fn (StudentAnswer $a): bool => $a->teacher_recoteje_score === null && $a->ai_model_used !== 'none')
                ->count();

            $totalExamWeight = $exam !== null
                ? (float) $exam->examQuestions()->sum('points_weight')
                : 0.0;
            $rawTotal = $objectiveScore + $aiEstimatedScore;
            $finalScore = $pendingAiCount === 0
                ? ($exam !== null
                    ? $this->gradeCalculator->normalizeScoreForExam($rawTotal, $exam, $totalExamWeight)
                    : round($rawTotal, 2))
                : null;

            $attempt->update([
                'status' => 'submitted',
                'submitted_at' => $attempt->free_close_at !== null && now()->gte($attempt->free_close_at) ? $attempt->free_close_at : now(),
                'objective_score' => $objectiveScore,
                'ai_estimated_score' => $aiEstimatedScore,
                'final_score' => $finalScore,
                'pending_ai_count' => $pendingAiCount,
            ]);

            return $attempt->fresh();
        });

        if ($submitted === null) {
            return $attempt->fresh();
        }
        if ($submitted->status === 'expired') {
            throw new DomainException('El tiempo límite del examen ha expirado.');
        }
        $this->examNotifications->attemptClosed($submitted);

        $studentUser = $submitted->relationLoaded('student') ? $submitted->student : $submitted->student()->first();
        $this->broadcastSafely(static fn () => ExamAttemptSubmitted::dispatch(
            (int) $submitted->exam_id,
            (int) $submitted->id,
            (int) $submitted->student_id,
            $studentUser ? $studentUser->full_name : 'Estudiante #' . $submitted->student_id,
            now()->format('H:i:s'),
            $submitted->final_score !== null ? (float) $submitted->final_score : null,
            (string) $submitted->status
        ));

        return $submitted;
    }

    /** Conserva las respuestas confirmadas y aplica el estado de expiración secuencial. */
    private function expireSequentialIfDue(StudentAttempt $attempt): bool
    {
        if ($this->isInProgress($attempt)) {
            return false;
        }

        $this->preserveCurrentDraft($attempt, score: false);
        $attempt->update(['status' => 'expired', 'submitted_at' => now()]);

        return true;
    }

    /**
     * Conserva las respuestas y registra la causa de detención de los intentos activos.
     * El cierre de la ventana y esta operación comparten la transacción del examen.
     *
     * @param Exam $exam Examen bloqueado por el cierre docente.
     * @param int $actorId Docente que detuvo el examen.
     * @return void
     */
    public function stopByTeacher(Exam $exam, int $actorId): void
    {
        $stoppedAt = now();
        StudentAttempt::where('exam_id', $exam->id)->where('status', 'in_progress')
            ->lockForUpdate()->chunkById(100, function ($attempts) use ($actorId, $stoppedAt): void {
                foreach ($attempts as $attempt) {
                    $this->preserveCurrentDraft($attempt, score: false);
                    $attempt->update(['status' => 'expired', 'submitted_at' => $stoppedAt]);
                    AuditLog::create([
                        'user_id' => $actorId,
                        'action' => StudentAttempt::ACTION_STOPPED_BY_TEACHER,
                        'auditable_type' => StudentAttempt::class,
                        'auditable_id' => $attempt->id,
                        'new_values' => ['status' => 'expired', 'cause' => 'Detención del examen por el docente'],
                    ]);
                }
            });
    }

    /**
     * Indica si el intento sigue en curso (RF-GRU-017) según su plazo individual
     * y la misma tolerancia que aplica la validación de envío.
     */
    public function isInProgress(StudentAttempt $attempt): bool
    {
        if ($attempt->status !== 'in_progress') {
            return false;
        }

        return ! $this->hasReachedDeadline($attempt, $this->attemptDeadline($attempt));
    }

    /**
     * Venció el tiempo propio del intento (RF-EXA-050): la hora límite del
     * examen solo impide nuevos inicios, y un intento iniciado continúa hasta
     * agotar los tiempos de sus preguntas. Si el docente detiene el examen, los
     * intentos en curso se finalizan en ExamAssemblyService::closeExamEarly
     * (RF-EXA-024).
     */
    public function hasReachedDeadline(StudentAttempt $attempt, ?CarbonInterface $deadline, ?CarbonInterface $now = null): bool
    {
        if ($deadline === null) {
            return false;
        }

        $now ??= now();

        return $attempt->free_close_at !== null ? $now->gte($deadline) : $now->gt($deadline);
    }

    /**
     * Momento máximo en que un intento puede seguir recibiendo respuestas: su
     * inicio más la suma de los tiempos de sus preguntas, la tolerancia de tres
     * segundos por pregunta y la tolerancia de red de 60 segundos.
     */
    public function attemptDeadline(StudentAttempt $attempt, ?int $totalQuestionSeconds = null): ?CarbonInterface
    {
        if ($attempt->free_close_at !== null) {
            return $attempt->free_close_at;
        }
        if ($attempt->started_at === null) {
            return null;
        }

        $sequence = array_map('intval', $attempt->question_sequence_payload ?? []);

        // Sin preguntas no hay tiempos que sumar: el límite es el cierre del examen
        if ($sequence === []) {
            $exam = $attempt->relationLoaded('exam') ? $attempt->exam : $attempt->exam()->first();

            return $exam?->ends_at?->copy()->addSeconds(60);
        }

        $totalSeconds = $totalQuestionSeconds ?? (int) ExamQuestion::query()->whereIn('id', $sequence)->sum('time_limit_seconds');

        return $attempt->started_at->copy()
            ->addSeconds($totalSeconds + count($sequence) * self::QUESTION_TIME_TOLERANCE_SECONDS + 60);
    }

    /**
     * @return array<string, mixed>
     */
    private function gradeAnswer(string $questionType, mixed $questionable, mixed $answerPayload, float $pointsWeight): array
    {
        return match ($questionType) {
            'multiple_choice' => $this->gradeMultipleChoice($questionable, $answerPayload, $pointsWeight),
            'matching' => $this->gradeMatching($questionable, $answerPayload, $pointsWeight),
            'open', 'code' => $this->registerQualitativeAnswer($answerPayload),
            default => throw ValidationException::withMessages(['question_type' => ['Tipo de pregunta desconocido.']]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function gradeMultipleChoice(mixed $item, mixed $answerPayload, float $pointsWeight): array
    {
        $rawIndex = is_array($answerPayload) ? ($answerPayload['selected_option_index'] ?? null) : $answerPayload;
        // Sin selección (avance sin responder o tiempo agotado, RF-EXA-031) vale cero: '' no debe leerse como la opción 0
        $selectedIndex = is_int($rawIndex) || (is_string($rawIndex) && ctype_digit($rawIndex)) ? (int) $rawIndex : -1;
        $isCorrect = $selectedIndex === (int) $item->correct_option_index;

        return ['is_correct' => $isCorrect, 'points_awarded' => $isCorrect ? $pointsWeight : 0.0];
    }

    /**
     * Calificación proporcional estrictamente biyectiva en relación de columnas [CRÍTICA-03]
     * (RF-EVA-002 / P-114): (pares_correctos / total_pares) * pointsWeight.
     *
     * @return array<string, mixed>
     */
    private function gradeMatching(mixed $item, mixed $answerPayload, float $pointsWeight): array
    {
        $expectedRaw = is_array($item->pairs_payload ?? null) ? $item->pairs_payload : [];
        $totalPairs = count($expectedRaw);
        if ($totalPairs === 0 || ! is_array($answerPayload)) {
            return ['is_correct' => false, 'points_awarded' => 0.0];
        }

        if (count($answerPayload) > $totalPairs) {
            return ['is_correct' => false, 'points_awarded' => 0.0];
        }

        $validLeftIds = collect($item->left_column_payload ?? [])
            ->pluck('id')
            ->filter()
            ->mapWithKeys(static fn (mixed $id): array => [(string) $id => true])
            ->all();
        $validRightIds = collect($item->right_column_payload ?? [])
            ->pluck('id')
            ->filter()
            ->mapWithKeys(static fn (mixed $id): array => [(string) $id => true])
            ->all();

        $expectedMap = [];
        foreach ($expectedRaw as $pair) {
            if (is_array($pair) && isset($pair['left_id'], $pair['right_id'])) {
                $expectedMap[(string) $pair['left_id']] = (string) $pair['right_id'];
            }
        }

        $seenLeft = [];
        $seenRight = [];
        $correctPairs = 0;

        foreach ($answerPayload as $pair) {
            if (! is_array($pair)) {
                return ['is_correct' => false, 'points_awarded' => 0.0];
            }

            $leftId = trim((string) ($pair['left_id'] ?? ''));
            $rightId = trim((string) ($pair['right_id'] ?? ''));

            if ($leftId === '' || isset($seenLeft[$leftId])) {
                return ['is_correct' => false, 'points_awarded' => 0.0];
            }
            if ($validLeftIds !== [] && ! isset($validLeftIds[$leftId])) {
                return ['is_correct' => false, 'points_awarded' => 0.0];
            }
            $seenLeft[$leftId] = true;

            if ($rightId === '') {
                continue;
            }
            if (($validRightIds !== [] && ! isset($validRightIds[$rightId])) || isset($seenRight[$rightId])) {
                return ['is_correct' => false, 'points_awarded' => 0.0];
            }
            $seenRight[$rightId] = true;

            if (isset($expectedMap[$leftId]) && $expectedMap[$leftId] === $rightId) {
                $correctPairs++;
            }
        }

        $score = round(($correctPairs / $totalPairs) * $pointsWeight, 2);
        $isCorrect = $correctPairs === $totalPairs;

        return [
            'is_correct' => $isCorrect,
            'points_awarded' => $score,
        ];
    }

    /**
     * Registra respuestas abiertas y de código sin invocar a la IA durante la
     * aplicación del examen (RF-IAR-001 Opción A / ADR-059): las respuestas en
     * blanco se resuelven de inmediato con puntaje 0 sin consumo de IA
     * (RF-EVA-008) y las respuestas con contenido quedan pendientes de
     * evaluación bajo demanda o recoteje docente.
     *
     * @return array<string, mixed>
     */
    private function registerQualitativeAnswer(mixed $answerPayload): array
    {
        $studentText = StudentAnswer::qualitativeText($answerPayload);

        if ($studentText === '') {
            return [
                'is_correct' => false,
                'points_awarded' => 0.0,
                'ai_similarity_score' => 0.0,
                'ai_meets_goal' => false,
                'ai_feedback' => 'Respuesta en blanco; se asigna puntaje 0 sin consumo de IA.',
                'ai_evaluated_at' => now(),
                'ai_is_fallback' => false,
                'ai_model_used' => 'none',
            ];
        }

        return [
            'is_correct' => null,
            'points_awarded' => 0.0,
            'ai_similarity_score' => null,
            'ai_meets_goal' => null,
            'ai_feedback' => null,
            'ai_evaluated_at' => null,
            'ai_is_fallback' => false,
            'ai_model_used' => null,
        ];
    }

    /**
     * Construye el payload de la pregunta ocultando la respuesta correcta y
     * aplicando la paráfrasis congelada en snapshot o activa (RF-BAN-010).
     *
     * @return array<string, mixed>
     */
    private function questionPayloadWithoutAnswer(ExamQuestion $examQuestion, ?StudentAttempt $attempt = null): array
    {
        $question = $examQuestion->question;
        $item = $question->questionable;

        $resolved = $this->resolvedStemData($examQuestion, $attempt);
        $stem = $resolved['stem'];

        $itemPayload = match ($question->question_type) {
            'multiple_choice' => ['options' => $item->options_payload],
            'matching' => ['left_items' => $item->left_column_payload, 'right_items' => $item->right_column_payload],
            'open' => [],
            'code' => [
                'programming_language' => $item->programming_language,
                'is_find_error' => $item->is_find_error,
                'starter_code' => $item->starter_code,
                'instructions' => $item->instructions,
            ],
            default => [],
        };

        $basePayload = [
            'id' => $question->id,
            'title' => $question->title,
            'stem' => $stem,
            'question_type' => $question->question_type,
            // Imagen y PDF de la pregunta (RF-BAN-012, RF-BAN-013)
            'has_image' => $question->image_path !== null,
            'has_pdf' => $question->pdf_path !== null,
        ];

        if ($resolved['paraphrase_id'] !== null) {
            $basePayload['paraphrase_id'] = $resolved['paraphrase_id'];
            $basePayload['paraphrased_text'] = $stem;
        }

        return array_merge($basePayload, $itemPayload);
    }

    /**
     * Enunciado que el estudiante vio en su intento (paráfrasis congelada en
     * snapshot o rotación determinista), para mostrarlo igual en la revisión
     * posterior (RF-REP-005).
     */
    public function visibleStem(ExamQuestion $examQuestion, ?StudentAttempt $attempt = null): string
    {
        return $this->resolvedStemData($examQuestion, $attempt)['stem'];
    }

    /**
     * Construye el mapa inmutable de enunciados servidos por exam_question_id
     * al iniciar el intento, con rotación 1 + N (enunciado raíz + paráfrasis)
     * cargando las relaciones en bloque sin N+1 [ALTA-09 / MEDIA-05].
     *
     * @param array<int, int> $sequence
     * @return array<string, array{paraphrase_id: ?int, stem: string}>
     */
    private function buildParaphraseSnapshot(array $sequence, int $studentId): array
    {
        if ($sequence === []) {
            return [];
        }

        $examQuestions = ExamQuestion::with('question.paraphrases')
            ->whereIn('id', $sequence)
            ->get()
            ->keyBy('id');

        $snapshot = [];
        foreach ($sequence as $examQuestionId) {
            $examQuestion = $examQuestions->get((int) $examQuestionId);
            if ($examQuestion === null || $examQuestion->question === null) {
                continue;
            }

            $selected = $this->selectVariantForStudent($examQuestion, $studentId);
            $snapshot[(string) $examQuestion->id] = [
                'paraphrase_id' => $selected['paraphrase_id'],
                'stem' => $selected['stem'],
            ];
        }

        return $snapshot;
    }

    /**
     * Resuelve el enunciado y paráfrasis efectivos priorizando el snapshot congelado
     * en el intento para garantizar inmutabilidad histórica [ALTA-09 / MEDIA-05].
     *
     * @return array{paraphrase_id: ?int, paraphrase: ?QuestionParaphrase, stem: string}
     */
    private function resolvedStemData(ExamQuestion $examQuestion, ?StudentAttempt $attempt): array
    {
        $question = $examQuestion->question;

        if ($attempt !== null && is_array($attempt->paraphrase_snapshot_payload)) {
            $snapshotEntry = $attempt->paraphrase_snapshot_payload[(string) $examQuestion->id]
                ?? $attempt->paraphrase_snapshot_payload[(int) $examQuestion->id]
                ?? null;

            if (is_array($snapshotEntry) && array_key_exists('stem', $snapshotEntry)) {
                $paraphraseId = isset($snapshotEntry['paraphrase_id']) && $snapshotEntry['paraphrase_id'] !== null
                    ? (int) $snapshotEntry['paraphrase_id']
                    : null;
                $frozenStem = (string) $snapshotEntry['stem'];
                $paraphrase = null;

                if ($paraphraseId !== null) {
                    $paraphrases = $question->relationLoaded('paraphrases')
                        ? $question->paraphrases
                        : $question->paraphrases()->get();
                    $paraphrase = $paraphrases->firstWhere('id', $paraphraseId);
                    if ($paraphrase === null) {
                        $paraphrase = new QuestionParaphrase([
                            'question_id' => $question->id,
                            'stem' => $frozenStem,
                        ]);
                        $paraphrase->id = $paraphraseId;
                        $paraphrase->exists = true;
                    }
                }

                return [
                    'paraphrase_id' => $paraphraseId,
                    'paraphrase' => $paraphrase,
                    'stem' => $frozenStem,
                ];
            }
        }

        $seedActor = (int) ($attempt?->student_id ?? $attempt?->id ?? 0);

        return $this->selectVariantForStudent($examQuestion, $seedActor);
    }

    /**
     * @return array{paraphrase_id: ?int, paraphrase: ?QuestionParaphrase, stem: string}
     */
    private function selectVariantForStudent(ExamQuestion $examQuestion, int $seedActor): array
    {
        $question = $examQuestion->question;
        $paraphrases = $question->relationLoaded('paraphrases')
            ? $question->paraphrases
            : $question->paraphrases()->get();

        if ($paraphrases->isEmpty()) {
            return [
                'paraphrase_id' => null,
                'paraphrase' => null,
                'stem' => (string) $question->stem,
            ];
        }

        $explicitParaphraseId = $examQuestion->getAttribute('paraphrase_id');
        if ($explicitParaphraseId !== null) {
            $chosen = $paraphrases->firstWhere('id', (int) $explicitParaphraseId) ?? $paraphrases->first();

            return [
                'paraphrase_id' => $chosen !== null ? (int) $chosen->id : null,
                'paraphrase' => $chosen,
                'stem' => $chosen !== null ? (string) $chosen->stem : (string) $question->stem,
            ];
        }

        // Rotación 1 + N incluyendo el enunciado original en el índice 0
        $variants = array_merge([null], $paraphrases->values()->all());
        $hash = (int) sprintf('%u', crc32($seedActor . ':' . $examQuestion->id));
        $chosen = $variants[$hash % count($variants)];

        if ($chosen instanceof QuestionParaphrase) {
            return [
                'paraphrase_id' => (int) $chosen->id,
                'paraphrase' => $chosen,
                'stem' => (string) $chosen->stem,
            ];
        }

        return [
            'paraphrase_id' => null,
            'paraphrase' => null,
            'stem' => (string) $question->stem,
        ];
    }

    private function resolveActiveParaphrase(ExamQuestion $examQuestion, ?StudentAttempt $attempt): ?QuestionParaphrase
    {
        return $this->resolvedStemData($examQuestion, $attempt)['paraphrase'];
    }
}
