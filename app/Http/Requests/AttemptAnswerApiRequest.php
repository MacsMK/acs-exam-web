<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptAnswerApiRequest.php
 * Ubicación:   app/Http/Requests/AttemptAnswerApiRequest.php
 * Descripción: Validación de respuesta por reactivo desde la API móvil v1
 *              con blindaje contra producto cartesiano en matching [CRÍTICA-03]
 *              (api.v1.exams.answers, MPM-V4 Tabla 12, MPW-V2 §6.3).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

use App\Models\Exam;
use App\Models\ExamQuestion;

class AttemptAnswerApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question_id' => ['required_without:exam_question_id'],
            'exam_question_id' => ['required_without:question_id', 'nullable', 'integer', 'exists:acs_exam_question,id'],
            'selected_option_ids' => ['sometimes', 'nullable', 'array'],
            'selected_option_ids.*' => ['string'],
            'pairs' => ['sometimes', 'nullable', 'array', 'max:8'],
            'pairs.*' => ['nullable', 'string'],
            'text' => ['sometimes', 'nullable', 'string'],
            'code' => ['sometimes', 'nullable', 'string'],
            'given_answer' => ['nullable'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pairs = $this->input('pairs');
            if (! is_array($pairs) || $pairs === []) {
                return;
            }

            $seenRightIds = [];
            foreach ($pairs as $rightId) {
                if (! is_string($rightId)) {
                    continue;
                }

                $trimmed = trim($rightId);
                if ($trimmed === '') {
                    continue;
                }

                if (isset($seenRightIds[$trimmed])) {
                    $validator->errors()->add(
                        'pairs',
                        'No se permite repetir identificadores de la columna derecha en una relación de columnas.'
                    );

                    return;
                }

                $seenRightIds[$trimmed] = true;
            }

            $examQuestion = $this->resolveTargetExamQuestion();
            $question = $examQuestion?->question;
            if ($question !== null && $question->question_type === 'matching' && $question->questionable !== null) {
                $rightColumn = $question->questionable->right_column_payload ?? [];
                if (is_array($rightColumn) && $rightColumn !== [] && count($pairs) > count($rightColumn)) {
                    $validator->errors()->add(
                        'pairs',
                        'La cantidad de pares enviados supera el tamaño de la columna derecha.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question_id.required_without' => 'El campo identificador de pregunta es obligatorio',
            'exam_question_id.required_without' => 'El campo identificador de reactivo es obligatorio',
            'exam_question_id.exists' => 'La opción seleccionada en reactivo no es válida',
            'pairs.array' => 'Los pares de relación de columnas deben enviarse como un arreglo asociativo',
            'pairs.max' => 'La relación de columnas admite máximo 8 pares',
            'pairs.*.string' => 'Cada valor seleccionado en la relación de columnas debe ser texto válido',
            'time_spent_seconds.min' => 'El tiempo empleado debe ser mayor o igual que 0',
        ];
    }

    private function resolveTargetExamQuestion(): ?ExamQuestion
    {
        $exam = $this->route('exam');
        $examQuestionId = $this->input('exam_question_id');
        $questionId = $this->input('question_id');

        if (is_numeric($examQuestionId)) {
            $query = ExamQuestion::with('question.questionable');
            if ($exam instanceof Exam) {
                $query->where('exam_id', $exam->id);
            }

            return $query->find((int) $examQuestionId);
        }

        if ($exam instanceof Exam && is_numeric($questionId)) {
            return ExamQuestion::with('question.questionable')
                ->where('exam_id', $exam->id)
                ->where('question_id', (int) $questionId)
                ->first();
        }

        return null;
    }
}
