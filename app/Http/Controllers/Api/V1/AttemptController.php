<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptController.php
 * Ubicación:   app/Http/Controllers/Api/V1/AttemptController.php
 * Descripción: Aplicación contrarreloj del examen desde la app móvil
 *              (Módulo 7): iniciar intento, obtener la pregunta actual
 *              (sin la respuesta correcta), responder y no poder regresar
 *              (Listado: "no deberá regresar a una pregunta anterior"),
 *              cerrar el intento y calificar lo objetivo al momento;
 *              abiertas/código se evalúan aquí mismo de forma síncrona con
 *              AiEvaluatorContract porque QUEUE_CONNECTION=sync en este
 *              entorno de demo (en producción, encolar como
 *              EvaluateStudentAnswerJob, Módulo 8).
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Contracts\AiEvaluatorContract;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Services\ExamAssemblyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttemptController extends Controller
{
    public function __construct(
        private readonly ExamAssemblyService $assemblyService,
        private readonly AiEvaluatorContract $aiEvaluator,
    ) {
    }

    public function start(Request $request, Exam $exam): JsonResponse
    {
        $student = $request->user();

        abort_unless($exam->is_published, 404);
        abort_unless($exam->groups()->whereHas('students', fn ($q) => $q->where('acs_users.id', $student->id))->exists(), 403);

        $attempt = StudentAttempt::firstOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $student->id],
            [
                'group_id' => $exam->groups()->whereHas('students', fn ($q) => $q->where('acs_users.id', $student->id))->first()->id,
                'question_sequence_payload' => $this->assemblyService->buildQuestionSequence($exam),
                'started_at' => now(),
                'status' => 'in_progress',
            ],
        );

        return response()->json(['attempt' => $attempt]);
    }

    public function currentQuestion(StudentAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnership($attempt);

        $sequence = $attempt->question_sequence_payload;

        if ($attempt->current_question_index >= count($sequence)) {
            return response()->json(['finished' => true]);
        }

        $examQuestionId = $sequence[$attempt->current_question_index];
        $examQuestion = ExamQuestion::with('question.questionable')->findOrFail($examQuestionId);

        return response()->json([
            'exam_question_id' => $examQuestion->id,
            'index' => $attempt->current_question_index,
            'total' => count($sequence),
            'time_limit_seconds' => $examQuestion->time_limit_seconds,
            'points_weight' => $examQuestion->points_weight,
            'question' => $this->questionPayloadWithoutAnswer($examQuestion),
        ]);
    }

    public function submitAnswer(Request $request, StudentAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnership($attempt);

        $data = $request->validate([
            'exam_question_id' => ['required', 'integer', 'exists:acs_exam_question,id'],
            'answer_payload' => ['required'],
        ]);

        $examQuestion = ExamQuestion::with('question.questionable')->findOrFail($data['exam_question_id']);
        $question = $examQuestion->question;
        $questionable = $question->questionable;

        $graded = $this->gradeAnswer($question->question_type, $questionable, $data['answer_payload'], (float) $examQuestion->points_weight);

        StudentAnswer::updateOrCreate(
            ['student_attempt_id' => $attempt->id, 'exam_question_id' => $examQuestion->id],
            array_merge($graded, [
                'answer_payload' => $data['answer_payload'],
                'answered_at' => now(),
            ]),
        );

        // No se puede regresar a una pregunta anterior aunque sobre tiempo.
        $attempt->increment('current_question_index');

        return response()->json(['message' => 'Respuesta registrada.']);
    }

    public function submitFinal(StudentAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnership($attempt);

        $answers = $attempt->answers()->get();

        $objectiveScore = (float) $answers->whereNotNull('is_correct')->sum('points_awarded');
        $aiEstimatedScore = (float) $answers->whereNotNull('ai_similarity_score')->sum('points_awarded');
        $pendingAiCount = $answers->whereNull('ai_evaluated_at')
            ->filter(fn (StudentAnswer $a) => $a->examQuestion->question->question_type === 'open'
                || $a->examQuestion->question->question_type === 'code')
            ->count();

        $attempt->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'objective_score' => $objectiveScore,
            'ai_estimated_score' => $aiEstimatedScore,
            'final_score' => $pendingAiCount === 0 ? $objectiveScore + $aiEstimatedScore : null,
            'pending_ai_count' => $pendingAiCount,
        ]);

        return response()->json(['attempt' => $attempt->fresh(), 'result_visibility_mode' => $attempt->exam->result_visibility_mode]);
    }

    /**
     * @return array<string, mixed>
     */
    private function gradeAnswer(string $questionType, mixed $questionable, mixed $answerPayload, float $pointsWeight): array
    {
        return match ($questionType) {
            'multiple_choice' => $this->gradeMultipleChoice($questionable, $answerPayload, $pointsWeight),
            'matching' => $this->gradeMatching($questionable, $answerPayload, $pointsWeight),
            'open' => $this->gradeWithAi($questionable->reference_answer, (string) $answerPayload, $pointsWeight),
            'code' => $this->gradeWithAi($questionable->reference_solution, (string) $answerPayload, $pointsWeight),
            default => throw ValidationException::withMessages(['question_type' => ['Tipo de pregunta desconocido.']]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function gradeMultipleChoice(mixed $item, mixed $answerPayload, float $pointsWeight): array
    {
        $selectedIndex = (int) (is_array($answerPayload) ? ($answerPayload['selected_option_index'] ?? -1) : $answerPayload);
        $isCorrect = $selectedIndex === (int) $item->correct_option_index;

        return [
            'is_correct' => $isCorrect,
            'points_awarded' => $isCorrect ? $pointsWeight : 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gradeMatching(mixed $item, mixed $answerPayload, float $pointsWeight): array
    {
        $submittedPairs = is_array($answerPayload) ? $answerPayload : [];
        $correctPairs = $item->pairs_payload;

        $isCorrect = $this->normalizePairs($submittedPairs) === $this->normalizePairs($correctPairs);

        return [
            'is_correct' => $isCorrect,
            'points_awarded' => $isCorrect ? $pointsWeight : 0.0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $pairs
     * @return array<int, string>
     */
    private function normalizePairs(array $pairs): array
    {
        $normalized = array_map(
            static fn (array $pair) => ($pair['left_id'] ?? '').'-'.($pair['right_id'] ?? ''),
            $pairs,
        );
        sort($normalized);

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function gradeWithAi(string $referenceAnswer, string $studentAnswer, float $pointsWeight): array
    {
        $result = $this->aiEvaluator->evaluate($studentAnswer, $referenceAnswer);

        return [
            'ai_similarity_score' => $result['similarity_score'],
            'ai_meets_goal' => $result['meets_goal'],
            'ai_feedback' => $result['feedback'],
            'ai_evaluated_at' => now(),
            'points_awarded' => $result['meets_goal'] ? $pointsWeight : 0.0,
        ];
    }

    /**
     * Nunca se incluye la respuesta correcta/de referencia en lo que ve el
     * alumno mientras contesta.
     *
     * @return array<string, mixed>
     */
    private function questionPayloadWithoutAnswer(ExamQuestion $examQuestion): array
    {
        $question = $examQuestion->question;
        $item = $question->questionable;

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

        return array_merge([
            'id' => $question->id,
            'title' => $question->title,
            'stem' => $question->stem,
            'question_type' => $question->question_type,
        ], $itemPayload);
    }

    private function authorizeOwnership(StudentAttempt $attempt): void
    {
        abort_unless($attempt->student_id === request()->user()->id, 403);
    }
}
