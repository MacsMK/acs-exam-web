<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        SubjectSuggestRequest.php
 * Ubicación:      app/Http/Requests/SubjectSuggestRequest.php
 * Requerimiento:  RF-BAN (Detección Fuzzy de Materias Similares)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 12/11/2026
 * Descripción:    Validación de consulta de materias similares en el catálogo
 *                 del docente mientras captura o edita reactivos.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use App\Http\Requests\Concerns\NormalizesQuestionInputs;

class SubjectSuggestRequest extends FormRequest
{
    use NormalizesQuestionInputs;

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
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El campo nombre de materia es obligatorio',
            'name.string' => 'El nombre de materia debe ser una cadena de texto válida',
            'name.max' => 'El nombre de materia no debe exceder los 100 caracteres',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => $this->cleanSingleLineText($this->input('name'), false),
            ]);
        }
    }
}
