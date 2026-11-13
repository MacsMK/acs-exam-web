<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        NormalizesQuestionInputs.php
 * Ubicación:      app/Http/Requests/Concerns/NormalizesQuestionInputs.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, MPW-V2 §6.3, Tabla 17)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 12/11/2026
 * Descripción:    Trait compartido para saneamiento Unicode de entradas de
 *                 reactivos, reglas polimórficas unificadas, validación de
 *                 paridad en matching y mensajes normativos Tabla 17 en español.
 * ============================================================================
 */

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

use App\Models\Subject;

trait NormalizesQuestionInputs
{
    /**
     * Caracteres de control/formato Unicode invisibles que deben depurarse.
     *
     * @var array<int, string>
     */
    private const INVISIBLE_UNICODE_CHARS = [
        "\u{200B}",
        "\u{200C}",
        "\u{200D}",
        "\u{200E}",
        "\u{200F}",
        "\u{202A}",
        "\u{202B}",
        "\u{202C}",
        "\u{202D}",
        "\u{202E}",
        "\u{2060}",
        "\u{FEFF}",
        "\u{00AD}",
    ];

    /**
     * Espacios no separables / tipográficos que deben homogeneizarse a espacio ASCII.
     *
     * @var array<int, string>
     */
    private const UNICODE_SPACE_VARIANTS = [
        "\u{00A0}",
        "\u{2007}",
        "\u{2009}",
        "\u{202F}",
    ];

    /**
     * Normaliza un texto de una sola línea eliminando Unicode invisible,
     * reemplazando espacios especiales, colapsando múltiples espacios y recortando.
     */
    protected function cleanSingleLineText(mixed $value, bool $nullIfEmpty = false): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $cleaned = Subject::cleanDisplayName($this->stripExtraInvisibleChars($value));

        if ($cleaned === null) {
            return $nullIfEmpty ? null : '';
        }

