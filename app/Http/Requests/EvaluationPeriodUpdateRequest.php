<?php

declare(strict_types=1);

/**
 * Proyecto: acs-exam-web | Organización: A-Code Solutions
 * Actualización del nombre y las fechas de un periodo de evaluación (RF-CAL-001).
 */

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;

class EvaluationPeriodUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');
        $teacher = $this->user();

        return $group instanceof Group && $teacher?->role === 'teacher'
            && $group->teacher_id === $teacher->id && ! $group->is_closed;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return EvaluationPeriodStoreRequest::detailsRules() + EvaluationPeriodStoreRequest::examSelectionRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return EvaluationPeriodStoreRequest::detailsMessages() + EvaluationPeriodStoreRequest::examSelectionMessages();
    }
}
