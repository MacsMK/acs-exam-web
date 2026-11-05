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
            'first_name' => ['required', 'string', 'max:100', 'regex:/\A(?=.*\p{L})[\p{L}\p{M} ]+\z/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/\A(?=.*\p{L})[\p{L}\p{M} ]+\z/u'],
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
            'first_name.required' => 'El campo nombre es obligatorio',
            'first_name.string' => 'El nombre no tiene un formato válido',
            'first_name.max' => 'El nombre no debe exceder los 100 caracteres',
            'first_name.regex' => 'El nombre no tiene un formato válido',

            'last_name.required' => 'El campo apellidos es obligatorio',
            'last_name.string' => 'El campo apellidos no tiene un formato válido',
            'last_name.max' => 'El campo apellidos no debe exceder los 100 caracteres',
            'last_name.regex' => 'El campo apellidos no tiene un formato válido',

            'email.required' => 'El campo correo electrónico es obligatorio',
            'email.email' => 'El correo electrónico no tiene un formato válido',
            'email.max' => 'El correo electrónico no debe exceder los 255 caracteres',
            'email.unique' => 'Este correo electrónico ya está registrado',

            'phone.regex' => 'El teléfono debe contener 10 dígitos',

            'password.required' => 'El campo contraseña es obligatorio',
            'password.string' => 'El campo contraseña no tiene un formato válido',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Los campos contraseña y confirmar contraseña deben coincidir',

            'accepts_privacy_notice.accepted' => 'Debes aceptar el aviso de privacidad para continuar',
        ];
    }
}
