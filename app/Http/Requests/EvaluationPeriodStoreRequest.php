<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     EvaluationPeriodStoreRequest.php
 * Ubicación:   app/Http/Requests/EvaluationPeriodStoreRequest.php
 * Descripción: Validación de creación de periodos de evaluación y ponderaciones
 *              (RF-CAL-001, RF-CAL-004, RF-CAL-005, MPW-V2 Tabla 17).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EvaluationPeriodStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'exams_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'homework_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'project_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'other_weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Configure the validator instance.
     * Cumple RF-CAL-005: la suma de las ponderaciones de los rubros debe ser exactamente 100%.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $sum = (float) $this->input('exams_weight', 0)
                + (float) $this->input('homework_weight', 0)
                + (float) $this->input('project_weight', 0)
                + (float) $this->input('other_weight', 0);

            if (abs($sum - 100.0) > 0.01) {
                $v->errors()->add(
                    'exams_weight',
                    'La suma de las ponderaciones de los rubros debe ser exactamente el 100 % (Suma actual: ' . $sum . '%).'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del periodo de evaluación es obligatorio.',
            'name.max' => 'El nombre del periodo no debe exceder los 100 caracteres.',
            'starts_at.required' => 'La fecha de inicio es obligatoria.',
            'ends_at.required' => 'La fecha de fin es obligatoria.',
            'ends_at.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'exams_weight.required' => 'La ponderación de exámenes es obligatoria.',
            'homework_weight.required' => 'La ponderación de tareas es obligatoria.',
            'project_weight.required' => 'La ponderación de proyectos es obligatoria.',
            'other_weight.required' => 'La ponderación de otros rubros es obligatoria.',
        ];
    }
}
