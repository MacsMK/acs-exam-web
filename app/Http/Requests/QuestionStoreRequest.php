<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionStoreRequest.php
 * Ubicación:      app/Http/Requests/QuestionStoreRequest.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, MPW-V2 §6.3, Tabla 7 y Tabla 17)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Validación tipada de creación de reactivo polimórfico en el
 *                 Banco de Preguntas con saneamiento Unicode, paráfrasis
 *                 iniciales opcionales, guardar-y-nuevo y mensajes Tabla 17.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

use App\Http\Requests\Concerns\NormalizesQuestionInputs;

class QuestionStoreRequest extends FormRequest
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
        return array_merge(
            $this->polymorphicQuestionRules(),
            [
                'save_and_new' => ['nullable', 'boolean'],
                'initial_paraphrases' => ['nullable', 'array', 'list', 'max:3'],
                'initial_paraphrases.*' => ['required', 'string', 'min:3', 'max:500', 'distinct'],
            ]
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateMatchingParity($validator);
            $this->validateInitialParaphrases($validator);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            $this->polymorphicQuestionMessages(),
            [
                'save_and_new.boolean' => 'La opción guardar y crear otro debe ser booleana',
                'initial_paraphrases.array' => 'Las paráfrasis iniciales deben enviarse como un arreglo',
                'initial_paraphrases.list' => 'Las paráfrasis iniciales deben enviarse como una lista secuencial',
                'initial_paraphrases.max' => 'Solo puedes registrar hasta 3 paráfrasis iniciales al crear el reactivo',
                'initial_paraphrases.*.required' => 'Cada paráfrasis inicial es obligatoria y no puede estar vacía',
                'initial_paraphrases.*.string' => 'Cada paráfrasis inicial debe ser una cadena de texto válida',
                'initial_paraphrases.*.min' => 'Cada paráfrasis inicial debe tener al menos 3 caracteres',
                'initial_paraphrases.*.max' => 'Cada paráfrasis inicial no debe exceder los 500 caracteres',
                'initial_paraphrases.*.distinct' => 'Las paráfrasis iniciales no pueden repetirse entre sí',
            ]
        );
    }

    protected function prepareForValidation(): void
    {
        $sanitized = $this->sanitizeQuestionPayload($this->all());

        if (array_key_exists('save_and_new', $sanitized)) {
            $sanitized['save_and_new'] = $this->normalizeSaveAndNew($sanitized['save_and_new']);
        }

        $this->replace($sanitized);
    }

    private function validateInitialParaphrases(Validator $validator): void
    {
        $paraphrases = $this->input('initial_paraphrases');
        if (! is_array($paraphrases) || $paraphrases === []) {
            return;
        }

        $normalizedStem = $this->normalizeStemForComparison((string) $this->input('stem', ''));
        $seen = [];

        foreach ($paraphrases as $index => $raw) {
            if (! is_string($raw)) {
                continue;
            }

            $normalized = $this->normalizeStemForComparison($raw);
            if ($normalized === '') {
                continue;
            }

            if ($normalizedStem !== '' && $normalized === $normalizedStem) {
                $validator->errors()->add(
                    "initial_paraphrases.{$index}",
                    'La paráfrasis inicial no puede ser equivalente al enunciado principal.'
                );
                continue;
            }

            if (isset($seen[$normalized])) {
                $validator->errors()->add(
                    "initial_paraphrases.{$index}",
                    'Las paráfrasis iniciales no pueden ser equivalentes entre sí.'
                );
                continue;
            }

            $seen[$normalized] = true;
        }
    }

    private function normalizeSaveAndNew(mixed $value): mixed
    {
        if (in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($value, [false, 0, '0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return $value;
    }
}
