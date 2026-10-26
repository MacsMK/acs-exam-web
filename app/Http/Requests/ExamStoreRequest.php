<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'group_ids.*' => ['integer', 'exists:acs_groups,id'],
        ];
    }
}
