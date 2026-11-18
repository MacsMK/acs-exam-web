<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AssignTeacherPackageRequest.php
 * Ubicación:      app/Http/Requests/AssignTeacherPackageRequest.php
 * Requerimiento:  RW-05, RF-SUS-001..015 (Asignación de Paquete SaaS Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Valida la asignación manual de paquete SaaS y estado de pago
 *                 para una cuenta docente desde el portal Superadministrador
 *                 con mensajes normativos en español (MPW-V2 §6.3, Tabla 17).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para la asignación administrativa de suscripción SaaS a docentes.
 *
 * @package App\Http\Requests
 */
class AssignTeacherPackageRequest extends FormRequest
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
     * Reglas de validación del paquete, estatus de pago y notas administrativas.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_package_id' => ['required', 'integer', 'exists:acs_subscription_packages,id'],
            'payment_status' => ['required', 'string', 'in:paid,pending,overdue,suspended'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Mensajes de validación normalizados conforme a MPW-V2 §8.11 / Tabla 17.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subscription_package_id.required' => 'El campo paquete de suscripción es obligatorio',
            'subscription_package_id.integer' => 'El paquete de suscripción no tiene un formato válido',
            'subscription_package_id.exists' => 'La opción seleccionada en paquete de suscripción no es válida',

            'payment_status.required' => 'El campo estado de pago es obligatorio',
            'payment_status.string' => 'El estado de pago no tiene un formato válido',
            'payment_status.in' => 'La opción seleccionada en estado de pago no es válida',

            'notes.string' => 'El campo notas no tiene un formato válido',
            'notes.max' => 'El campo notas no debe exceder los 255 caracteres',
        ];
    }

    /**
     * Normaliza notas opcionales recortando espacios extremos.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && is_string($this->input('notes'))) {
            $notes = trim($this->input('notes'));
            $this->merge([
                'notes' => $notes !== '' ? $notes : null,
            ]);
        }
    }
}
