<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptAnswerApiRequest.php
 * Ubicación:   app/Http/Requests/AttemptAnswerApiRequest.php
 * Descripción: Validación de respuesta por reactivo desde la API móvil v1
 *              (api.v1.exams.answers, MPM-V4 Tabla 12, MPW-V2 §6.3).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'pairs' => ['sometimes', 'nullable', 'array'],
            'text' => ['sometimes', 'nullable', 'string'],
            'code' => ['sometimes', 'nullable', 'string'],
            'given_answer' => ['nullable'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0'],
        ];
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
            'time_spent_seconds.min' => 'El tiempo empleado debe ser mayor o igual que 0',
        ];
    }
}
