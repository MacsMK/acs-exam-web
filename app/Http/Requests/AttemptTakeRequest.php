<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AttemptTakeRequest.php
 * Ubicación:   app/Http/Requests/AttemptTakeRequest.php
 * Descripción: Validación para iniciar o reanudar un intento de examen
 *              (api.v1.exams.take y attempts.start, MPW-V2 §6.3).
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;

class AttemptTakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $routeExam = $this->route('exam');
        if ($routeExam instanceof Exam && ! $this->has('exam_id')) {
            $this->merge(['exam_id' => $routeExam->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'exam_id' => ['required', 'integer', 'exists:acs_exams,id'],
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exam_id.required' => 'El campo examen es obligatorio',
            'exam_id.exists' => 'La opción seleccionada en examen no es válida',
            'device_fingerprint.max' => 'La huella del dispositivo no debe exceder los 255 caracteres',
        ];
    }
}
