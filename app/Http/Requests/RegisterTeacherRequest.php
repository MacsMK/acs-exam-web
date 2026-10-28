<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     RegisterTeacherRequest.php
 * Ubicación:   app/Http/Requests/RegisterTeacherRequest.php
 * Descripción: Registro de docente. Teléfono obligatorio a 10 dígitos
 *              (acuerdo de equipo 26/10/2026, coincide con el validador
 *              ya usado del lado web por Balam).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTeacherRequest extends FormRequest
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
            'phone' => ['required', 'regex:/^[0-9]{10}$/'],
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
            'first_name.required' => 'El campo nombre es obligatorio',
            'first_name.max' => 'El nombre no debe exceder los 100 caracteres',
            'last_name.required' => 'El campo apellidos es obligatorio',
            'last_name.max' => 'Los apellidos no deben exceder los 100 caracteres',
            'email.required' => 'El campo correo electrónico es obligatorio',
            'email.email' => 'El correo electrónico no tiene un formato válido',
            'email.unique' => 'El correo electrónico ya se encuentra registrado',
            'phone.required' => 'El campo teléfono es obligatorio',
            'phone.regex' => 'El teléfono debe tener exactamente 10 dígitos.',
            'password.required' => 'El campo contraseña es obligatorio',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Los campos contraseña y confirmación de contraseña deben coincidir',
            'accepts_privacy_notice.accepted' => 'Debes aceptar el aviso de privacidad para continuar.',
        ];
    }
}
