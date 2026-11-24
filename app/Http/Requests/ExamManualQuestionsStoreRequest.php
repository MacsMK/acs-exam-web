<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamManualQuestionsStoreRequest.php
 * Ubicación:      app/Http/Requests/ExamManualQuestionsStoreRequest.php
 * Requerimiento:  RW-12, RW-13 (Asignación Manual Dual desde Banco, MPW-V3 Tabla 18)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    FormRequest para agregar manualmente reactivos activos del
 *                 banco docente hacia un apartado existente o uno nuevo.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Http\Requests\Concerns\NormalizesQuestionInputs;
use App\Models\Exam;

/**
 * Validación formal de asignación manual de reactivos al examen.
 */
class ExamManualQuestionsStoreRequest extends FormRequest
{
    use NormalizesQuestionInputs;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || $user->role !== 'teacher') {
            return false;
        }

        $exam = $this->route('exam');
        if ($exam instanceof Exam) {
            return (int) $exam->teacher_id === (int) $user->id;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teacherId = $this->user()?->id;
        $exam = $this->route('exam');
        $examId = $exam instanceof Exam ? $exam->id : 0;

        return [
            'exam_section_id' => [
                'nullable',
                'integer',
                Rule::exists('acs_exam_sections', 'id')->where('exam_id', $examId),
            ],
            'new_section_title' => ['required_without:exam_section_id', 'nullable', 'string', 'max:120'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('acs_questions', 'id')
                    ->where('teacher_id', $teacherId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exam_section_id.integer' => 'El identificador del apartado debe ser un número entero',
            'exam_section_id.exists' => 'El apartado seleccionado no pertenece a este examen',
            'new_section_title.required_without' => 'Debes seleccionar un apartado existente o escribir el título de uno nuevo',
            'new_section_title.string' => 'El título del nuevo apartado debe ser una cadena de texto válida',
            'new_section_title.max' => 'El título del nuevo apartado no debe exceder los 120 caracteres',
            'question_ids.required' => 'Debes seleccionar al menos un reactivo del banco de preguntas',
            'question_ids.array' => 'Los reactivos seleccionados deben enviarse como un arreglo',
            'question_ids.min' => 'Debes seleccionar al menos un reactivo del banco de preguntas',
            'question_ids.*.required' => 'El identificador del reactivo es obligatorio',
            'question_ids.*.integer' => 'Cada reactivo seleccionado debe tener un identificador numérico entero',
            'question_ids.*.distinct' => 'No puedes incluir el mismo reactivo más de una vez',
            'question_ids.*.exists' => 'Uno o más reactivos seleccionados no existen, fueron eliminados o no pertenecen a tu banco',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('exam_section_id') && $this->input('exam_section_id') === '') {
            $payload['exam_section_id'] = null;
        }

        if ($this->has('new_section_title')) {
            $payload['new_section_title'] = $this->cleanSingleLineText($this->input('new_section_title'), true);
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
