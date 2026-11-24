<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GroupUpdateRequest.php
 * Ubicación:      app/Http/Requests/GroupUpdateRequest.php
 * Requerimiento:  RF-GRU-003 (Modificación de grupos)
 * Actividad:      ACT-WEB-020
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    FormRequest de modificación de grupo: nombre obligatorio,
 *                 materia y descripción opcionales, con las mismas reglas
 *                 que el alta (GroupStoreRequest).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modificación de grupo (RF-GRU-003). La pertenencia al docente se valida en el controlador.
 */
class GroupUpdateRequest extends FormRequest
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
        return (new GroupStoreRequest())->rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return (new GroupStoreRequest())->messages();
    }
}
