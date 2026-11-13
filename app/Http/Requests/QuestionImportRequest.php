<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionImportRequest.php
 * Ubicación:      app/Http/Requests/QuestionImportRequest.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico - Importación CSV/XLSX)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 08/11/2026
 * Descripción:    Validación del archivo CSV, TXT o Excel (.xlsx) cargado para
 *                 previsualización dry-run e importación atómica por lotes.
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
            'csv_file' => [
                'required',
                'file',
                'max:5120',
                'mimes:csv,txt,xlsx',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv_file.required' => 'El archivo de importación es obligatorio',
            'csv_file.file' => 'El archivo cargado debe ser válido',
            'csv_file.max' => 'El archivo de importación no debe exceder los 5 MB',
            'csv_file.mimes' => 'El archivo debe tener formato .csv, .txt o .xlsx',
            'csv_file.mimetypes' => 'El tipo de contenido del archivo no corresponde a CSV, TXT o Excel (.xlsx)',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->hasFile('csv_file') && $this->hasFile('import_file')) {
            $this->files->set('csv_file', $this->file('import_file'));
        }
    }
}
