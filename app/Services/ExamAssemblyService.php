<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamAssemblyService.php
 * Ubicación:   app/Services/ExamAssemblyService.php
 * Descripción: Creación de examen, sorteo aleatorio del banco de preguntas
 *              para un apartado (RW-13) y nuevo sorteo de un reactivo puntual
 *              (RW-14) con integridad transaccional.
 * ============================================================================
 */

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExamAssemblyService
{
    /**
     * Crea un examen en borrador y sincroniza sus grupos asignados dentro de
     * una transacción.
     *
     * @param array<string, mixed> $data
     */
    public function createExam(User $teacher, array $data): Exam
    {
        return DB::transaction(static function () use ($teacher, $data): Exam {
            $exam = Exam::create([
                'teacher_id' => $teacher->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'grading_scale' => $data['grading_scale'],
                'randomize_question_order' => (bool) ($data['randomize_question_order'] ?? true),
                'result_visibility_mode' => $data['result_visibility_mode'],
                'require_webcam' => (bool) ($data['require_webcam'] ?? false),
                'max_allowed_incidents' => $data['max_allowed_incidents'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'share_token' => Str::random(32),
                'is_published' => false,
            ]);

            if (! empty($data['group_ids'])) {
                $exam->groups()->sync($data['group_ids']);
            }

            return $exam;
        });
    }

    /**
     * Crea un apartado y sortea sus reactivos dentro de una transacción.
     *
     * @param array<string, mixed> $data
     */
    public function createSectionAndDraw(Exam $exam, array $data): ExamSection
    {
        return DB::transaction(function () use ($exam, $data): ExamSection {
            $section = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => $data['title'],
                'question_type' => $data['question_type'],
                'requested_quantity' => (int) $data['requested_quantity'],
                'sort_order' => $exam->sections()->count(),
            ]);

            $section->setRelation('exam', $exam);
            $this->drawQuestionsForSection($section);

            return $section;
        });
    }

    /**
     * Sortea requested_quantity preguntas del tipo del apartado, del banco
     * del propio docente, evitando repetir reactivos ya usados en el
     * examen, y las acepta como acs_exam_question.
     */
    public function drawQuestionsForSection(ExamSection $section): void
    {
        $section->loadMissing('exam');
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

        DB::transaction(static function () use ($candidates, $exam, $section): void {
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
        });
    }

    /**
     * Nuevo sorteo de un reactivo puntual dentro de su apartado (RW-14):
     * lo sustituye por otro candidato del mismo tipo que no esté ya en el
     * examen.
     */
    public function reshuffleQuestion(ExamQuestion $examQuestion): ExamQuestion
    {
        $examQuestion->loadMissing(['examSection', 'exam']);
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

        return DB::transaction(static function () use ($examQuestion, $replacement): ExamQuestion {
            $examQuestion->update([
                'question_id' => $replacement->id,
                'time_limit_seconds' => $replacement->default_time_seconds,
                'points_weight' => $replacement->default_points,
            ]);

            return $examQuestion->fresh();
        });
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
