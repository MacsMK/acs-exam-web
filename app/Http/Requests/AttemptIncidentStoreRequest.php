<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptIncidentStoreRequest.php
 * Ubicación:   app/Http/Requests/AttemptIncidentStoreRequest.php
 * Descripción: Validación de reporte de incidentes anti-fraude durante la
 *              aplicación de un examen (api.v1.exams.incidents, MPM-V4 §7.7).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttemptIncidentStoreRequest extends FormRequest
{
    private const ALLOWED_INCIDENT_TYPES = [
        'app_left',
        'multi_window',
        'focus_lost',
        'tab_switch',
        'app_background',
        'split_screen',
        'camera_lost',
        'camera_covered',
        'disconnect',
        'screenshot_attempt',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'incident_type' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_INCIDENT_TYPES)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'incident_type.required' => 'El campo tipo de incidente es obligatorio',
            'incident_type.in' => 'La opción seleccionada en tipo de incidente no es válida',
            'description.max' => 'La descripción no debe exceder los 500 caracteres',
        ];
    }
}
