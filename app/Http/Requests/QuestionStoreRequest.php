<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     QuestionStoreRequest.php
 * Ubicación:   app/Http/Requests/QuestionStoreRequest.php
 * Descripción: Validación tipada de creación de reactivo polimórfico en el
 *              Banco de Preguntas (MPW-V2 §6.3, Tabla 7 y Tabla 17).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'teacher';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'stem' => ['required', 'string'],
            'question_type' => ['required', 'in:multiple_choice,matching,open,code'],
            'subject_tag' => ['nullable', 'string', 'max:100'],
            'default_points' => ['required', 'numeric', 'min:0'],
            'default_time_seconds' => ['required', 'integer', 'min:1'],

            // multiple_choice
            'options' => ['required_if:question_type,multiple_choice', 'array', 'min:2'],
            'options.*' => ['string'],
            'correct_option_index' => ['required_if:question_type,multiple_choice', 'integer', 'min:0'],

            // matching
            'concepts' => ['required_if:question_type,matching', 'array', 'min:2'],
            'concepts.*' => ['string'],
            'definitions' => ['required_if:question_type,matching', 'array', 'min:2'],
            'definitions.*' => ['string'],

            // open
            'reference_answer' => ['required_if:question_type,open', 'string'],
            'evaluation_rubric' => ['nullable', 'string'],

            // code
            'programming_language' => ['required_if:question_type,code', 'string', 'max:40'],
            'is_find_error' => ['sometimes', 'boolean'],
            'starter_code' => ['nullable', 'string'],
            'reference_solution' => ['required_if:question_type,code', 'string'],
            'instructions' => ['required_if:question_type,code', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El campo título es obligatorio',
            'title.max' => 'El título no debe exceder los 150 caracteres',
            'stem.required' => 'El campo enunciado es obligatorio',
            'question_type.required' => 'El campo tipo de reactivo es obligatorio',
            'question_type.in' => 'La opción seleccionada en tipo de reactivo no es válida',
            'subject_tag.max' => 'La materia no debe exceder los 100 caracteres',
            'default_points.required' => 'El campo ponderación es obligatorio',
            'default_points.min' => 'La ponderación debe ser mayor o igual que 0',
            'default_time_seconds.required' => 'El campo tiempo límite es obligatorio',
            'default_time_seconds.min' => 'El tiempo límite debe ser mayor o igual que 1',
            'options.required_if' => 'El campo opciones de respuesta es obligatorio',
            'options.min' => 'Las opciones de respuesta deben contener al menos 2 elementos',
            'correct_option_index.required_if' => 'El campo respuesta correcta es obligatorio',
            'concepts.required_if' => 'El campo conceptos es obligatorio',
            'concepts.min' => 'Los conceptos deben contener al menos 2 elementos',
            'definitions.required_if' => 'El campo definiciones es obligatorio',
            'definitions.min' => 'Las definiciones deben contener al menos 2 elementos',
            'reference_answer.required_if' => 'El campo respuesta de referencia es obligatorio',
            'programming_language.required_if' => 'El campo lenguaje de programación es obligatorio',
            'reference_solution.required_if' => 'El campo solución de referencia es obligatorio',
            'instructions.required_if' => 'El campo instrucciones es obligatorio',
        ];
    }
}
