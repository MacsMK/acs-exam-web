<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     QuestionBankService.php
 * Ubicación:   app/Services/QuestionBankService.php
 * Descripción: Servicio transaccional del Banco de Preguntas (Módulo 5,
 *              MPW-V2 §6.4.1): creación atómica de las 4 variantes
 *              polimórficas y registro de paráfrasis anti-copia (RF-BAN-010).
 * ============================================================================
 */

namespace App\Services;

use App\Models\CodeItem;
use App\Models\MatchingItem;
use App\Models\MultipleChoiceItem;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionBankService
{
    /**
     * @return LengthAwarePaginator<Question>
     */
    public function paginateForTeacher(User $teacher, int $perPage = 20): LengthAwarePaginator
    {
        return $teacher->questionsAuthored()
            ->with(['questionable', 'paraphrases'])
            ->withCount('paraphrases')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Crea de forma transaccional un reactivo polimórfico y su entidad raíz.
     *
     * @param array<string, mixed> $data
     */
    public function createQuestion(User $teacher, array $data): Question
    {
        return DB::transaction(function () use ($teacher, $data): Question {
            $questionable = match ($data['question_type']) {
                'multiple_choice' => MultipleChoiceItem::create([
                    'options_payload' => collect($data['options'] ?? [])
                        ->values()
                        ->map(fn (string $text, int $index) => ['index' => $index, 'text' => $text])
                        ->all(),
                    'correct_option_index' => (int) $data['correct_option_index'],
                ]),
                'matching' => $this->buildMatchingItem($data['concepts'] ?? [], $data['definitions'] ?? []),
                'open' => OpenItem::create([
                    'reference_answer' => (string) $data['reference_answer'],
                    'evaluation_rubric' => $data['evaluation_rubric'] ?? null,
                ]),
                'code' => CodeItem::create([
                    'programming_language' => (string) $data['programming_language'],
                    'is_find_error' => (bool) ($data['is_find_error'] ?? false),
                    'starter_code' => $data['starter_code'] ?? null,
                    'reference_solution' => (string) $data['reference_solution'],
                    'instructions' => (string) $data['instructions'],
                ]),
                default => throw ValidationException::withMessages([
                    'question_type' => ['La opción seleccionada en tipo de reactivo no es válida'],
                ]),
            };

            return Question::create([
                'teacher_id' => $teacher->id,
                'subject_tag' => $data['subject_tag'] ?? null,
                'title' => (string) $data['title'],
                'stem' => (string) $data['stem'],
                'question_type' => (string) $data['question_type'],
                'default_points' => $data['default_points'],
                'default_time_seconds' => (int) $data['default_time_seconds'],
                'questionable_type' => $questionable::class,
                'questionable_id' => $questionable->id,
            ]);
        });
    }

    /**
     * Registra una paráfrasis anti-copia para un reactivo existente (RF-BAN-010).
     */
    public function createParaphrase(Question $question, string $stem): QuestionParaphrase
    {
        return DB::transaction(static fn (): QuestionParaphrase => QuestionParaphrase::create([
            'question_id' => $question->id,
            'stem' => trim($stem),
        ]));
    }

    /**
     * Construye un MatchingItem a partir de pares concepto/definición con la
     * columna derecha barajada automáticamente.
     *
     * @param array<int, string> $concepts
     * @param array<int, string> $definitions
     */
    private function buildMatchingItem(array $concepts, array $definitions): MatchingItem
    {
        $leftItems = [];
        $rightItemsOrdered = [];
        $pairs = [];

        foreach (array_values($concepts) as $index => $concept) {
            $leftId = 'L'.($index + 1);
            $rightId = 'R'.($index + 1);

            $leftItems[] = ['id' => $leftId, 'text' => (string) $concept];
            $rightItemsOrdered[] = ['id' => $rightId, 'text' => (string) ($definitions[$index] ?? '')];
            $pairs[] = ['left_id' => $leftId, 'right_id' => $rightId];
        }

        $rightItemsShuffled = collect($rightItemsOrdered)->shuffle()->values()->all();

        return MatchingItem::create([
            'left_column_payload' => $leftItems,
            'right_column_payload' => $rightItemsShuffled,
            'pairs_payload' => $pairs,
        ]);
    }
}
