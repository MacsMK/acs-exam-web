<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     QuestionParaphraseStoreRequest.php
 * Ubicación:   app/Http/Requests/QuestionParaphraseStoreRequest.php
 * Descripción: Validación de alta de paráfrasis anti-copia de un reactivo
 *              (RF-BAN-010, MPW-V2 §6.3, Tabla 17).
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;

class QuestionParaphraseStoreRequest extends FormRequest
{
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

    protected function prepareForValidation(): void
    {
        $text = $this->input('stem') ?? $this->input('paraphrased_text');
        if (is_string($text)) {
            $this->merge([
                'stem' => trim($text),
                'paraphrased_text' => trim($text),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stem' => ['required', 'string', 'min:3'],
            'paraphrased_text' => ['nullable', 'string', 'min:3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stem.required' => 'El campo redacción de paráfrasis es obligatorio',
            'stem.min' => 'La paráfrasis debe tener al menos 3 caracteres',
            'paraphrased_text.min' => 'La paráfrasis debe tener al menos 3 caracteres',
        ];
    }
}
