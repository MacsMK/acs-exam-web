<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionParaphraseStoreRequest.php
 * Ubicación:      app/Http/Requests/QuestionParaphraseStoreRequest.php
 * Requerimiento:  RF-BAN-010 (Paráfrasis Anti-Copia, MPW-V2 §6.3, Tabla 17)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 08/11/2026
 * Descripción:    Validación de alta de paráfrasis anti-copia con limpieza
 *                 Unicode, validación contra el enunciado raíz y duplicados,
 *                 y tope máximo de 10 variantes por reactivo.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

use App\Http\Requests\Concerns\NormalizesQuestionInputs;
use App\Models\Question;

class QuestionParaphraseStoreRequest extends FormRequest
{
    use NormalizesQuestionInputs;

    public const MAX_PARAPHRASES_PER_QUESTION = 10;

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
        return [
            'stem' => ['required', 'string', 'min:3', 'max:500'],
            'paraphrased_text' => ['nullable', 'string', 'min:3', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('stem')) {
                return;
            }

            $question = $this->route('question');
            if (! $question instanceof Question) {
                return;
            }

            $candidateNormalized = $this->normalizeStemForComparison((string) $this->input('stem', ''));
            if ($candidateNormalized === '') {
                return;
            }

            if ($candidateNormalized === $this->normalizeStemForComparison((string) $question->stem)) {
                $validator->errors()->add(
                    'stem',
                    'La paráfrasis no puede ser equivalente al enunciado principal del reactivo.'
                );

                return;
            }

            $existing = $question->paraphrases()->get();

            if ($existing->count() >= self::MAX_PARAPHRASES_PER_QUESTION) {
                $validator->errors()->add(
                    'stem',
                    sprintf('Has alcanzado el límite máximo de %d paráfrasis por reactivo.', self::MAX_PARAPHRASES_PER_QUESTION)
                );

                return;
            }

            foreach ($existing as $paraphrase) {
                if ($this->normalizeStemForComparison((string) $paraphrase->stem) === $candidateNormalized) {
                    $validator->errors()->add(
                        'stem',
                        'Ya existe una paráfrasis equivalente registrada para este reactivo.'
                    );

                    return;
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stem.required' => 'El campo redacción de paráfrasis es obligatorio',
            'stem.string' => 'La redacción de la paráfrasis debe ser una cadena de texto válida',
            'stem.min' => 'La paráfrasis debe tener al menos 3 caracteres',
            'stem.max' => 'La paráfrasis no debe exceder los 500 caracteres',
            'paraphrased_text.string' => 'La redacción de la paráfrasis debe ser una cadena de texto válida',
            'paraphrased_text.min' => 'La paráfrasis debe tener al menos 3 caracteres',
            'paraphrased_text.max' => 'La paráfrasis no debe exceder los 500 caracteres',
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('stem') ?? $this->input('paraphrased_text');
        if (! is_string($raw)) {
            return;
        }

        $cleaned = $this->cleanSingleLineText($raw, false);
        $this->merge([
            'stem' => $cleaned,
            'paraphrased_text' => $cleaned,
        ]);
    }
}
