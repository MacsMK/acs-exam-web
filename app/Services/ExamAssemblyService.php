<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamAssemblyService.php
 * Ubicación:   app/Services/ExamAssemblyService.php
 * Descripción: Sorteo aleatorio del banco de preguntas para un apartado
 *              (RW-13) y nuevo sorteo de un reactivo puntual (RW-14).
 * ============================================================================
 */

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Question;
use Illuminate\Validation\ValidationException;

class ExamAssemblyService
{
    /**
     * Sortea requested_quantity preguntas del tipo del apartado, del banco
     * del propio docente, evitando repetir reactivos ya usados en el
     * examen, y las acepta como acs_exam_question.
     */
    public function drawQuestionsForSection(ExamSection $section): void
    {
        $exam = $section->exam;

        $alreadyUsedIds = ExamQuestion::where('exam_id', $exam->id)->pluck('question_id');

        $candidates = Question::where('teacher_id', $exam->teacher_id)
            ->where('question_type', $section->question_type)
            ->whereNotIn('id', $alreadyUsedIds)
            ->inRandomOrder()
            ->limit($section->requested_quantity)
            ->get();

        if ($candidates->count() < $section->requested_quantity) {
            throw ValidationException::withMessages([
                'requested_quantity' => [
                    "El banco solo tiene {$candidates->count()} reactivos de tipo {$section->question_type} disponibles, se pidieron {$section->requested_quantity}.",
                ],
            ]);
        }

        foreach ($candidates->values() as $index => $question) {
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'exam_section_id' => $section->id,
                'question_id' => $question->id,
                'time_limit_seconds' => $question->default_time_seconds,
                'points_weight' => $question->default_points,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Nuevo sorteo de un reactivo puntual dentro de su apartado (RW-14):
     * lo sustituye por otro candidato del mismo tipo que no esté ya en el
     * examen.
     */
    public function reshuffleQuestion(ExamQuestion $examQuestion): ExamQuestion
    {
        $section = $examQuestion->examSection;
        $exam = $examQuestion->exam;

        $alreadyUsedIds = ExamQuestion::where('exam_id', $exam->id)
            ->where('id', '!=', $examQuestion->id)
            ->pluck('question_id')
            ->push($examQuestion->question_id);

        $replacement = Question::where('teacher_id', $exam->teacher_id)
            ->where('question_type', $section->question_type)
            ->whereNotIn('id', $alreadyUsedIds)
            ->inRandomOrder()
            ->first();

        if (! $replacement) {
            throw ValidationException::withMessages([
                'question_id' => ['No hay otro reactivo del mismo tipo disponible en el banco para sustituir este.'],
            ]);
        }

        $examQuestion->update([
            'question_id' => $replacement->id,
            'time_limit_seconds' => $replacement->default_time_seconds,
            'points_weight' => $replacement->default_points,
        ]);

        return $examQuestion->fresh();
    }

    /**
     * Arma el orden de preguntas que verá un alumno: aleatorio o fijo
     * según randomize_question_order (RW-20).
     *
     * @return array<int, int> ids de acs_exam_question en el orden a mostrar
     */
    public function buildQuestionSequence(Exam $exam): array
    {
        $examQuestions = $exam->examQuestions()->orderBy('sort_order')->pluck('id')->all();

        if ($exam->randomize_question_order) {
            shuffle($examQuestions);
        }

        return $examQuestions;
    }
}
