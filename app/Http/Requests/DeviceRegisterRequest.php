<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     DeviceRegisterRequest.php
 * Ubicación:   app/Http/Requests/DeviceRegisterRequest.php
 * Descripción: Validación del registro de token FCM / dispositivo móvil
 *              (api.v1.devices.store, MPM-V4 §6.8, MPW-V2 §6.3).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $token = $this->input('token') ?? $this->input('fcm_token') ?? $this->input('fingerprint');
        if (is_string($token) && trim($token) !== '') {
            $trimmed = trim($token);
            $this->merge([
                'token' => $trimmed,
                'fingerprint' => $trimmed,
            ]);
        }

        if (! $this->has('platform') || $this->input('platform') === null || $this->input('platform') === '') {
            $this->merge(['platform' => 'android']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
            'fingerprint' => ['nullable', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'model' => ['nullable', 'string', 'max:120'],
            'os_version' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'El campo token del dispositivo es obligatorio',
            'token.max' => 'El token del dispositivo no debe exceder los 512 caracteres',
            'platform.required' => 'El campo plataforma es obligatorio',
            'platform.in' => 'La opción seleccionada en plataforma no es válida',
            'model.max' => 'El modelo del dispositivo no debe exceder los 120 caracteres',
            'os_version.max' => 'La versión del sistema operativo no debe exceder los 60 caracteres',
        ];
    }
}
