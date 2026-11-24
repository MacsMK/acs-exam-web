<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherReviewStoreRequest.php
 * Ubicación:      app/Http/Requests/TeacherReviewStoreRequest.php
 * Requerimiento:  RF-IAR-002, RF-EVA-009, RW-11 (Recoteje Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Descripción:    Validación formal de las calificaciones manuales y notas
 *                 asignadas por el docente al recotejar evaluaciones de IA,
 *                 restringiendo reactivos cualitativos y límites por reactivo.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

use App\Models\Exam;
use App\Models\StudentAttempt;
use App\Support\AcademicNumber;

class TeacherReviewStoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $reviews = $this->input('reviews');
        if (! is_array($reviews)) {
            return;
        }

        foreach ($reviews as $index => $review) {
            if (is_array($review) && array_key_exists('score', $review)) {
                $reviews[$index]['score'] = AcademicNumber::normalize($review['score']);
            }
        }

        $this->merge(['reviews' => $reviews]);
    }

    public function authorize(): bool
    {
        $exam = $this->route('exam');

        return $this->user()?->role === 'teacher'
            && $exam instanceof Exam
            && (int) $exam->teacher_id === (int) $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reviews' => ['required', 'array', 'min:1'],
            'reviews.*.answer_id' => ['required', 'integer', 'distinct', 'exists:acs_student_answers,id'],
            'reviews.*.score' => ['required', 'numeric', 'min:0'],
            'reviews.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $exam = $this->route('exam');
            $attempt = $this->route('attempt');

            if (! ($exam instanceof Exam) || ! ($attempt instanceof StudentAttempt)) {
                return;
            }

            if ((int) $attempt->exam_id !== (int) $exam->id) {
                $validator->errors()->add('attempt', 'El intento seleccionado no pertenece a este examen');

                return;
            }

            if ($attempt->status !== 'submitted') {
                $validator->errors()->add('attempt', 'Solo se pueden revisar intentos entregados');

                return;
            }

            $reviews = $this->input('reviews');
            if (! is_array($reviews) || $reviews === []) {
                return;
            }

            $answerIds = collect($reviews)
                ->pluck('answer_id')
                ->filter(static fn (mixed $id): bool => is_numeric($id))
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $answers = $attempt->answers()
                ->with('examQuestion.question')
                ->whereIn('id', $answerIds)
                ->get()
                ->keyBy('id');

            foreach ($reviews as $index => $review) {
                if (! is_array($review)) {
                    continue;
                }

                $answerId = isset($review['answer_id']) && is_numeric($review['answer_id'])
                    ? (int) $review['answer_id']
                    : 0;
                $answer = $answers->get($answerId);

                if ($answer === null) {
                    $validator->errors()->add(
                        "reviews.{$index}.answer_id",
                        'La respuesta seleccionada no pertenece al intento evaluado'
                    );
                    continue;
                }

                $questionType = (string) ($answer->examQuestion?->question?->question_type ?? '');
                if (! in_array($questionType, ['open', 'code'], true)) {
                    $validator->errors()->add(
                        "reviews.{$index}.answer_id",
                        'Solo puedes revisar reactivos abiertos o de programación'
                    );
                    continue;
                }

                if (isset($review['score']) && is_numeric($review['score'])) {
                    $score = (float) $review['score'];
                    $maxPoints = (float) ($answer->examQuestion?->points_weight ?? 0);

                    if ($score < 0 || $score > $maxPoints) {
                        $validator->errors()->add(
                            "reviews.{$index}.score",
                            "La calificación asignada debe estar entre 0 y {$maxPoints} puntos"
                        );
                    }
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
            'reviews.required' => 'El listado de reactivos para revisar es obligatorio',
            'reviews.array' => 'El listado de reactivos para revisar no tiene un formato válido',
            'reviews.min' => 'Selecciona al menos un reactivo para revisar',
            'reviews.*.answer_id.required' => 'El identificador de la respuesta es obligatorio',
            'reviews.*.answer_id.integer' => 'El identificador de la respuesta debe ser un número entero',
            'reviews.*.answer_id.distinct' => 'No puedes enviar la misma respuesta más de una vez',
            'reviews.*.answer_id.exists' => 'La respuesta seleccionada no existe en el sistema',
            'reviews.*.score.required' => 'La calificación asignada por el docente es obligatoria',
            'reviews.*.score.numeric' => 'La calificación debe ser un valor numérico válido',
            'reviews.*.score.min' => 'La calificación no puede ser menor a cero',
            'reviews.*.notes.string' => 'Las notas de retroalimentación deben ser una cadena de texto válida',
            'reviews.*.notes.max' => 'Las notas de retroalimentación no pueden superar los 1,000 caracteres',
        ];
    }
}
