<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ExamAssemblyService.php
 * Ubicación:   app/Services/ExamAssemblyService.php
 * Descripción: Creación de examen, sorteo aleatorio del banco de preguntas
 *              para un apartado (RW-13) filtrado por tipo, asignatura y linaje
 *              Copy-On-Write, y nuevo sorteo puntual (RW-14) transaccional.
 * ============================================================================
 */

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Repositories\Contracts\QuestionRepositoryInterface;

class ExamAssemblyService
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
    ) {
    }

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

            if (! empty($data['group_ids']) && is_array($data['group_ids'])) {
                $ownedGroupIds = Group::where('teacher_id', $teacher->id)
                    ->whereIn('id', array_map('intval', $data['group_ids']))
                    ->pluck('id')
                    ->all();

                $exam->groups()->sync($ownedGroupIds);
            }

            return $exam;
        });
    }

    /**
     * Crea un apartado con su filtro opcional de materia normalizada y sortea
     * sus reactivos dentro de una transacción.
     *
     * @param array<string, mixed> $data
     */
    public function createSectionAndDraw(Exam $exam, array $data): ExamSection
    {
        if ($exam->is_published) {
            throw ValidationException::withMessages([
                'exam' => ['No se puede modificar un examen publicado.'],
            ]);
        }

        return DB::transaction(function () use ($exam, $data): ExamSection {
            $cleanSubject = Subject::cleanDisplayName($data['subject_tag'] ?? null);
            $subject = $cleanSubject !== null
                ? $this->questionRepository->resolveOrCreateSubject((int) $exam->teacher_id, $cleanSubject)
                : null;

            $section = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => $data['title'],
                'question_type' => $data['question_type'],
                'subject_tag' => $subject?->name ?? $cleanSubject,
                'subject_id' => $subject?->id,
                'requested_quantity' => (int) $data['requested_quantity'],
                'sort_order' => $exam->sections()->count(),
            ]);

            $section->setRelation('exam', $exam);
            $this->drawQuestionsForSection($section);

            return $section;
        });
    }

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
            ->pluck('question_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $candidates = $this->questionRepository->getRandomCandidatesForSection(
            (int) $exam->teacher_id,
            $questionType,
            $alreadyUsedIds,
            $quantity,
            $subjectTag,
            null
        );

        if ($candidates->count() < $quantity) {
            throw ValidationException::withMessages([
                'requested_quantity' => [
                    "El banco solo tiene {$candidates->count()} reactivos disponibles para los filtros seleccionados; se solicitaron {$quantity}.",
                ],
            ]);
        }

        return $candidates->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Acepta una propuesta validando propiedad docente, tipo de reactivo [MEDIA-02],
     * linaje Copy-On-Write y persistiendo la materia normalizada del apartado.
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

        $questionIds = array_values(array_map('intval', $questionIds));
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
            $subjectTag,
        ): ExamSection {
            $questions = Question::where('teacher_id', $exam->teacher_id)
                ->where('question_type', $questionType)
                ->whereIn('id', $questionIds)
                ->get()
                ->keyBy('id');

            if ($questions->count() !== count($questionIds)) {
                throw ValidationException::withMessages([
                    'question_ids' => ['Algunos reactivos ya no están disponibles o no corresponden al tipo del apartado.'],
                ]);
            }

            $alreadyUsed = ExamQuestion::where('exam_id', $exam->id)
                ->whereIn('question_id', $questionIds)
                ->exists();

            if ($alreadyUsed) {
                throw ValidationException::withMessages([
                    'question_ids' => ['Uno o más reactivos ya forman parte del examen.'],
                ]);
            }

            $cleanSubject = Subject::cleanDisplayName($subjectTag);
            $subject = $cleanSubject !== null
                ? $this->questionRepository->resolveOrCreateSubject((int) $exam->teacher_id, $cleanSubject)
                : null;

            $section = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => $title,
                'question_type' => $questionType,
                'subject_tag' => $subject?->name ?? $cleanSubject,
                'subject_id' => $subject?->id,
                'requested_quantity' => count($questionIds),
                'sort_order' => $exam->sections()->count(),
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
     * Sortea requested_quantity preguntas del tipo y materia del apartado del banco
     * del propio docente, evitando repetir reactivos o linajes COW ya usados.
     */
    public function drawQuestionsForSection(ExamSection $section): void
    {
        $section->loadMissing('exam');
        $exam = $section->exam;

        $alreadyUsedIds = ExamQuestion::where('exam_id', $exam->id)
            ->pluck('question_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $candidates = $this->questionRepository->getRandomCandidatesForSection(
            (int) $exam->teacher_id,
            (string) $section->question_type,
            $alreadyUsedIds,
            (int) $section->requested_quantity,
            $section->subject_tag,
            $section->subject_id
        );

        if ($candidates->count() < $section->requested_quantity) {
            throw ValidationException::withMessages([
                'requested_quantity' => [
                    "El banco solo tiene {$candidates->count()} reactivos de tipo {$section->question_type} disponibles para los filtros seleccionados; se pidieron {$section->requested_quantity}.",
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
     * lo sustituye por otro candidato del mismo tipo y materia que no esté ya en el
     * examen ni comparta linaje Copy-On-Write.
     */
    public function reshuffleQuestion(ExamQuestion $examQuestion): ExamQuestion
    {
        $examQuestion->loadMissing(['examSection', 'exam']);
        $section = $examQuestion->examSection;
        $exam = $examQuestion->exam;

        $alreadyUsedIds = ExamQuestion::where('exam_id', $exam->id)
            ->pluck('question_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $replacement = $this->questionRepository->getRandomCandidatesForSection(
            (int) $exam->teacher_id,
            (string) $section->question_type,
            $alreadyUsedIds,
            1,
            $section->subject_tag,
            $section->subject_id
        )->first();

        if (! $replacement) {
            throw ValidationException::withMessages([
                'question_id' => ['No hay otro reactivo del mismo tipo y materia disponible en el banco para sustituir este.'],
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
