<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptAnswerWebRequest.php
 * Ubicación:   app/Http/Requests/AttemptAnswerWebRequest.php
 * Descripción: Validación de envío de respuesta en aplicación de examen web
 *              (attempts.answer, MPW-V2 §6.3, Tabla 17).
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Models\StudentAttempt;
use Illuminate\Foundation\Http\FormRequest;

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
            'pairs' => ['nullable', 'array'],
            'answer_text' => ['nullable', 'string'],
            'given_answer' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exam_question_id.required' => 'El campo reactivo del examen es obligatorio',
            'exam_question_id.exists' => 'La opción seleccionada en reactivo del examen no es válida',
        ];
    }
}
