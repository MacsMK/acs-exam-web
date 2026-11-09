<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamAssemblyService.php
 * Ubicación:   app/Services/ExamAssemblyService.php
 * Descripción: Sorteo de reactivos, propuestas provisionales y aceptación
 *              de apartados del examen.
 * ============================================================================
 */

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamAssemblyService
{
    /**
     * Genera una propuesta sin guardar apartados ni reactivos.
     *
     * @return array<int, int> IDs de preguntas propuestas.
     */
    public function generateProposal(
        Exam $exam,
        string $questionType,
        int $quantity,
        ?string $subjectTag = null
    ): array {
        $alreadyUsedIds = ExamQuestion::where('exam_id', $exam->id)
            ->pluck('question_id');

        $query = Question::where('teacher_id', $exam->teacher_id)
            ->where('question_type', $questionType)
            ->whereNotIn('id', $alreadyUsedIds);

        if ($subjectTag !== null && $subjectTag !== '') {
            $query->where('subject_tag', $subjectTag);
        }

        $candidates = $query
            ->inRandomOrder()
            ->limit($quantity)
            ->pluck('id');

        if ($candidates->count() < $quantity) {
            throw ValidationException::withMessages([
                'requested_quantity' => [
                    "El banco solo tiene {$candidates->count()} reactivos disponibles para los filtros seleccionados; se solicitaron {$quantity}.",
                ],
            ]);
        }

        return $candidates->all();
    }

    /**
     * Acepta una propuesta y guarda el apartado con sus reactivos.
     *
     * @param array<int, int> $questionIds
     */
    public function acceptProposal(
        Exam $exam,
        string $title,
        string $questionType,
        array $questionIds,
        ?string $subjectTag = null
    ): ExamSection {
        if ($exam->is_published) {
            throw ValidationException::withMessages([
                'exam' => ['No se puede modificar un examen publicado.'],
            ]);
        }

        if ($questionIds === [] || count($questionIds) !== count(array_unique($questionIds))) {
            throw ValidationException::withMessages([
                'question_ids' => ['La propuesta está vacía o contiene preguntas repetidas.'],
            ]);
        }

        return DB::transaction(function () use (
            $exam,
            $title,
            $questionType,
            $questionIds,
            $subjectTag
        ): ExamSection {
            // Serializa las aceptaciones concurrentes del mismo examen.
            $lockedExam = Exam::whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedExam->is_published) {
                throw ValidationException::withMessages([
                    'exam' => ['No se puede modificar un examen publicado.'],
                ]);
            }

            $alreadyUsedIds = ExamQuestion::where('exam_id', $exam->id)
                ->pluck('question_id');

            $query = Question::where('teacher_id', $lockedExam->teacher_id)
                ->where('question_type', $questionType)
                ->whereIn('id', $questionIds)
                ->whereNotIn('id', $alreadyUsedIds);

            if ($subjectTag !== null && $subjectTag !== '') {
                $query->where('subject_tag', $subjectTag);
            }

            $questions = $query->get()->keyBy('id');

            if ($questions->count() !== count($questionIds)) {
                throw ValidationException::withMessages([
                    'question_ids' => [
                        'La propuesta ya no está disponible. Genera una nueva antes de continuar.',
                    ],
                ]);
            }

            $section = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => $title,
                'question_type' => $questionType,
                'requested_quantity' => count($questionIds),
                'sort_order' => $lockedExam->sections()->count(),
            ]);

            foreach ($questionIds as $index => $questionId) {
                $question = $questions->get($questionId);

                ExamQuestion::create([
                    'exam_id' => $exam->id,
                    'exam_section_id' => $section->id,
                    'question_id' => $question->id,
                    'time_limit_seconds' => $question->default_time_seconds,
                    'points_weight' => $question->default_points,
                    'sort_order' => $index,
                ]);
            }

            return $section;
        });
    }

    /**
     * Sortea y guarda preguntas para un apartado ya creado.
     * Se conserva para mantener compatibilidad con el flujo existente.
     */
    public function drawQuestionsForSection(ExamSection $section): void
    {
        $exam = $section->exam;

        $alreadyUsedIds = ExamQuestion::where('exam_id', $exam->id)
            ->pluck('question_id');

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
     * Sustituye un reactivo por otro disponible del mismo tipo.
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
                'question_id' => [
                    'No hay otro reactivo del mismo tipo disponible en el banco para sustituir este.',
                ],
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
     * Determina el orden de las preguntas para el alumno.
     *
     * @return array<int, int>
     */
    public function buildQuestionSequence(Exam $exam): array
    {
        $examQuestions = $exam->examQuestions()
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        if ($exam->randomize_question_order) {
            shuffle($examQuestions);
        }

        return $examQuestions;
    }
}