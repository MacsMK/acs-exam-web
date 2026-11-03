<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GroupStoreRequest.php
 * Ubicación:      app/Http/Requests/GroupStoreRequest.php
 * Requerimiento:  RW-32 a RW-36
 * Autor:          Equipo de Desarrollo Web (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    FormRequest de alta de grupo. Solo el docente dueño lo crea
 *                 (ADR-017).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de grupo (RW-32 a RW-36). Solo el docente dueño lo crea.
 */
class GroupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'teacher';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El campo nombre del grupo es obligatorio',
            'name.max' => 'El nombre del grupo no debe exceder los 120 caracteres',
            'subject.max' => 'La materia no debe exceder los 120 caracteres',
        ];
    }
}
