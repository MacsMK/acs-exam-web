<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GradebookSaveRequest.php
 * Ubicación:   app/Http/Requests/GradebookSaveRequest.php
 * Descripción: Validación de guardado de sábana de calificaciones por periodo
 *              (RF-CAL-009, RF-CAL-012, RF-CAL-021, MPW-V3 Tabla 18).
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Services\GradePlanService;
use App\Support\AcademicNumber;
use App\Support\GradebookCatalogContext;
use Illuminate\Foundation\Http\FormRequest;

class GradebookSaveRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $grades = $this->input('grades');
        if (! is_array($grades)) {
            return;
        }

        foreach ($grades as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach (GradePlanService::COMPONENTS as $component) {
                $field = $component.'_component';
                if (array_key_exists($field, $row)) {
                    $grades[$index][$field] = AcademicNumber::normalize($row[$field]);
                }
            }
        }

        $this->merge(['grades' => $grades]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'teacher';
    }

    protected function getRedirectUrl(): string
    {
        $catalogContext = GradebookCatalogContext::destinationQuery($this);
        if ($catalogContext === []) {
            return parent::getRedirectUrl();
        }

        $parameters = [$this->route('group'), $this->route('period'), ...$catalogContext];
        $page = filter_var($this->input('gradebook_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($page !== false) {
            $parameters['page'] = $page;
        }

        return route('groups.periods.gradebook', $parameters);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $group = $this->route('group');
        $scale = $group?->grading_scale;
        $number = [
            'bail', 'nullable', $scale === '0-100' ? AcademicNumber::INTEGER : AcademicNumber::DECIMAL,
            'numeric', 'min:0', $scale === '0-10' ? 'max:10' : 'max:100',
        ];

        if ($group !== null && ! $group->allowsManualGradeEntry()) {
            $number = ['missing'];
        }

        return [
            'gradebook_page' => ['required_with:gradebook_complete', 'integer', 'min:1'],
            'gradebook_complete' => ['required_with:gradebook_page', 'in:1'],
            'grades' => ['required', 'array'],
            'grades.*.manual_comments' => ['sometimes', 'array:exams,homework,project,other'],
            'grades.*.manual_comments.*' => ['nullable', 'string', 'max:2000'],
            'grades.*.reason' => ['nullable', 'string', 'max:1000'],
            'grades.*.student_id' => ['required', 'integer', 'distinct', 'exists:acs_users,id'],
            'grades.*.exams_component' => $number,
            'grades.*.homework_component' => $number,
            'grades.*.project_component' => $number,
            'grades.*.other_component' => $number,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gradebook_page.required_with' => 'La captura llegó incompleta. Recarga la página y vuelve a guardar las calificaciones.',
            'gradebook_complete.required_with' => 'La captura llegó incompleta. Recarga la página y vuelve a guardar las calificaciones.',
            'gradebook_complete.in' => 'La captura llegó incompleta. Recarga la página y vuelve a guardar las calificaciones.',
            'grades.*.*.missing' => 'La captura numérica del grupo 0–10 requiere definir primero su unidad',
            'grades.*.*.regex' => $this->route('group')?->grading_scale === '0-100'
                ? 'Captura una calificación entera entre 0 y 100.'
                : 'Captura una calificación con hasta dos decimales.',
            'grades.required' => 'No se enviaron datos de calificaciones.',
            'grades.*.exams_component.min' => 'La calificación de exámenes no puede ser menor a cero.',
            'grades.*.exams_component.max' => 'La calificación de exámenes no puede exceder :max.',
            'grades.*.homework_component.min' => 'La calificación de tareas no puede ser menor a cero.',
            'grades.*.homework_component.max' => 'La calificación de tareas no puede exceder :max.',
            'grades.*.project_component.min' => 'La calificación de proyectos no puede ser menor a cero.',
            'grades.*.project_component.max' => 'La calificación de proyectos no puede exceder :max.',
            'grades.*.other_component.min' => 'La calificación de otros rubros no puede ser menor a cero.',
            'grades.*.other_component.max' => 'La calificación de otros rubros no puede exceder :max.',
        ];
    }
}
