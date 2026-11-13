<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionBatchStoreRequest.php
 * Ubicación:      app/Http/Requests/QuestionBatchStoreRequest.php
 * Requerimiento:  RF-BAN (Captura Rápida en Lote en Banco de Preguntas)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 12/11/2026
 * Descripción:    Validación transaccional de captura rápida en lote (hasta 30
 *                 reactivos polimórficos por envío) con asignatura compartida
 *                 opcional y mensajes canónicos Tabla 17 en español.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

use App\Http\Requests\Concerns\NormalizesQuestionInputs;
use App\Models\Question;

class QuestionBatchStoreRequest extends FormRequest
{
    use NormalizesQuestionInputs;

    public const MAX_BATCH_ITEMS = 30;

    public function authorize(): bool
    {
        return $this->user()?->role === 'teacher';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'shared_subject_tag' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:' . self::MAX_BATCH_ITEMS],
        ];

        $items = $this->input('items');
        if (! is_array($items) || $items === []) {
            return array_merge($rules, $this->polymorphicQuestionRules('items.*.', Question::VALID_TYPES));
        }

        foreach (array_slice(array_keys($items), 0, self::MAX_BATCH_ITEMS) as $index) {
            $prefix = "items.{$index}.";
            $rules = array_merge(
                $rules,
                $this->polymorphicQuestionRules(
                    $prefix,
                    Question::VALID_TYPES,
                    fn (): mixed => $this->input("{$prefix}options", []),
                    fn (): bool => $this->input("{$prefix}question_type") === 'code'
                        && filter_var($this->input("{$prefix}is_find_error"), FILTER_VALIDATE_BOOLEAN)
                )
            );
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items');
            if (! is_array($items)) {
                return;
            }

            foreach (array_keys($items) as $index) {
                $this->validateMatchingParity($validator, "items.{$index}.");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = array_merge(
            [
                'shared_subject_tag.string' => 'La materia compartida debe ser una cadena de texto válida',
                'shared_subject_tag.max' => 'La materia compartida no debe exceder los 100 caracteres',
                'items.required' => 'Debes capturar al menos un reactivo en el lote',
                'items.array' => 'Los reactivos del lote deben enviarse como un arreglo',
                'items.list' => 'Los reactivos del lote deben enviarse como una lista secuencial',
                'items.min' => 'Debes capturar al menos un reactivo en el lote',
                'items.max' => sprintf('La captura rápida en lote admite máximo %d reactivos por envío', self::MAX_BATCH_ITEMS),
            ],
            $this->polymorphicQuestionMessages('items.*.')
        );

        $items = $this->input('items');
        if (is_array($items)) {
            foreach (array_slice(array_keys($items), 0, self::MAX_BATCH_ITEMS) as $index) {
                $messages = array_merge($messages, $this->polymorphicQuestionMessages("items.{$index}."));
            }
        }

        return $messages;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        $sharedSubject = array_key_exists('shared_subject_tag', $input)
            ? $this->cleanSingleLineText($input['shared_subject_tag'], true)
            : null;

        if (array_key_exists('shared_subject_tag', $input)) {
            $input['shared_subject_tag'] = $sharedSubject;
        }

        if (isset($input['items']) && is_array($input['items'])) {
            $sanitizedItems = [];
            foreach (array_values($input['items']) as $item) {
                if (! is_array($item)) {
                    $sanitizedItems[] = $item;
                    continue;
                }

                $cleanItem = $this->sanitizeQuestionPayload($item);
                if ((! isset($cleanItem['subject_tag']) || $cleanItem['subject_tag'] === null) && is_string($sharedSubject)) {
                    $cleanItem['subject_tag'] = $sharedSubject;
                }
                $sanitizedItems[] = $cleanItem;
            }

            $input['items'] = $sanitizedItems;
        }

        $this->replace($input);
    }
}
