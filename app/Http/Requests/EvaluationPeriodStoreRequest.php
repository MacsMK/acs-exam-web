<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     EvaluationPeriodStoreRequest.php
 * Ubicación:   app/Http/Requests/EvaluationPeriodStoreRequest.php
 * Descripción: Validación de creación de periodos de evaluación y ponderaciones
 *              (RF-CAL-001, RF-CAL-004, RF-CAL-005, MPW-V3 Tabla 18).
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Support\AcademicNumber;
use App\Support\GradebookCatalogContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class EvaluationPeriodStoreRequest extends FormRequest
{
    private const WEIGHT_FIELDS = ['exams_weight', 'homework_weight', 'project_weight', 'other_weight'];

    public static function earliestStartDate(): Carbon
    {
        return today()->subMonthsNoOverflow(6);
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

        return $catalogContext === []
            ? parent::getRedirectUrl()
            : route('groups.periods.index', [$this->route('group'), ...$catalogContext]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(AcademicNumber::normalize(...), $this->only(self::WEIGHT_FIELDS)));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = self::detailsRules();
        $rules['starts_at'][] = 'after_or_equal:' . self::earliestStartDate()->toDateString();

        return $rules + self::examSelectionRules() + array_fill_keys(self::WEIGHT_FIELDS, [
            'bail', 'required', 'numeric', 'decimal:0,2', AcademicNumber::DECIMAL, 'min:0', 'max:100',
        ]);
    }

    /** @return array<string, list<string>> */
    public static function detailsRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'starts_at' => ['bail', 'required', 'date', 'date_format:Y-m-d'],
            'ends_at' => ['bail', 'required', 'date', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function examSelectionRules(): array
    {
        return [
            'uses_exam_selection' => ['nullable', 'boolean'],
            'required_exam_ids' => ['nullable', 'array', 'list'],
            'required_exam_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     * Cumple RF-CAL-005: la suma de las ponderaciones de los rubros debe ser exactamente 100%.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->hasAny(self::WEIGHT_FIELDS)) {
                return;
            }

            $sum = array_sum(array_map(
                static fn ($weight) => (int) round((float) $weight * 100),
                $this->only(self::WEIGHT_FIELDS),
            ));

            if ($sum !== 10000) {
                $v->errors()->add(
                    'exams_weight',
                    'Las ponderaciones deben sumar exactamente 100 %. La suma actual es ' . ($sum / 100) . ' %.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::detailsMessages() + self::examSelectionMessages() + [
            'exams_weight.required' => 'La ponderación de exámenes es obligatoria.',
            'exams_weight.decimal' => 'La ponderación de exámenes admite hasta dos decimales.',
            'homework_weight.required' => 'La ponderación de tareas es obligatoria.',
            'homework_weight.decimal' => 'La ponderación de tareas admite hasta dos decimales.',
            'project_weight.required' => 'La ponderación de proyectos es obligatoria.',
            'project_weight.decimal' => 'La ponderación de proyectos admite hasta dos decimales.',
            'other_weight.required' => 'La ponderación de otros rubros es obligatoria.',
            'other_weight.decimal' => 'La ponderación de otros rubros admite hasta dos decimales.',
            'regex' => 'Captura un valor de 0 a 100 con hasta dos decimales.',
        ];
    }

    /** @return array<string, string> */
    public static function detailsMessages(): array
    {
        return [
            'name.required' => 'El nombre del periodo de evaluación es obligatorio.',
            'name.max' => 'El nombre del periodo no debe exceder los 100 caracteres.',
            'starts_at.required' => 'El campo fecha de inicio es obligatorio',
            'starts_at.date' => 'La fecha de inicio no tiene un formato válido',
            'starts_at.date_format' => 'La fecha de inicio no tiene un formato válido',
            'starts_at.after_or_equal' => 'La fecha de inicio debe ser igual o posterior al '
                . self::earliestStartDate()->format('d/m/Y'),
            'ends_at.required' => 'El campo fecha de cierre es obligatorio',
            'ends_at.date' => 'La fecha de cierre no tiene un formato válido',
            'ends_at.date_format' => 'La fecha de cierre no tiene un formato válido',
            'ends_at.after_or_equal' => 'La fecha de cierre debe ser igual o posterior a la fecha de inicio',
        ];
    }

    /** @return array<string, string> */
    public static function examSelectionMessages(): array
    {
        return [
            'uses_exam_selection.boolean' => 'La opción de selección de exámenes no es válida',
            'required_exam_ids.array' => 'La selección de exámenes no tiene un formato válido',
            'required_exam_ids.list' => 'La selección de exámenes no tiene un formato válido',
            'required_exam_ids.*.integer' => 'La aplicación de examen seleccionada no es válida',
            'required_exam_ids.*.distinct' => 'Selecciona cada aplicación de examen una sola vez',
            'required_exam_ids.*.min' => 'La aplicación de examen seleccionada no es válida',
        ];
    }
}
