<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptAnswerWebRequest.php
 * Ubicación:   app/Http/Requests/AttemptAnswerWebRequest.php
 * Descripción: Validación de envío de respuesta en aplicación de examen web
 *              con integridad biyectiva en relación de columnas [CRÍTICA-03]
 *              (attempts.answer, MPW-V2 §6.3, Tabla 17).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

use App\Models\ExamQuestion;
use App\Models\StudentAttempt;

class AttemptAnswerWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $attempt = $this->route('attempt');
        if ($attempt instanceof StudentAttempt) {
            return $attempt->student_id === $user->id;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'exam_question_id' => ['required', 'integer', 'exists:acs_exam_question,id'],
            'selected_option_index' => ['nullable', 'integer', 'min:0'],
            'pairs' => ['nullable', 'array', 'max:8'],
            'pairs.*.left_id' => ['required_with:pairs', 'string', 'distinct'],
            'pairs.*.right_id' => ['nullable', 'string'],
            'answer_text' => ['nullable', 'string'],
            'given_answer' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pairs = $this->input('pairs');
            if (! is_array($pairs) || $pairs === []) {
                return;
            }

            $selectedRightIds = [];
            foreach ($pairs as $pair) {
                if (! is_array($pair)) {
                    continue;
                }

                $rightId = trim((string) ($pair['right_id'] ?? ''));
                if ($rightId === '') {
                    continue;
                }

                if (isset($selectedRightIds[$rightId])) {
                    $validator->errors()->add(
                        'pairs',
                        'No puedes repetir la misma definición de la columna derecha en múltiples conceptos.'
                    );

                    return;
                }

                $selectedRightIds[$rightId] = true;
            }

            $examQuestionId = $this->input('exam_question_id');
            if (is_numeric($examQuestionId)) {
                $examQuestion = ExamQuestion::with('question.questionable')->find((int) $examQuestionId);
                $question = $examQuestion?->question;
                if ($question !== null && $question->question_type === 'matching' && $question->questionable !== null) {
                    $rightColumn = $question->questionable->right_column_payload ?? [];
                    if (is_array($rightColumn) && $rightColumn !== [] && count($pairs) > count($rightColumn)) {
                        $validator->errors()->add(
                            'pairs',
                            'La cantidad de pares enviados excede la columna derecha del reactivo.'
                        );
                    }
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
            'exam_question_id.required' => 'El campo reactivo del examen es obligatorio',
            'exam_question_id.exists' => 'La opción seleccionada en reactivo del examen no es válida',
            'pairs.array' => 'Los pares de relación de columnas deben enviarse como un arreglo',
            'pairs.max' => 'La relación de columnas admite máximo 8 pares',
            'pairs.*.left_id.required_with' => 'Cada par debe especificar el identificador de la columna izquierda',
            'pairs.*.left_id.string' => 'El identificador izquierdo debe ser texto válido',
            'pairs.*.left_id.distinct' => 'No se permite repetir conceptos de la columna izquierda en una respuesta',
            'pairs.*.right_id.string' => 'El identificador derecho debe ser texto válido',
        ];
    }
}
