<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        RejectTeacherVerificationRequest.php
 * Ubicación:      app/Http/Requests/RejectTeacherVerificationRequest.php
 * Requerimiento:  RW-05, ADR-057 (Dictamen Administrativo de Rechazo Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Valida el motivo obligatorio y descriptivo para rechazar una
 *                 solicitud de acreditación de identidad docente desde el panel
 *                 del Superadministrador.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para el dictamen administrativo de rechazo de verificación docente.
 *
 * @package App\Http\Requests
 */
class RejectTeacherVerificationRequest extends FormRequest
{
    /**
     * Autoriza exclusivamente al rol super_admin.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'super_admin';
    }

    /**
     * Reglas de validación del motivo de rechazo.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * Mensajes de validación normalizados conforme a MPW-V3 §8.11 / Tabla 18.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'El campo motivo de rechazo es obligatorio',
            'rejection_reason.string' => 'El motivo de rechazo no tiene un formato válido',
            'rejection_reason.min' => 'El motivo de rechazo debe tener al menos 10 caracteres',
            'rejection_reason.max' => 'El motivo de rechazo no debe exceder los 500 caracteres',
        ];
    }

    /**
     * Recorta espacios extremos del motivo de rechazo.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('rejection_reason') && is_string($this->input('rejection_reason'))) {
            $this->merge([
                'rejection_reason' => trim($this->input('rejection_reason')),
            ]);
        }
    }
}
