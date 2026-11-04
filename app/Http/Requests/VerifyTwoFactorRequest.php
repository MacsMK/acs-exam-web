<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     VerifyTwoFactorRequest.php
 * Ubicación:   app/Http/Requests/VerifyTwoFactorRequest.php
 * Descripción: Paso 2 del login: código de un solo uso de 6 dígitos.
 *              `user_id` es opcional (no "required"): el panel web lo
 *              manda (campo oculto del formulario, sesión sin estado
 *              entre paso 1 y 2) y lo usa `Auth\LoginController`, pero la
 *              API v1 móvil (`Api\V1\AuthController::twoFactor`) no lo
 *              recibe nunca — `AuthRepository.verifyTwoFactor` del
 *              cliente Flutter solo manda `{"code": "..."}` (MPM-V4 Tabla
 *              12) — así que localiza al usuario por el código vigente.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTwoFactorRequest extends FormRequest
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
            'user_id' => ['sometimes', 'integer', 'exists:acs_users,id'],
            'code' => ['required', 'digits:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'El campo código de verificación es obligatorio',
            'code.digits' => 'El código de verificación debe contener 6 dígitos',
        ];
    }
}
