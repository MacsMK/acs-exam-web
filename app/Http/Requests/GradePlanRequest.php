<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GradePlanRequest.php
 * Ubicación:      app/Http/Requests/GradePlanRequest.php
 * Requerimiento:  RF-CAL-006 a 008, 010 y 011, 015, 016 y 018 a 020, 024 y 028
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Valida los campos de escala, mínimo, ponderaciones y confirmación.
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Support\AcademicNumber;
use Illuminate\Foundation\Http\FormRequest;

class GradePlanRequest extends FormRequest
{
    /**
     * Comprueba el rol y el ámbito de la solicitud.
     *
     * @return bool Resultado de la comprobación.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'teacher' && $this->route('group')->teacher_id === $this->user()->id;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach ($this->only(['components', 'previous', 'exams', 'periods', 'minimum']) as $field => $value) {
            $normalized[$field] = is_array($value)
                ? array_map(AcademicNumber::normalize(...), $value)
                : AcademicNumber::normalize($value);
        }
        $this->merge($normalized);
    }

    /**
     * Define las reglas de formato y contenido de la solicitud.
     *
     * @return array Datos estructurados de la operación.
     */
    public function rules(): array
    {
        $percentage = ['bail', 'nullable', AcademicNumber::DECIMAL, 'decimal:0,2', 'between:0,100'];
        $confirmation = ['nullable', 'string', 'size:64'];
        if ($this->route('period')) {
            return [
                'components' => ['required', 'array:exams,homework,project,other', 'required_array_keys:exams,homework,project,other'],
                'components.*' => ['bail', 'required', AcademicNumber::DECIMAL, 'decimal:0,2', 'between:0,100'],
                'previous' => ['sometimes', 'array'], 'previous.*' => $percentage,
                'exams' => ['sometimes', 'array'], 'exams.*' => $percentage,
                'confirmation' => $confirmation,
            ];
        }

        $maximum = $this->input('scale') === '0-10' ? 10 : 100;

        return [
            'scale' => ['required', 'in:0-10,0-100'],
            'minimum' => ['bail', 'required', AcademicNumber::DECIMAL, 'decimal:0,2', "between:0,{$maximum}"],
            'periods' => ['sometimes', 'array'], 'periods.*' => $percentage, 'confirmation' => $confirmation,
        ];
    }

    public function messages(): array
    {
        return [
            'regex' => 'Captura un número con hasta dos decimales.',
            'minimum.between' => 'El mínimo debe respetar la escala seleccionada',
        ];
    }
}
