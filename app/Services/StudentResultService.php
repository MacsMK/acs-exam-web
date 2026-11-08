<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentResultService.php
 * Ubicación:   app/Services/StudentResultService.php
 * Descripción: Consultas del estudiante sobre sus propios resultados
 *              (ADR-037): estado «Pendiente de evaluación» (RF-REP-002),
 *              puntos por pregunta y por apartado (RF-REP-004), respuestas y
 *              soluciones de referencia una vez publicada la calificación y
 *              cerrado el acceso al examen (RF-REP-005) y comentarios del
 *              docente sin justificaciones de IA (RF-REP-006).
 * ============================================================================
 */

namespace App\Services;

use App\Models\ExamQuestion;
use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class StudentResultService
{
    public const PENDING_MESSAGE = 'Pendiente de evaluación';

    public const REVIEW_LOCKED_MESSAGE = 'Podrás consultar tus respuestas y las soluciones cuando tu calificación esté publicada y haya terminado el plazo de acceso al examen.';

    /**
     * Segundos de tolerancia tras `ends_at`: el servidor todavía acepta el
     * envío de un intento hasta ese momento (AttemptService), por lo que las
     * soluciones no se publican antes.
     */
    private const ACCESS_CLOSE_TOLERANCE_SECONDS = 60;

    public function __construct(private readonly AttemptService $attemptService)
    {
    }

    /**
     * Intento del propio estudiante en uno de sus grupos actuales; cualquier
     * otro caso se responde 404 para no revelar la existencia del intento.
     */
    public function ownedAttemptOrFail(User $student, int $attemptId): StudentAttempt
    {
        return StudentAttempt::where('id', $attemptId)
            ->where('student_id', $student->id)
            ->inCurrentGroupsOfStudent($student->id)
            ->with('exam')
            ->firstOrFail();
    }

    /**
     * Intentos enviados cuya evaluación definitiva sigue pendiente
     * (RF-REP-002); no se expone ningún dato de la calificación.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pendingRowsForStudent(User $student): Collection
    {
        return StudentAttempt::where('student_id', $student->id)
            ->inCurrentGroupsOfStudent($student->id)
            ->where('status', 'submitted')
            ->whereNull('final_score')
            ->with('exam')
            ->get()
            ->map(fn (StudentAttempt $attempt): array => [
                'submitted_at' => $attempt->submitted_at,
                'row' => [
                    'id' => (string) $attempt->id,
                    'exam_name' => $attempt->exam->title,
                    'score' => null,
                    'weight' => 100.0,
                    'is_passing' => null,
                    'status' => 'pending',
                ],
            ]);
    }

    /**
     * Detalle del resultado: pendiente (RF-REP-002) o calificación publicada
     * con puntos por apartado y por pregunta (RF-REP-004) y comentarios del
     * docente (RF-REP-006).
     *
     * @return array<string, mixed>
     */
    public function detail(StudentAttempt $attempt): array
    {
        $exam = $attempt->exam;

        if ($this->isPending($attempt)) {
            return [
                'id' => (string) $attempt->id,
                'exam_name' => $exam->title,
                'status' => 'pending',
                'message' => self::PENDING_MESSAGE,
            ];
        }

        $this->abortUnlessPublished($attempt);

        $answers = $this->answersByExamQuestion($attempt);
        $sections = $this->servedQuestions($attempt)
            ->groupBy('exam_section_id')
            ->map(function (Collection $questions) use ($answers): array {
                $rows = $questions->map(function (ExamQuestion $examQuestion) use ($answers): array {
                    $earned = (float) ($answers->get($examQuestion->id)?->points_awarded ?? 0.0);

                    return [
                        'id' => (string) $examQuestion->question_id,
                        'title' => $examQuestion->question->title,
                        'points_earned' => round($earned, 2),
                        'points_possible' => round((float) $examQuestion->points_weight, 2),
                    ];
                })->values();

                return [
                    'title' => $questions->first()->examSection->title,
                    'points_earned' => round((float) $rows->sum('points_earned'), 2),
                    'points_possible' => round((float) $rows->sum('points_possible'), 2),
                    'questions' => $rows->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => (string) $attempt->id,
            'exam_name' => $exam->title,
            'status' => 'published',
            'score' => round((float) $attempt->final_score, 2),
            'grading_scale' => $exam->grading_scale,
            'is_passing' => (float) $attempt->final_score >= ($exam->grading_scale === '0-100' ? 60.0 : 6.0),
            'sections' => $sections,
            'teacher_comments' => $this->teacherComments($answers),
            'review_available' => $this->reviewIsAvailable($attempt),
        ];
    }

    /**
     * Respuestas del estudiante y soluciones de referencia (RF-REP-005): solo
     * con la calificación publicada y el acceso al examen cerrado.
     *
     * @return array<string, mixed>
     */
    public function review(StudentAttempt $attempt): array
    {
        abort_unless($this->reviewIsAvailable($attempt), 403, self::REVIEW_LOCKED_MESSAGE);

        $answers = $this->answersByExamQuestion($attempt);

        return [
            'id' => (string) $attempt->id,
            'exam_name' => $attempt->exam->title,
            'questions' => $this->servedQuestions($attempt)
                ->map(function (ExamQuestion $examQuestion) use ($attempt, $answers): array {
                    $question = $examQuestion->question;
                    $answer = $answers->get($examQuestion->id);
                    $item = $question->questionable;

                    return [
                        'id' => (string) $question->id,
                        'title' => $question->title,
                        'stem' => $this->attemptService->visibleStem($examQuestion, $attempt),
                        'question_type' => $question->question_type,
                        'student_answer' => $answer === null
                            ? null
                            : $this->formatStudentAnswer($question->question_type, $item, $answer->answer_payload),
                        'reference_solution' => $this->formatReferenceSolution($question->question_type, $item),
                        'points_earned' => round((float) ($answer?->points_awarded ?? 0.0), 2),
                        'points_possible' => round((float) $examQuestion->points_weight, 2),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * La evaluación definitiva está pendiente mientras el intento enviado no
     * tenga calificación final (RF-EVA-010: respuestas por evaluar).
     */
    public function isPending(StudentAttempt $attempt): bool
    {
        return $attempt->status === 'submitted' && $attempt->final_score === null;
    }

    /**
     * Calificación publicada y acceso al examen cerrado (RF-REP-005).
     */
    public function reviewIsAvailable(StudentAttempt $attempt): bool
    {
        if ($attempt->final_score === null) {
            return false;
        }

        $exam = $attempt->exam;

        return $exam->ends_at === null
            || now()->gt($exam->ends_at->copy()->addSeconds(self::ACCESS_CLOSE_TOLERANCE_SECONDS));
    }

    private function abortUnlessPublished(StudentAttempt $attempt): void
    {
        abort_if($attempt->final_score === null, 404, 'No hay un resultado publicado para este intento.');
    }

    /**
     * @return Collection<int, StudentAnswer>
     */
    private function answersByExamQuestion(StudentAttempt $attempt): Collection
    {
        return $attempt->answers()->get()->keyBy('exam_question_id');
    }

    /**
     * Preguntas servidas al estudiante, en el orden del intento.
     *
     * @return EloquentCollection<int, ExamQuestion>
     */
    private function servedQuestions(StudentAttempt $attempt): EloquentCollection
    {
        $sequence = array_map('intval', $attempt->question_sequence_payload ?? []);

        $byId = ExamQuestion::with(['question.questionable', 'examSection'])
            ->whereIn('id', $sequence)
            ->get()
            ->keyBy('id');

        return (new EloquentCollection($sequence))
            ->map(fn (int $id) => $byId->get($id))
            ->filter()
            ->values();
    }

    /**
     * Solo comentarios escritos por el docente; las justificaciones
     * provisionales de IA (`ai_feedback`) nunca se publican (RF-REP-006).
     *
     * @param Collection<int, StudentAnswer> $answers
     * @return array<int, string>
     */
    private function teacherComments(Collection $answers): array
    {
        return $answers
            ->pluck('teacher_recoteje_notes')
            ->map(fn (?string $note): string => trim((string) $note))
            ->filter(fn (string $note): bool => $note !== '')
            ->values()
            ->all();
    }

    private function formatStudentAnswer(string $questionType, mixed $item, mixed $payload): ?string
    {
        return match ($questionType) {
            'multiple_choice' => $this->optionText(
                $item,
                (int) (is_array($payload) ? ($payload['selected_option_index'] ?? -1) : ($payload ?? -1)),
            ),
            'matching' => $this->matchingText($item, is_array($payload) ? $payload : []),
            default => is_string($payload) && trim($payload) !== '' ? $payload : null,
        };
    }

    private function formatReferenceSolution(string $questionType, mixed $item): ?string
    {
        return match ($questionType) {
            'multiple_choice' => $this->optionText($item, (int) $item->correct_option_index),
            'matching' => $this->matchingText($item, $item->pairs_payload ?? []),
            'open' => (string) $item->reference_answer,
            'code' => (string) $item->reference_solution,
            default => null,
        };
    }

    private function optionText(mixed $item, int $index): ?string
    {
        foreach ($item->options_payload ?? [] as $option) {
            if ((int) ($option['index'] ?? -1) === $index) {
                return (string) $option['text'];
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $pairs
     */
    private function matchingText(mixed $item, array $pairs): ?string
    {
        $left = collect($item->left_column_payload ?? [])->pluck('text', 'id');
        $right = collect($item->right_column_payload ?? [])->pluck('text', 'id');

        $lines = collect($pairs)
            ->map(fn (array $pair): string => ($left[$pair['left_id'] ?? ''] ?? '?').' → '.($right[$pair['right_id'] ?? ''] ?? '?'))
            ->values();

        return $lines->isEmpty() ? null : $lines->implode("\n");
    }
}
