<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupVerifyFolioRequest.php
 * Ubicación:   app/Http/Requests/GroupVerifyFolioRequest.php
 * Descripción: Validación de verificación de pertenencia a un grupo mediante
 *              escaneo QR o folio (api.v1.groups.verify, MPW-V2 §6.3).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupVerifyFolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $resolved = $this->input('qr_payload') ?? $this->input('folio') ?? $this->input('access_folio');
        if (is_string($resolved) && trim($resolved) !== '') {
            $normalized = strtoupper(trim($resolved));
            $this->merge([
                'qr_payload' => $normalized,
                'folio' => $normalized,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qr_payload' => ['required_without:folio', 'nullable', 'string', 'max:255'],
            'folio' => ['required_without:qr_payload', 'nullable', 'string', 'max:12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qr_payload.required_without' => 'El campo código QR es obligatorio',
            'folio.required_without' => 'El campo folio de acceso es obligatorio',
        ];
    }

    public function resolvedFolio(): string
    {
        return strtoupper(trim((string) ($this->input('qr_payload') ?? $this->input('folio') ?? '')));
    }
}
