<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupJoinRequest.php
 * Ubicación:   app/Http/Requests/GroupJoinRequest.php
 * Descripción: Validación unificada para unirse a un grupo por folio o código
 *              QR (Web: access_folio; API móvil v1: folio | qr_payload).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $resolved = $this->input('folio') ?? $this->input('access_folio') ?? $this->input('qr_payload');
        if (is_string($resolved) && trim($resolved) !== '') {
            $normalized = strtoupper(trim($resolved));
            $this->merge([
                'folio' => $normalized,
                'access_folio' => $normalized,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'folio' => ['required_without_all:access_folio,qr_payload', 'nullable', 'string', 'max:12'],
            'access_folio' => ['required_without_all:folio,qr_payload', 'nullable', 'string', 'max:12'],
            'qr_payload' => ['required_without_all:folio,access_folio', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'folio.required_without_all' => 'El campo folio de acceso es obligatorio',
            'folio.max' => 'El folio de acceso no debe exceder los 12 caracteres',
            'access_folio.required_without_all' => 'El campo folio de acceso es obligatorio',
            'access_folio.max' => 'El folio de acceso no debe exceder los 12 caracteres',
            'qr_payload.required_without_all' => 'El campo código QR es obligatorio',
        ];
    }

    public function resolvedFolio(): string
    {
        return strtoupper(trim((string) ($this->input('folio') ?? $this->input('access_folio') ?? $this->input('qr_payload') ?? '')));
    }
}
