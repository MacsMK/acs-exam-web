<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamConfigurationUpdateRequest.php
 * Ubicación:      app/Http/Requests/ExamConfigurationUpdateRequest.php
 * Requerimiento:  RW-17..20, RW-25, MPW-V2 §6.3, Tabla 30
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Validación formal para la configuración de tiempos,
 *                 ponderaciones y candados de examen (ADR-017).
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;

class ExamConfigurationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || $user->role !== 'teacher') {
            return false;
        }

        $exam = $this->route('exam');
        if ($exam instanceof Exam) {
            return $exam->teacher_id === $user->id;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'grading_scale' => ['required', 'in:0-10,0-100'],
            'result_visibility_mode' => [
                'required',
                'in:final_score,estimated_score,objective_only_pending,hidden',
            ],
            'max_allowed_incidents' => ['required', 'integer', 'min:0'],
            'randomize_question_order' => ['required', 'boolean'],
            'require_webcam' => ['required', 'boolean'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer'],
            'questions' => ['required', 'array'],
            'questions.*.points_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'questions.*.time_limit_seconds' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El campo título es obligatorio',
            'title.max' => 'El título no debe exceder los 150 caracteres',
            'grading_scale.required' => 'El campo escala de calificación es obligatorio',
            'grading_scale.in' => 'La opción seleccionada en escala de calificación no es válida',
            'result_visibility_mode.required' => 'El campo visibilidad de resultados es obligatorio',
            'max_allowed_incidents.required' => 'El campo incidentes permitidos es obligatorio',
            'questions.required' => 'El examen debe incluir al menos una pregunta para configurarse',
            'questions.*.points_weight.required' => 'La ponderación por pregunta es obligatoria',
            'questions.*.points_weight.numeric' => 'La ponderación debe ser un valor numérico',
            'questions.*.time_limit_seconds.required' => 'El tiempo límite por pregunta es obligatorio',
            'questions.*.time_limit_seconds.min' => 'El tiempo asignado debe ser mayor que 0 segundos',
        ];
    }
}
