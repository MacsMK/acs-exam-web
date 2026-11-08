<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GradebookSaveRequest.php
 * Ubicación:   app/Http/Requests/GradebookSaveRequest.php
 * Descripción: Validación de guardado de sábana de calificaciones por periodo
 *              (RF-CAL-009, RF-CAL-012, RF-CAL-021, MPW-V2 Tabla 17).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GradebookSaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'teacher';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array'],
            'grades.*.student_id' => ['required', 'integer', 'exists:acs_users,id'],
            'grades.*.exams_component' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grades.*.homework_component' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grades.*.project_component' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grades.*.other_component' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grades.required' => 'No se enviaron datos de calificaciones.',
            'grades.*.exams_component.min' => 'La calificación de exámenes no puede ser menor a cero.',
            'grades.*.exams_component.max' => 'La calificación de exámenes no puede exceder 100.',
            'grades.*.homework_component.min' => 'La calificación de tareas no puede ser menor a cero.',
            'grades.*.homework_component.max' => 'La calificación de tareas no puede exceder 100.',
            'grades.*.project_component.min' => 'La calificación de proyectos no puede ser menor a cero.',
            'grades.*.project_component.max' => 'La calificación de proyectos no puede exceder 100.',
            'grades.*.other_component.min' => 'La calificación de otros rubros no puede ser menor a cero.',
            'grades.*.other_component.max' => 'La calificación de otros rubros no puede exceder 100.',
        ];
    }
}
