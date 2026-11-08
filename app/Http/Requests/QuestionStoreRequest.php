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
            'options' => ['exclude_unless:question_type,multiple_choice', 'required', 'array', 'min:2', 'max:6'],
            'options.*' => ['exclude_unless:question_type,multiple_choice', 'required', 'string'],
            'correct_option_index' => [
                'exclude_unless:question_type,multiple_choice',
                'required',
                'integer',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $options = $this->input('options', []);
                    if (is_array($options) && !empty($options) && (int) $value >= count($options)) {
                        $fail('El índice de la respuesta correcta debe corresponder a una de las opciones registradas.');
                    }
                },
            ],

            // matching
            'concepts' => ['exclude_unless:question_type,matching', 'required', 'array', 'min:2', 'max:8'],
            'concepts.*' => ['exclude_unless:question_type,matching', 'required', 'string'],
            'definitions' => ['exclude_unless:question_type,matching', 'required', 'array', 'min:2', 'max:8'],
            'definitions.*' => ['exclude_unless:question_type,matching', 'required', 'string'],

            // open
            'reference_answer' => ['exclude_unless:question_type,open', 'required', 'string'],
            'evaluation_rubric' => ['exclude_unless:question_type,open', 'nullable', 'string'],

            // code
            'programming_language' => ['exclude_unless:question_type,code', 'required', 'string', 'max:40'],
            'is_find_error' => ['exclude_unless:question_type,code', 'sometimes', 'boolean'],
            'starter_code' => ['exclude_unless:question_type,code', 'required_if:is_find_error,1', 'required_if:is_find_error,true', 'nullable', 'string'],
            'reference_solution' => ['exclude_unless:question_type,code', 'required', 'string'],
            'instructions' => ['exclude_unless:question_type,code', 'required', 'string'],
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
            'options.max' => 'Las opciones de respuesta no deben exceder 6 alternativas',
            'options.*.required' => 'Cada alternativa de respuesta debe contener texto',
            'correct_option_index.required_if' => 'El campo respuesta correcta es obligatorio',
            'concepts.required_if' => 'El campo conceptos es obligatorio',
            'concepts.min' => 'Los conceptos deben contener al menos 2 elementos',
            'concepts.max' => 'Los conceptos no deben exceder 8 pares',
            'concepts.*.required' => 'Cada concepto debe contener texto',
            'definitions.required_if' => 'El campo definiciones es obligatorio',
            'definitions.min' => 'Las definiciones deben contener al menos 2 elementos',
            'definitions.max' => 'Las definiciones no deben exceder 8 pares',
            'definitions.*.required' => 'Cada definición debe contener texto',
            'reference_answer.required_if' => 'El campo respuesta de referencia es obligatorio',
            'programming_language.required_if' => 'El campo lenguaje de programación es obligatorio',
            'starter_code.required_if' => 'En la modalidad de encontrar el error, el código inicial con defecto es obligatorio',
            'reference_solution.required_if' => 'El campo solución de referencia es obligatorio',
            'instructions.required_if' => 'El campo instrucciones es obligatorio',
        ];
    }
}
