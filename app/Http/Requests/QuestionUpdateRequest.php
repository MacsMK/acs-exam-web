<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionUpdateRequest.php
 * Ubicación:      app/Http/Requests/QuestionUpdateRequest.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, MPW-V2 §6.3, Tabla 17)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Validación tipada de actualización de reactivos polimórficos
 *                 con blindaje de inmutabilidad de tipo [MEDIA-01], saneamiento
 *                 Unicode y mensajes en español canónicos (MPW-V2 Tabla 17).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

use App\Http\Requests\Concerns\NormalizesQuestionInputs;
use App\Models\Question;

class QuestionUpdateRequest extends FormRequest
{
    use NormalizesQuestionInputs;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || $user->role !== 'teacher') {
            return false;
        }

        $question = $this->route('question');
        if ($question instanceof Question) {
            return $question->teacher_id === $user->id;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $question = $this->route('question');
        $allowedTypes = $question instanceof Question
            ? [$question->question_type]
            : Question::VALID_TYPES;

        return $this->polymorphicQuestionRules('', $allowedTypes);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateMatchingParity($validator);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->polymorphicQuestionMessages();
    }

    protected function prepareForValidation(): void
    {
        $sanitized = $this->sanitizeQuestionPayload($this->all());

        // [MEDIA-01] Blindaje de inmutabilidad: jamás permitir mutar question_type vía formulario.
        $question = $this->route('question');
        if ($question instanceof Question) {
            $sanitized['question_type'] = $question->question_type;
        }

        $this->replace($sanitized);
    }
}
