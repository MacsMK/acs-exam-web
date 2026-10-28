<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos base de un examen (RW-19, RW-20, RW-21, RW-25, RW-26, RW-27).
 */
class ExamStoreRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'grading_scale' => ['required', 'in:0-10,0-100'],
            'randomize_question_order' => ['sometimes', 'boolean'],
            'result_visibility_mode' => ['required', 'in:final_score,estimated_score,objective_only_pending,hidden'],
            'require_webcam' => ['sometimes', 'boolean'],
            'max_allowed_incidents' => ['required', 'integer', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [
                'integer',
                Rule::exists('acs_groups', 'id')->where('teacher_id', $this->user()?->id),
            ],
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
            'grading_scale.required' => 'El campo escala de calificación es obligatorio',
            'grading_scale.in' => 'La opción seleccionada en escala de calificación no es válida',
            'result_visibility_mode.required' => 'El campo visibilidad del resultado es obligatorio',
            'result_visibility_mode.in' => 'La opción seleccionada en visibilidad del resultado no es válida',
            'max_allowed_incidents.required' => 'El campo incidentes máximos permitidos es obligatorio',
            'max_allowed_incidents.min' => 'El número de incidentes máximos permitidos debe ser mayor o igual que 0',
            'starts_at.required' => 'El campo fecha de inicio es obligatorio',
            'ends_at.required' => 'El campo fecha de cierre es obligatorio',
            'ends_at.after' => 'La fecha de cierre debe ser posterior a la fecha de inicio',
            'group_ids.*.exists' => 'La opción seleccionada en grupo no es válida o no pertenece al docente',
        ];
    }
}
