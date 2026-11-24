<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamSectionUpdateRequest.php
 * Ubicación:      app/Http/Requests/ExamSectionUpdateRequest.php
 * Requerimiento:  RW-12, RW-13 (Edición de Apartado de Examen, MPW-V3 Tabla 18)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    FormRequest de actualización de título y materia de un
 *                 apartado existente dentro de un examen docente.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Http\Requests\Concerns\NormalizesQuestionInputs;
use App\Models\Exam;

/**
 * Validación formal de edición de apartado de examen.
 */
class ExamSectionUpdateRequest extends FormRequest
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
            return (int) $exam->teacher_id === (int) $user->id;
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
            'subject_tag' => ['nullable', 'string', 'max:100'],
            'subject_id' => [
                'nullable',
                'integer',
                Rule::exists('acs_subjects', 'id')->where('teacher_id', $this->user()?->id),
            ],
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
            'subject_tag.string' => 'La materia del apartado debe ser una cadena de texto válida',
            'subject_tag.max' => 'La materia del apartado no debe exceder los 100 caracteres',
            'subject_id.integer' => 'El identificador de la materia debe ser un número entero',
            'subject_id.exists' => 'La materia seleccionada no existe o no pertenece a tu catálogo docente',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

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
