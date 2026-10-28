<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptController.php
 * Ubicación:   app/Http/Controllers/Api/V1/AttemptController.php
 * Descripción: Aplicación contrarreloj del examen desde la app móvil
 *              (Módulo 7): api.v1.exams.take, api.v1.exams.answers y
 *              api.v1.exams.incidents (MPM-V4 Tabla 12, MPW-V2 §6.1, §6.3).
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttemptAnswerApiRequest;
use App\Http\Requests\AttemptIncidentStoreRequest;
use App\Http\Requests\AttemptTakeRequest;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Services\AttemptService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    public function __construct(private readonly AttemptService $attemptService)
    {
    }

    /**
     * api.v1.exams.take. `exam_id` va en el cuerpo de la petición, no en
     * la URL. Inicia el intento si no existe o continúa el ya existente.
     */
    public function take(AttemptTakeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $exam = Exam::findOrFail($data['exam_id']);

        try {
            $attempt = $this->attemptService->start($exam, $request->user());
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success($this->questionResponse($attempt));
    }

    /**
     * api.v1.exams.answers. Valida la pregunta activa en servidor y califica
     * la respuesta mediante AttemptService.
     */
    public function answers(AttemptAnswerApiRequest $request, Exam $exam): JsonResponse
    {
        $attempt = $this->attemptForUser($exam, $request->user());
        $data = $request->validated();

        $examQuestion = $this->currentExamQuestionOrFail($attempt);

        // RW-20: no se puede regresar ni adelantar de pregunta. Se valida
        // contra la pregunta activa real del servidor.
        if (isset($data['question_id'])) {
            abort_unless(
                (string) $examQuestion->question_id === (string) $data['question_id'],
                409,
                'La pregunta activa cambió; vuelve a consultar el examen.',
            );
        } elseif (isset($data['exam_question_id'])) {
            abort_unless(
                (int) $examQuestion->id === (int) $data['exam_question_id'],
                409,
                'La pregunta activa cambió; vuelve a consultar el examen.',
            );
        }

        try {
            $this->attemptService->submitAnswer($attempt, $examQuestion->id, $this->normalizeAnswerPayload($data));

            $attempt = $attempt->fresh();

            if ($attempt->current_question_index >= count($attempt->question_sequence_payload)) {
                $this->attemptService->submitFinal($attempt);
                $attempt = $attempt->fresh();
            }
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success($this->questionResponse($attempt));
    }

    /**
     * Finaliza explícitamente un intento en curso si el cliente lo invoca.
     */
    public function finish(Request $request, Exam $exam): JsonResponse
    {
        $attempt = $this->attemptForUser($exam, $request->user());

        try {
            $attempt = $this->attemptService->submitFinal($attempt);
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success($this->questionResponse($attempt), 'Examen finalizado.');
    }

    /**
     * api.v1.exams.incidents (§7.7). Incrementa el contador de incidentes
     * del intento en curso y, si se excede `max_allowed_incidents` del
     * examen, lo cancela (`cancelled_by_incident`).
     */
    public function incidents(AttemptIncidentStoreRequest $request, Exam $exam): JsonResponse
    {
        $attempt = $this->attemptForUser($exam, $request->user());
        $request->validated();

        if ($attempt->status === 'in_progress') {
            $attempt->increment('incidents_count');
            $attempt->refresh();

            if ($attempt->incidents_count > $exam->max_allowed_incidents) {
                $attempt->update(['status' => 'cancelled_by_incident', 'submitted_at' => now()]);
            }
        }

        return ApiResponse::success(null, 'Incidente registrado.');
    }

    private function attemptForUser(Exam $exam, User $user): StudentAttempt
    {
        $attempt = StudentAttempt::where('exam_id', $exam->id)->where('student_id', $user->id)->first();

        abort_if($attempt === null, 404, 'No has iniciado este examen.');

        return $attempt;
    }

    private function currentExamQuestionOrFail(StudentAttempt $attempt): ExamQuestion
    {
        abort_if($attempt->status !== 'in_progress', 409, 'Este intento ya no está en curso.');

        $sequence = $attempt->question_sequence_payload ?? [];

        abort_if($attempt->current_question_index >= count($sequence), 409, 'El examen ya terminó.');

        return ExamQuestion::with(['question.questionable', 'question.paraphrases'])
            ->findOrFail($sequence[$attempt->current_question_index]);
    }

    /**
     * Traduce el cuerpo de api.v1.exams.answers a la forma que espera
     * AttemptService::submitAnswer (compartida con el panel web).
     *
     * @param array<string, mixed> $data
     */
    private function normalizeAnswerPayload(array $data): mixed
    {
        if (array_key_exists('selected_option_ids', $data) && is_array($data['selected_option_ids'])) {
            $firstSelected = reset($data['selected_option_ids']);

            return ['selected_option_index' => $firstSelected === false ? -1 : (int) $firstSelected];
        }

        if (array_key_exists('pairs', $data) && is_array($data['pairs'])) {
            return collect($data['pairs'])
                ->map(fn ($rightId, $leftId) => ['left_id' => (string) $leftId, 'right_id' => (string) $rightId])
                ->values()
                ->all();
        }

        if (array_key_exists('code', $data)) {
            return (string) ($data['code'] ?? '');
        }

        if (array_key_exists('given_answer', $data) && $data['given_answer'] !== null) {
            return $data['given_answer'];
        }

        return (string) ($data['text'] ?? '');
    }

    /**
     * @return array{question: array<string, mixed>|null, remaining_seconds: int|null}
     */
    private function questionResponse(StudentAttempt $attempt): array
    {
        $current = $this->attemptService->currentQuestion($attempt);

        if ($current === null) {
            return ['question' => null, 'remaining_seconds' => null];
        }

        return [
            'question' => $this->questionPayload($current['question']),
            'remaining_seconds' => $current['time_limit_seconds'],
        ];
    }

    /**
     * Traduce el payload interno de AttemptService::currentQuestion
     * a la forma exacta de QuestionModel del cliente móvil (question_model.dart).
     *
     * @param array<string, mixed> $question
     * @return array<string, mixed>
     */
    private function questionPayload(array $question): array
    {
        $base = [
            'id' => (string) $question['id'],
            'statement' => $question['stem'],
            'question_type' => $question['question_type'],
        ];

        if (! empty($question['paraphrase_id'])) {
            $base['paraphrase_id'] = $question['paraphrase_id'];
            $base['paraphrased_text'] = $question['paraphrased_text'] ?? $question['stem'];
        }

        return match ($question['question_type']) {
            'multiple_choice' => $base + [
                'options' => collect($question['options'])
                    ->map(fn (array $option) => ['id' => (string) $option['index'], 'text' => $option['text']])
                    ->values()
                    ->all(),
            ],
            'matching' => $base + [
                'left_column' => $question['left_items'],
                'right_column' => $question['right_items'],
            ],
            'code' => $base + [
                'language' => $question['programming_language'],
                'initial_code' => $question['starter_code'] ?? '',
            ],
            default => $base,
        };
    }
}
