<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamSectionStoreRequest.php
 * Ubicación:   app/Http/Requests/ExamSectionStoreRequest.php
 * Descripción: Validación de armado de apartado de examen y sorteo del banco
 *              (RW-12, RW-13, MPW-V3 §6.3, Tabla 18).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Http\Requests\Concerns\NormalizesQuestionInputs;
use App\Models\Exam;

class ExamSectionStoreRequest extends FormRequest
{
    use NormalizesQuestionInputs;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || $user->role !== 'teacher') {
            return false;
        }

        $exam = $this->route('exam');
        if ($exam instanceof Exam) {
            return $exam->teacher_id === $user->id;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'question_type' => ['required', 'string', 'in:multiple_choice,matching,open,code'],
            'subject_tag' => ['nullable', 'string', 'max:100'],
            'subject_id' => [
                'nullable',
                'integer',
                Rule::exists('acs_subjects', 'id')->where('teacher_id', $this->user()?->id),
            ],
            'requested_quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El campo título del apartado es obligatorio',
            'title.string' => 'El título del apartado debe ser una cadena de texto válida',
            'title.max' => 'El título del apartado no debe exceder los 150 caracteres',
            'question_type.required' => 'El campo tipo de reactivo es obligatorio',
            'question_type.in' => 'La opción seleccionada en tipo de reactivo no es válida',
            'subject_tag.string' => 'La materia del apartado debe ser una cadena de texto válida',
            'subject_tag.max' => 'La materia del apartado no debe exceder los 100 caracteres',
            'subject_id.integer' => 'El identificador de la materia debe ser un número entero',
            'subject_id.exists' => 'La materia seleccionada no existe o no pertenece a tu catálogo docente',
            'requested_quantity.required' => 'El campo cantidad a sortear es obligatorio',
            'requested_quantity.integer' => 'La cantidad a sortear debe ser un número entero',
            'requested_quantity.min' => 'La cantidad a sortear debe ser mayor o igual que 1',
            'requested_quantity.max' => 'La cantidad a sortear debe ser menor o igual que 100',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('questions_count') && ! $this->has('requested_quantity')) {
            $payload['requested_quantity'] = $this->input('questions_count');
        }

        if ($this->has('title')) {
            $payload['title'] = $this->cleanSingleLineText($this->input('title'), false);
        }

        if ($this->has('subject_tag')) {
            $payload['subject_tag'] = $this->cleanSingleLineText($this->input('subject_tag'), true);
        }

        if ($this->has('subject_id') && $this->input('subject_id') === '') {
            $payload['subject_id'] = null;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