        return $cleaned;
    }

    /**
     * Normaliza un texto multilínea (enunciado, rúbrica, respuesta abierta, instrucciones)
     * eliminando caracteres Unicode invisibles y espacios extremos sin destruir saltos de línea.
     */
    protected function cleanMultilineText(mixed $value, bool $nullIfEmpty = false): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $stripped = $this->stripExtraInvisibleChars($value);
        $normalizedSpaces = str_replace(self::UNICODE_SPACE_VARIANTS, ' ', $stripped);
        $normalizedEol = str_replace(["\r\n", "\r"], "\n", $normalizedSpaces);
        $trimmed = trim($normalizedEol);

        if ($trimmed === '') {
            return $nullIfEmpty ? null : '';
        }

        return $trimmed;
    }

    /**
     * Limpia únicamente caracteres invisibles en código fuente preservando indentación.
     */
    protected function cleanCodeText(mixed $value, bool $nullIfWhitespaceOnly = false): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $stripped = $this->stripExtraInvisibleChars($value);
        $normalizedSpaces = str_replace(self::UNICODE_SPACE_VARIANTS, ' ', $stripped);
        $normalizedEol = str_replace(["\r\n", "\r"], "\n", $normalizedSpaces);

        if ($nullIfWhitespaceOnly && trim($normalizedEol) === '') {
            return null;
        }

        return $normalizedEol;
    }

    /**
     * Reindexa secuencialmente un arreglo y aplica limpieza de una línea a sus ítems textuales.
     */
    protected function cleanSingleLineArray(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_values(array_map(
            fn (mixed $item): mixed => $this->cleanSingleLineText($item, false),
            $value
        ));
    }

    /**
     * Normaliza una cadena para detección de equivalencia semántica en paráfrasis
     * (minúsculas UTF-8, sin Unicode invisible y sin espacios redundantes).
     */
    protected function normalizeStemForComparison(string $value): string
    {
        $single = $this->cleanSingleLineText($value, false);

        return mb_strtolower(is_string($single) ? $single : '', 'UTF-8');
    }

    /**
     * Aplica la limpieza estándar de payload de reactivo (Store y Update) sobre un arreglo.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function sanitizeQuestionPayload(array $input): array
    {
        foreach (['title', 'programming_language'] as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = $this->cleanSingleLineText($input[$field], false);
            }
        }

        if (array_key_exists('subject_tag', $input)) {
            $input['subject_tag'] = $this->cleanSingleLineText($input['subject_tag'], true);
        }

        if (array_key_exists('stem', $input)) {
            $input['stem'] = $this->cleanMultilineText($input['stem'], false);
        }

        if (array_key_exists('reference_answer', $input)) {
            $input['reference_answer'] = $this->cleanMultilineText($input['reference_answer'], false);
        }

        foreach (['evaluation_rubric', 'instructions'] as $optionalMultiline) {
            if (array_key_exists($optionalMultiline, $input)) {
                $input[$optionalMultiline] = $this->cleanMultilineText($input[$optionalMultiline], true);
            }
        }

        if (array_key_exists('starter_code', $input)) {
            $input['starter_code'] = $this->cleanCodeText($input['starter_code'], true);
        }

        if (array_key_exists('reference_solution', $input)) {
            $input['reference_solution'] = $this->cleanCodeText($input['reference_solution'], true) ?? '';
        }

        foreach (['options', 'concepts', 'definitions', 'initial_paraphrases'] as $arrayField) {
            if (array_key_exists($arrayField, $input)) {
                $input[$arrayField] = $this->cleanSingleLineArray($input[$arrayField]);
            }
        }

        if (array_key_exists('is_find_error', $input)) {
            $input['is_find_error'] = $this->normalizeOptionalBoolean($input['is_find_error']);
        }

        return $input;
    }

    /**
     * Reglas canónicas compartidas para un reactivo polimórfico (con prefijo opcional para lotes).
     *
     * @param array<int, string> $allowedTypes
     * @return array<string, mixed>
     */
    protected function polymorphicQuestionRules(
        string $prefix = '',
        array $allowedTypes = ['multiple_choice', 'matching', 'open', 'code'],
        ?Closure $optionsResolver = null,
        ?Closure $isFindErrorResolver = null,
    ): array {
        $typeKey = $prefix . 'question_type';

        $optionsClosure = $optionsResolver ?? fn (): mixed => $this->input($prefix . 'options', []);
        $findErrorClosure = $isFindErrorResolver ?? fn (): bool => $this->input($typeKey) === 'code'
            && filter_var($this->input($prefix . 'is_find_error'), FILTER_VALIDATE_BOOLEAN);

        return [
            $prefix . 'title' => ['required', 'string', 'max:150'],
            $prefix . 'stem' => ['required', 'string', 'min:3', 'max:5000'],
            $typeKey => ['required', 'string', Rule::in($allowedTypes)],
            $prefix . 'subject_tag' => ['nullable', 'string', 'max:100'],
            $prefix . 'default_points' => ['required', 'numeric', 'decimal:0,2', 'min:0.1', 'max:100'],
            $prefix . 'default_time_seconds' => ['required', 'integer', 'min:5', 'max:3600'],

            // multiple_choice
            $prefix . 'options' => ["exclude_unless:{$typeKey},multiple_choice", 'required', 'array', 'list', 'min:2', 'max:6'],
            $prefix . 'options.*' => ["exclude_unless:{$typeKey},multiple_choice", 'required', 'string', 'min:1', 'max:500', 'distinct'],
            $prefix . 'correct_option_index' => [
                "exclude_unless:{$typeKey},multiple_choice",
                'bail',
                'required',
                'integer',
                'min:0',
                static function (string $attribute, mixed $value, Closure $fail) use ($optionsClosure): void {
                    $options = $optionsClosure();
                    if (is_array($options) && ! empty($options) && (int) $value >= count($options)) {
                        $fail('El índice de la respuesta correcta debe corresponder a una de las opciones registradas.');
                    }
                },
            ],

            // matching
            $prefix . 'concepts' => ["exclude_unless:{$typeKey},matching", 'required', 'array', 'list', 'min:2', 'max:8'],
            $prefix . 'concepts.*' => ["exclude_unless:{$typeKey},matching", 'required', 'string', 'min:1', 'max:500', 'distinct'],
            $prefix . 'definitions' => ["exclude_unless:{$typeKey},matching", 'required', 'array', 'list', 'min:2', 'max:8'],
            $prefix . 'definitions.*' => ["exclude_unless:{$typeKey},matching", 'required', 'string', 'min:1', 'max:500', 'distinct'],

            // open
            $prefix . 'reference_answer' => ["exclude_unless:{$typeKey},open", 'required', 'string', 'max:10000'],
            $prefix . 'evaluation_rubric' => ["exclude_unless:{$typeKey},open", 'nullable', 'string', 'max:10000'],

            // code
            $prefix . 'programming_language' => ["exclude_unless:{$typeKey},code", 'required', 'string', 'max:40'],
            $prefix . 'is_find_error' => ["exclude_unless:{$typeKey},code", 'sometimes', 'boolean'],
            $prefix . 'starter_code' => [
                "exclude_unless:{$typeKey},code",
                Rule::requiredIf($findErrorClosure),
                'nullable',
                'string',
                'max:10000',
            ],
            $prefix . 'reference_solution' => ["exclude_unless:{$typeKey},code", 'required', 'string', 'max:10000'],
            $prefix . 'instructions' => ["exclude_unless:{$typeKey},code", 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Valida que los arreglos paralelos de relación de columnas tengan exactamente la misma longitud.
     */
    protected function validateMatchingParity(Validator $validator, string $prefix = ''): void
    {
        if ($this->input($prefix . 'question_type') !== 'matching') {
            return;
        }

        $concepts = $this->input($prefix . 'concepts');
        $definitions = $this->input($prefix . 'definitions');

        if (is_array($concepts) && is_array($definitions) && count($concepts) !== count($definitions)) {
            $validator->errors()->add(
                $prefix . 'definitions',
                'La cantidad de conceptos y definiciones debe ser exactamente igual.'
            );
        }
    }

    /**
     * Mensajes canónicos en español conforme a MPW-V2 Tabla 17.
     *
     * @return array<string, string>
     */
    protected function polymorphicQuestionMessages(string $prefix = ''): array
    {
        return [
            $prefix . 'title.required' => 'El campo título es obligatorio',
            $prefix . 'title.string' => 'El título debe ser una cadena de texto válida',
            $prefix . 'title.max' => 'El título no debe exceder los 150 caracteres',

            $prefix . 'stem.required' => 'El campo enunciado es obligatorio',
            $prefix . 'stem.string' => 'El enunciado debe ser una cadena de texto válida',
            $prefix . 'stem.min' => 'El enunciado debe contener al menos 3 caracteres',
            $prefix . 'stem.max' => 'El enunciado no debe exceder los 5000 caracteres',

            $prefix . 'question_type.required' => 'El campo tipo de reactivo es obligatorio',
            $prefix . 'question_type.string' => 'El tipo de reactivo debe ser una cadena de texto válida',
            $prefix . 'question_type.in' => 'La opción seleccionada en tipo de reactivo no es válida',

            $prefix . 'subject_tag.string' => 'La materia debe ser una cadena de texto válida',
            $prefix . 'subject_tag.max' => 'La materia no debe exceder los 100 caracteres',

            $prefix . 'default_points.required' => 'El campo ponderación es obligatorio',
            $prefix . 'default_points.numeric' => 'La ponderación debe ser un valor numérico',
            $prefix . 'default_points.decimal' => 'La ponderación puede incluir máximo 2 decimales',
            $prefix . 'default_points.min' => 'La ponderación debe ser mayor o igual que 0.1 puntos',
            $prefix . 'default_points.max' => 'La ponderación no debe exceder los 100 puntos',

            $prefix . 'default_time_seconds.required' => 'El campo tiempo límite es obligatorio',
            $prefix . 'default_time_seconds.integer' => 'El tiempo límite debe ser un número entero de segundos',
            $prefix . 'default_time_seconds.min' => 'El tiempo límite debe ser mayor o igual que 5 segundos',
            $prefix . 'default_time_seconds.max' => 'El tiempo límite no debe exceder los 3600 segundos',

            $prefix . 'options.required' => 'El campo opciones de respuesta es obligatorio',
            $prefix . 'options.array' => 'Las opciones de respuesta deben enviarse como un arreglo',
            $prefix . 'options.list' => 'Las opciones de respuesta deben enviarse como una lista secuencial',
            $prefix . 'options.min' => 'Las opciones de respuesta deben contener al menos 2 elementos',
            $prefix . 'options.max' => 'Las opciones de respuesta no deben exceder 6 alternativas',
            $prefix . 'options.*.required' => 'Cada alternativa de respuesta debe contener texto',
            $prefix . 'options.*.string' => 'Cada alternativa de respuesta debe ser una cadena de texto válida',
            $prefix . 'options.*.min' => 'Cada alternativa de respuesta debe contener al menos 1 carácter',
            $prefix . 'options.*.max' => 'Cada alternativa de respuesta no debe exceder los 500 caracteres',
            $prefix . 'options.*.distinct' => 'Las alternativas de respuesta no pueden repetirse',

            $prefix . 'correct_option_index.required' => 'El campo respuesta correcta es obligatorio',
            $prefix . 'correct_option_index.integer' => 'El índice de la respuesta correcta debe ser un número entero',
            $prefix . 'correct_option_index.min' => 'El índice de la respuesta correcta debe ser mayor o igual que 0',

            $prefix . 'concepts.required' => 'El campo conceptos es obligatorio',
            $prefix . 'concepts.array' => 'Los conceptos deben enviarse como un arreglo',
            $prefix . 'concepts.list' => 'Los conceptos deben enviarse como una lista secuencial',
            $prefix . 'concepts.min' => 'Los conceptos deben contener al menos 2 elementos',
            $prefix . 'concepts.max' => 'Los conceptos no deben exceder 8 pares',
            $prefix . 'concepts.*.required' => 'Cada concepto debe contener texto',
            $prefix . 'concepts.*.string' => 'Cada concepto debe ser una cadena de texto válida',
            $prefix . 'concepts.*.min' => 'Cada concepto debe contener al menos 1 carácter',
            $prefix . 'concepts.*.max' => 'Cada concepto no debe exceder los 500 caracteres',
            $prefix . 'concepts.*.distinct' => 'Los conceptos de la columna izquierda no pueden repetirse',

            $prefix . 'definitions.required' => 'El campo definiciones es obligatorio',
            $prefix . 'definitions.array' => 'Las definiciones deben enviarse como un arreglo',
            $prefix . 'definitions.list' => 'Las definiciones deben enviarse como una lista secuencial',
            $prefix . 'definitions.min' => 'Las definiciones deben contener al menos 2 elementos',
            $prefix . 'definitions.max' => 'Las definiciones no deben exceder 8 pares',
            $prefix . 'definitions.*.required' => 'Cada definición debe contener texto',
            $prefix . 'definitions.*.string' => 'Cada definición debe ser una cadena de texto válida',
            $prefix . 'definitions.*.min' => 'Cada definición debe contener al menos 1 carácter',
            $prefix . 'definitions.*.max' => 'Cada definición no debe exceder los 500 caracteres',
            $prefix . 'definitions.*.distinct' => 'Las definiciones de la columna derecha no pueden repetirse',

            $prefix . 'reference_answer.required' => 'El campo respuesta de referencia es obligatorio',
            $prefix . 'reference_answer.string' => 'La respuesta de referencia debe ser una cadena de texto válida',
            $prefix . 'reference_answer.max' => 'La respuesta de referencia no debe exceder los 10000 caracteres',

            $prefix . 'evaluation_rubric.string' => 'La rúbrica de evaluación debe ser una cadena de texto válida',
            $prefix . 'evaluation_rubric.max' => 'La rúbrica de evaluación no debe exceder los 10000 caracteres',

            $prefix . 'programming_language.required' => 'El campo lenguaje de programación es obligatorio',
            $prefix . 'programming_language.string' => 'El lenguaje de programación debe ser una cadena de texto válida',
            $prefix . 'programming_language.max' => 'El lenguaje de programación no debe exceder los 40 caracteres',

            $prefix . 'is_find_error.boolean' => 'El indicador de modalidad encontrar el error debe ser booleano',

            $prefix . 'starter_code.required' => 'En la modalidad de encontrar el error, el código inicial con defecto es obligatorio',
            $prefix . 'starter_code.required_if' => 'En la modalidad de encontrar el error, el código inicial con defecto es obligatorio',
            $prefix . 'starter_code.string' => 'El código inicial debe ser una cadena de texto válida',
            $prefix . 'starter_code.max' => 'El código inicial no debe exceder los 10000 caracteres',

            $prefix . 'reference_solution.required' => 'El campo solución de referencia es obligatorio',
            $prefix . 'reference_solution.string' => 'La solución de referencia debe ser una cadena de texto válida',
            $prefix . 'reference_solution.max' => 'La solución de referencia no debe exceder los 10000 caracteres',

            $prefix . 'instructions.string' => 'Las instrucciones deben ser una cadena de texto válida',
            $prefix . 'instructions.max' => 'Las instrucciones no deben exceder los 5000 caracteres',
        ];
    }

    private function stripExtraInvisibleChars(string $value): string
    {
        return str_replace(self::INVISIBLE_UNICODE_CHARS, '', $value);
    }

    private function normalizeOptionalBoolean(mixed $value): mixed
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
