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
 *              api.v1.exams.incidents (MPM-V4 Tabla 12). Envoltura JSON
 *              sobre AttemptService (lógica compartida con el panel web,
 *              app/Services) — este controlador solo traduce entre la
 *              forma interna compartida y la forma exacta que espera
 *              ExamQuestionResponseModel/QuestionModel del cliente móvil
 *              (ver docs/04_DECISIONES_ADR.md para el detalle de cada
 *              punto que estaba "pendiente de confirmación", DE-03).
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\StudentAttempt;
use App\Models\User;
use App\Services\AttemptService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    private const INCIDENT_TYPES = ['app_left', 'multi_window', 'focus_lost'];

    public function __construct(private readonly AttemptService $attemptService)
    {
    }

    /**
     * api.v1.exams.take. `exam_id` va en el cuerpo de la petición, no en
     * la URL (acs-exam-mobile, exam_repository.dart v1.2.0: "Tabla 12 no
     * define un endpoint con {id} para esta ruta"). Inicia el intento si
     * no existe o continúa el ya existente (AttemptService::start usa
     * firstOrCreate, es idempotente).
     */
    public function take(Request $request): JsonResponse
    {
        $data = $request->validate(['exam_id' => ['required', 'integer', 'exists:acs_exams,id']]);

        $exam = Exam::findOrFail($data['exam_id']);
        $attempt = $this->attemptService->start($exam, $request->user());

        return ApiResponse::success($this->questionResponse($attempt));
    }

    /**
     * api.v1.exams.answers. El cuerpo trae `question_id` (el id de
     * acs_questions que el cliente recibió en `question.id`, nunca el id
     * interno de acs_exam_question) más el campo propio del tipo de
     * reactivo activo: `selected_option_ids` (opción múltiple), `pairs`
     * (relación de columnas), `text` (abierta) o `code` (programación) —
     * forma de AnswerPayload.toJson() del cliente móvil, answer_payload.dart.
     */
    public function answers(Request $request, Exam $exam): JsonResponse
    {
        $attempt = $this->attemptForUser($exam, $request->user());

        $data = $request->validate([
            'question_id' => ['required'],
            'selected_option_ids' => ['sometimes', 'array'],
            'selected_option_ids.*' => ['string'],
            'pairs' => ['sometimes', 'array'],
            'text' => ['sometimes', 'string'],
            'code' => ['sometimes', 'string'],
        ]);

        $examQuestion = $this->currentExamQuestionOrFail($attempt);

        // RW-20: no se puede regresar ni adelantar de pregunta. Se valida
        // contra la pregunta activa real del servidor, nunca se confía en
        // que el cliente mande el reactivo correcto.
        abort_unless(
            (string) $examQuestion->question_id === (string) $data['question_id'],
            409,
            'La pregunta activa cambió; vuelve a consultar el examen.',
        );

        $this->attemptService->submitAnswer($attempt, $examQuestion->id, $this->normalizeAnswerPayload($data));

        $attempt = $attempt->fresh();

        if ($attempt->current_question_index >= count($attempt->question_sequence_payload)) {
            $this->attemptService->submitFinal($attempt);
            $attempt = $attempt->fresh();
        }

        return ApiResponse::success($this->questionResponse($attempt));
    }

    /**
     * api.v1.exams.incidents (§7.7). Incrementa el contador de incidentes
     * del intento en curso y, si se excede `max_allowed_incidents` del
     * examen, lo cancela (`cancelled_by_incident`) — el valor de
     * `incident_type` no cambia la gravedad, cualquiera de los tres
     * cuenta igual (decisión propia, ver docs/04_DECISIONES_ADR.md).
     */
    public function incidents(Request $request, Exam $exam): JsonResponse
    {
        $attempt = $this->attemptForUser($exam, $request->user());

        $request->validate([
            'incident_type' => ['required', 'string', 'in:'.implode(',', self::INCIDENT_TYPES)],
        ]);

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
        $sequence = $attempt->question_sequence_payload;

        abort_if($attempt->current_question_index >= count($sequence), 409, 'El examen ya terminó.');

        return ExamQuestion::with('question.questionable')->findOrFail($sequence[$attempt->current_question_index]);
    }

    /**
     * Traduce el cuerpo de api.v1.exams.answers a la forma que ya espera
     * AttemptService::submitAnswer (compartida con el panel web): un
     * índice entero para opción múltiple, un arreglo left_id/right_id
     * para relación de columnas, o el texto/código crudo para
     * abierta/programación.
     */
    private function normalizeAnswerPayload(array $data): mixed
    {
        if (array_key_exists('selected_option_ids', $data)) {
            $firstSelected = reset($data['selected_option_ids']);

            return ['selected_option_index' => $firstSelected === false ? -1 : (int) $firstSelected];
        }

        if (array_key_exists('pairs', $data)) {
            return collect($data['pairs'])
                ->map(fn ($rightId, $leftId) => ['left_id' => (string) $leftId, 'right_id' => (string) $rightId])
                ->values()
                ->all();
        }

        if (array_key_exists('code', $data)) {
            return $data['code'];
        }

        return $data['text'] ?? '';
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
     * (compartido con el panel web) a la forma exacta de QuestionModel
     * del cliente móvil (question_model.dart): `id`, `statement`,
     * `question_type` y el campo propio de cada subclase. Las opciones de
     * opción múltiple se identifican por su índice (como texto) porque
     * MultipleChoiceItem no tiene un id propio por opción
     * (QuestionController::store: options_payload = [{index, text}]).
     * Nunca se incluye cuál es la opción/par correcto.
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
