<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ResendTwoFactorRequest.php
 * Ubicación:   app/Http/Requests/ResendTwoFactorRequest.php
 * Descripción: Solicitud de un nuevo código del segundo paso (RF-AUT-006,
 *              Reenvío de código de autenticación). Solo recibe el correo con
 *              el que se inició la verificación: la API móvil localiza el
 *              código vigente, no la contraseña, así que no se vuelve a pedir.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResendTwoFactorRequest extends FormRequest
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
            'email' => ['required', 'email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'El campo correo electrónico es obligatorio',
            'email.email' => 'El correo electrónico no tiene un formato válido',
        ];
    }
}
