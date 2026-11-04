<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionImportRequest.php
 * Ubicación:      app/Http/Requests/QuestionImportRequest.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico - Importación CSV)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Validación del archivo CSV cargado para importación por lotes.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionImportRequest extends FormRequest
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
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv_file.required' => 'El archivo CSV es obligatorio',
            'csv_file.file' => 'El archivo cargado debe ser válido',
            'csv_file.mimes' => 'El archivo debe tener formato .csv o .txt',
            'csv_file.max' => 'El archivo CSV no debe exceder los 5 MB',
        ];
    }
}
