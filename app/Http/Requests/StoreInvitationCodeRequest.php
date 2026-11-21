<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        StoreInvitationCodeRequest.php
 * Ubicación:      app/Http/Requests/StoreInvitationCodeRequest.php
 * Requerimiento:  RW-05, ADR-057 (Emisión de Códigos Institucionales Docentes)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Valida la creación de un código de invitación institucional
 *                 con cupo acotado, vigencia futura opcional y prefijo sanitizado.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para la generación administrativa de códigos institucionales.
 *
 * @package App\Http\Requests
 */
class StoreInvitationCodeRequest extends FormRequest
{
    /**
     * Autoriza exclusivamente al rol super_admin.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'super_admin';
    }

    /**
     * Reglas de validación de parámetros del código institucional.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'max_uses' => ['required', 'integer', 'min:1', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'custom_prefix' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{2,10}$/'],
        ];
    }

    /**
     * Mensajes de validación normalizados conforme a MPW-V3 §8.11 / Tabla 18.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'institution_name.required' => 'El campo institución educativa es obligatorio',
            'institution_name.string' => 'El campo institución educativa no tiene un formato válido',
            'institution_name.max' => 'El campo institución educativa no debe exceder los 150 caracteres',

            'description.string' => 'El campo descripción no tiene un formato válido',
            'description.max' => 'El campo descripción no debe exceder los 255 caracteres',

            'max_uses.required' => 'El campo límite de usos es obligatorio',
            'max_uses.integer' => 'El límite de usos no tiene un formato válido',
            'max_uses.min' => 'El límite de usos debe ser mayor que 0',
            'max_uses.max' => 'El límite de usos debe ser menor o igual que 500',

            'expires_at.date' => 'La fecha de expiración no tiene un formato válido',
            'expires_at.after' => 'La fecha de expiración debe ser posterior a la fecha actual',

            'custom_prefix.string' => 'El prefijo personalizado no tiene un formato válido',
            'custom_prefix.regex' => 'El prefijo personalizado no tiene un formato válido',
        ];
    }

    /**
     * Limpia campos opcionales antes de validar reglas y rangos.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('institution_name') && is_string($this->input('institution_name'))) {
            $payload['institution_name'] = trim($this->input('institution_name'));
        }

        if ($this->has('description') && is_string($this->input('description'))) {
            $description = trim($this->input('description'));
            $payload['description'] = $description !== '' ? $description : null;
        }

        if ($this->has('expires_at') && is_string($this->input('expires_at'))) {
            $expiresAt = trim($this->input('expires_at'));
            $payload['expires_at'] = $expiresAt !== '' ? $expiresAt : null;
        }

        if ($this->has('custom_prefix') && is_string($this->input('custom_prefix'))) {
            $prefix = strtoupper(trim($this->input('custom_prefix')));
            $payload['custom_prefix'] = $prefix !== '' ? $prefix : null;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
