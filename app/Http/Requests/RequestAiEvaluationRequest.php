<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        RequestAiEvaluationRequest.php
 * Ubicación:      app/Http/Requests/RequestAiEvaluationRequest.php
 * Requerimiento:  RF-IAR-001..014, RF-EVA-004, ADR-059, MPW-V2 Tabla 17
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Form Request que autoriza al docente verificado dueño del
 *                 examen y valida las banderas de solicitud de evaluación o
 *                 reevaluación bajo demanda con IA.
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de solicitud de evaluación individual asistida por IA.
 *
 * @package App\Http\Requests
 */
class RequestAiEvaluationRequest extends FormRequest
{
    /**
     * Autoriza exclusivamente al docente activo, verificado y titular del examen.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $exam = $this->route('exam');

        return $user !== null
            && $user->role === 'teacher'
            && (bool) $user->is_active
            && $user->isVerifiedTeacher()
            && $exam instanceof Exam
            && (int) $exam->teacher_id === (int) $user->id;
    }

    /**
     * Reglas de validación para la solicitud de evaluación con IA.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_reevaluation' => ['nullable', 'boolean'],
            'dismiss_warning' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mensajes normalizados en español conforme a MPW-V2 Tabla 17 (sin punto final).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_reevaluation.boolean' => 'El indicador de reevaluación debe ser verdadero o falso',
            'dismiss_warning.boolean' => 'El indicador para omitir la advertencia debe ser verdadero o falso',
        ];
    }
}
