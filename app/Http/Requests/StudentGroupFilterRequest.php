<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentGroupFilterRequest.php
 * Ubicación:   app/Http/Requests/StudentGroupFilterRequest.php
 * Descripción: Filtro opcional por grupo de api.v1.exams.index y
 *              api.v1.grades.index (ADR-061, MPM-V3 Tabla 12). Lo usa el
 *              detalle del grupo del alumno en la app (RF-GRU-010,
 *              RF-EXA-026). Un grupo ajeno al alumno no es un error: el
 *              listado simplemente sale vacío.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentGroupFilterRequest extends FormRequest
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
            'group' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'group.integer' => 'El grupo indicado no es válido',
            'group.min' => 'El grupo indicado no es válido',
        ];
    }

    /**
     * Identificador del grupo pedido, o null si no se filtra.
     */
    public function groupId(): ?int
    {
        return $this->has('group') ? (int) $this->validated('group') : null;
    }
}
