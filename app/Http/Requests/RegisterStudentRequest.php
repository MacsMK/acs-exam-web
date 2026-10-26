<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     RegisterStudentRequest.php
 * Ubicación:   app/Http/Requests/RegisterStudentRequest.php
 * Descripción: Registro de alumno. Teléfono opcional (acuerdo de equipo
 *              26/10/2026); correo obligatorio para los 3 roles.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:acs_users,email'],
            'phone' => ['nullable', 'regex:/^[0-9]{10}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accepts_privacy_notice' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'El teléfono debe tener exactamente 10 dígitos.',
            'accepts_privacy_notice.accepted' => 'Debes aceptar el aviso de privacidad para continuar.',
        ];
    }
}
