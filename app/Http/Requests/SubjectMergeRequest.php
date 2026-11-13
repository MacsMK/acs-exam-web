<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        SubjectMergeRequest.php
 * Ubicación:      app/Http/Requests/SubjectMergeRequest.php
 * Requerimiento:  RF-BAN (Normalización y Fusión de Materias en Banco)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 12/11/2026
 * Descripción:    Validación de fusión transaccional de materias duplicadas o
 *                 con variantes tipográficas pertenecientes al docente.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Http\Requests\Concerns\NormalizesQuestionInputs;

class SubjectMergeRequest extends FormRequest
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
        $teacherId = $this->user()?->id;

        return [
            'source_subject_ids' => ['required', 'array', 'list', 'min:1'],
            'source_subject_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('acs_subjects', 'id')->where('teacher_id', $teacherId),
            ],
            'target_name' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_subject_ids.required' => 'Debes seleccionar al menos una materia origen para fusionar',
            'source_subject_ids.array' => 'Las materias origen deben enviarse como un arreglo',
            'source_subject_ids.list' => 'Las materias origen deben enviarse como una lista secuencial',
            'source_subject_ids.min' => 'Debes seleccionar al menos una materia origen para fusionar',
            'source_subject_ids.*.required' => 'Cada materia seleccionada debe tener un identificador válido',
            'source_subject_ids.*.integer' => 'Cada identificador de materia debe ser un número entero',
            'source_subject_ids.*.distinct' => 'No puedes repetir la misma materia origen en la fusión',
            'source_subject_ids.*.exists' => 'Alguna materia seleccionada no pertenece a tu catálogo docente',
            'target_name.required' => 'El campo nombre canónico destino es obligatorio',
            'target_name.string' => 'El nombre destino debe ser una cadena de texto válida',
            'target_name.min' => 'El nombre destino debe contener al menos 2 caracteres',
            'target_name.max' => 'El nombre destino no debe exceder los 100 caracteres',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('target_name')) {
            $payload['target_name'] = $this->cleanSingleLineText($this->input('target_name'), false);
        }

        if (is_array($this->input('source_subject_ids'))) {
            $payload['source_subject_ids'] = array_values($this->input('source_subject_ids'));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
