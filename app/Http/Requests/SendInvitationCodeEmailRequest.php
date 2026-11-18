<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        SendInvitationCodeEmailRequest.php
 * Ubicación:      app/Http/Requests/SendInvitationCodeEmailRequest.php
 * Requerimiento:  RW-05, ADR-057 (Envío de Código de Invitación Institucional)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Valida el envío por correo electrónico de un código de
 *                 invitación institucional y su enlace directo de registro.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para remitir un código de invitación institucional por correo.
 *
 * @package App\Http\Requests
 */
class SendInvitationCodeEmailRequest extends FormRequest
{
    /**
     * Autoriza exclusivamente al Superadministrador autenticado.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'super_admin';
    }

    /**
     * Reglas de validación del correo destinatario de la invitación.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
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
            'email.required' => 'El campo correo electrónico es obligatorio',
            'email.email' => 'El correo electrónico no tiene un formato válido',
            'email.max' => 'El correo electrónico no debe exceder los 255 caracteres',
        ];
    }

    /**
     * Normaliza el correo destinatario eliminando espacios en los extremos.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => mb_strtolower(trim($this->input('email'))),
            ]);
        }
    }
}
