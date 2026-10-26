<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     VerifyTwoFactorRequest.php
 * Ubicación:   app/Http/Requests/VerifyTwoFactorRequest.php
 * Descripción: Paso 2 del login: código de un solo uso de 6 dígitos.
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
            'user_id' => ['required', 'integer', 'exists:acs_users,id'],
            'code' => ['required', 'digits:6'],
        ];
    }
}
