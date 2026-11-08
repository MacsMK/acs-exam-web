<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherReviewStoreRequest.php
 * Ubicación:      app/Http/Requests/TeacherReviewStoreRequest.php
 * Requerimiento:  RF-IAR-002, RF-EVA-009, RW-11 (Recoteje Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Validación formal de las calificaciones manuales y notas
 *                 asignadas por el docente al recotejar evaluaciones de IA.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherReviewStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exam = $this->route('exam');

        return $this->user()?->role === 'teacher'
            && $exam !== null
            && (int) $exam->teacher_id === (int) $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reviews' => ['required', 'array', 'min:1'],
            'reviews.*.answer_id' => ['required', 'integer', 'exists:acs_student_answers,id'],
            'reviews.*.score' => ['required', 'numeric', 'min:0'],
            'reviews.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reviews.required' => 'El listado de reactivos a recotejar es obligatorio',
            'reviews.*.answer_id.required' => 'El identificador de la respuesta es obligatorio',
            'reviews.*.answer_id.exists' => 'La respuesta seleccionada no existe en el sistema',
            'reviews.*.score.required' => 'La calificación asignada por el docente es obligatoria',
            'reviews.*.score.numeric' => 'La calificación debe ser un valor numérico válido',
            'reviews.*.score.min' => 'La calificación no puede ser menor a cero',
            'reviews.*.notes.max' => 'Las notas de retroalimentación no pueden superar los 1,000 caracteres',
        ];
    }
}
