<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        RedeemInvitationCodeRequest.php
 * Ubicación:      app/Http/Requests/RedeemInvitationCodeRequest.php
 * Requerimiento:  RF-AUT-001, ADR-057 (Canje Posterior de Código Institucional)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Valida el canje de un código institucional por parte de un
 *                 docente cuya cuenta se encuentra pendiente o rechazada.
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Models\TeacherInvitationCode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para el canje posterior de un código de invitación docente.
 *
 * @package App\Http\Requests
 */
class RedeemInvitationCodeRequest extends FormRequest
{
    /**
     * Autoriza exclusivamente a docentes aún no verificados.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role === 'teacher'
            && ! $user->isVerifiedTeacher();
    }

    /**
     * Reglas de validación del código institucional.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invitation_code' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * Mensajes de validación normalizados conforme a MPW-V2 §8.11 / Tabla 17.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invitation_code.required' => 'El campo código de invitación es obligatorio',
            'invitation_code.string' => 'El código de invitación no tiene un formato válido',
            'invitation_code.max' => 'El código de invitación no debe exceder los 32 caracteres',
        ];
    }

    /**
     * Normaliza el código eliminando espacios y convirtiéndolo a mayúsculas.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('invitation_code') && is_string($this->input('invitation_code'))) {
            $normalized = TeacherInvitationCode::normalizeCode($this->input('invitation_code'));
            $this->merge([
                'invitation_code' => $normalized !== '' ? $normalized : null,
            ]);
        }
    }
}
