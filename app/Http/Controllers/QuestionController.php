<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     QuestionController.php
 * Ubicación:   app/Http/Controllers/QuestionController.php
 * Descripción: Banco de preguntas (Módulo 5): las 4 variantes polimórficas
 *              + generación de paráfrasis anti-copia (Listado de
 *              requerimientos).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\CodeItem;
use App\Models\MatchingItem;
use App\Models\MultipleChoiceItem;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\QuestionParaphrase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $questions = $request->user()->questionsAuthored()
            ->with('questionable')
            ->latest()
            ->paginate(20);

        return view('questions.index', ['questions' => $questions]);
    }

    public function create(): View
    {
        return view('questions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'stem' => ['required', 'string'],
            'question_type' => ['required', 'in:multiple_choice,matching,open,code'],
            'subject_tag' => ['nullable', 'string', 'max:100'],
            'default_points' => ['required', 'numeric', 'min:0'],
            'default_time_seconds' => ['required', 'integer', 'min:1'],

            // multiple_choice
            'options' => ['required_if:question_type,multiple_choice', 'array', 'min:2'],
            'options.*' => ['string'],
            'correct_option_index' => ['required_if:question_type,multiple_choice', 'integer'],

            // matching
            'left_items' => ['required_if:question_type,matching', 'array', 'min:2'],
            'right_items' => ['required_if:question_type,matching', 'array', 'min:2'],
            'pairs' => ['required_if:question_type,matching', 'array', 'min:1'],

            // open
            'reference_answer' => ['required_if:question_type,open', 'string'],
            'evaluation_rubric' => ['nullable', 'string'],

            // code
            'programming_language' => ['required_if:question_type,code', 'string', 'max:40'],
            'is_find_error' => ['sometimes', 'boolean'],
            'starter_code' => ['nullable', 'string'],
            'reference_solution' => ['required_if:question_type,code', 'string'],
            'instructions' => ['required_if:question_type,code', 'string'],
        ]);

        $questionable = match ($data['question_type']) {
            'multiple_choice' => MultipleChoiceItem::create([
                'options_payload' => collect($data['options'])
                    ->values()
                    ->map(fn (string $text, int $index) => ['index' => $index, 'text' => $text])
                    ->all(),
                'correct_option_index' => $data['correct_option_index'],
            ]),
            'matching' => MatchingItem::create([
                'left_column_payload' => $data['left_items'],
                'right_column_payload' => $data['right_items'],
                'pairs_payload' => $data['pairs'],
            ]),
            'open' => OpenItem::create([
                'reference_answer' => $data['reference_answer'],
                'evaluation_rubric' => $data['evaluation_rubric'] ?? null,
            ]),
            'code' => CodeItem::create([
                'programming_language' => $data['programming_language'],
                'is_find_error' => (bool) ($data['is_find_error'] ?? false),
                'starter_code' => $data['starter_code'] ?? null,
                'reference_solution' => $data['reference_solution'],
                'instructions' => $data['instructions'],
            ]),
            default => throw ValidationException::withMessages(['question_type' => ['Tipo inválido.']]),
        };

        $question = Question::create([
            'teacher_id' => $request->user()->id,
            'subject_tag' => $data['subject_tag'] ?? null,
            'title' => $data['title'],
            'stem' => $data['stem'],
            'question_type' => $data['question_type'],
            'default_points' => $data['default_points'],
            'default_time_seconds' => $data['default_time_seconds'],
            'questionable_type' => $questionable::class,
            'questionable_id' => $questionable->id,
        ]);

        return redirect()->route('questions.index')->with('status', 'Reactivo creado.');
    }

    /**
     * Genera (o guarda) una paráfrasis del reactivo: redacción distinta,
     * misma respuesta (Listado de requerimientos, anti-copia).
     */
    public function storeParaphrase(Request $request, Question $question): RedirectResponse
    {
        abort_unless($question->teacher_id === $request->user()->id, 403);

        $data = $request->validate([
            'stem' => ['required', 'string'],
        ]);

        QuestionParaphrase::create([
            'question_id' => $question->id,
            'stem' => $data['stem'],
        ]);

        return back()->with('status', 'Paráfrasis guardada.');
    }
}
