<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        EloquentQuestionRepository.php
 * Ubicación:      app/Repositories/Eloquent/EloquentQuestionRepository.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, MPW-V2 §2.4 y §6.7)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Implementación Eloquent del repositorio del banco de
 *                 preguntas polimórfico con Eager Loading (with, withCount),
 *                 paginación en SQL, filtros avanzados, duplicación transaccional,
 *                 SoftDeletes y aislamiento multi-docente.
 * ============================================================================
 */

namespace App\Repositories\Eloquent;

use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repositorio Eloquent para la entidad Question y sus variantes polimórficas.
 *
 * @package App\Repositories\Eloquent
 */
class EloquentQuestionRepository implements QuestionRepositoryInterface
{
    /**
     * Obtiene los reactivos de un docente paginados en SQL con su variante polimórfica precargada.
     *
     * @param int $teacherId Identificador del docente autor.
     * @param int $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<Question>
     */
    public function paginateForTeacher(int $teacherId, int $perPage = 20): LengthAwarePaginator
    {
        return Question::query()
            ->forTeacher($teacherId)
            ->with(['questionable', 'paraphrases'])
            ->withCount(['paraphrases', 'childQuestions'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Obtiene los reactivos de un docente con filtros avanzados y búsqueda textual, paginados en SQL.
     *
     * @param int                  $teacherId Identificador del docente autor.
     * @param array<string, mixed> $filters   Filtros de consulta (search, question_type, subject_tag, paraphrase_status, sort_by).
     * @param int                  $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<Question>
     */
    public function paginateForTeacherWithFilters(
        int $teacherId,
        array $filters,
        int $perPage = 20
    ): LengthAwarePaginator {
        return Question::query()
            ->forTeacher($teacherId)
            ->search($filters['search'] ?? null)
            ->filterBy($filters)
            ->with(['questionable', 'paraphrases'])
            ->withCount(['paraphrases', 'childQuestions'])
            ->paginate($perPage);
    }

    /**
     * Obtiene la colección de asignaturas / materias únicas registradas por el docente.
     *
     * @param int $teacherId Identificador del docente autor.
     * @return Collection<int, string>
     */
    public function getDistinctSubjectsForTeacher(int $teacherId): Collection
    {
        return Question::query()
            ->forTeacher($teacherId)
            ->whereNotNull('subject_tag')
            ->where('subject_tag', '!=', '')
            ->distinct()
            ->orderBy('subject_tag')
            ->pluck('subject_tag');
    }

    /**
     * Busca un reactivo por su identificador precargando su variante polimórfica y paráfrasis.
     *
     * @param int $questionId Identificador del reactivo.
     * @return Question|null
     */
    public function findByIdWithVariant(int $questionId): ?Question
    {
        return Question::query()
            ->with(['questionable', 'paraphrases'])
            ->withCount(['paraphrases', 'childQuestions'])
            ->find($questionId);
    }

    /**
     * Obtiene candidatos aleatorios del banco de un docente para sorteo de apartado (RW-13/14).
     *
     * @param int             $teacherId    Identificador del docente autor.
     * @param string          $questionType Tipo de reactivo (multiple_choice, matching, open, code).
     * @param array<int, int> $excludedIds  Identificadores de reactivos ya utilizados en el examen.
     * @param int             $limit        Cantidad solicitada de reactivos.
     * @return Collection<int, Question>
     */
    public function getRandomCandidatesForSection(
        int $teacherId,
        string $questionType,
        array $excludedIds,
        int $limit
    ): Collection {
        return Question::query()
            ->forTeacher($teacherId)
            ->ofType($questionType)
            ->whereNotIn('id', $excludedIds)
            ->with(['questionable'])
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * Crea un reactivo raíz vinculándolo transaccionalmente con su variante polimórfica.
     *
     * @param int                  $teacherId    Identificador del docente autor.
     * @param array<string, mixed> $questionData Atributos del reactivo raíz.
     * @param Model                $questionable Instancia persistida de la variante polimórfica.
     * @return Question
     */
    public function createWithVariant(int $teacherId, array $questionData, Model $questionable): Question
    {
        return DB::transaction(function () use ($teacherId, $questionData, $questionable): Question {
            $question = Question::create([
                'teacher_id' => $teacherId,
                'subject_tag' => $questionData['subject_tag'] ?? null,
                'title' => (string) $questionData['title'],
                'stem' => (string) $questionData['stem'],
                'question_type' => (string) $questionData['question_type'],
                'default_points' => $questionData['default_points'],
                'default_time_seconds' => (int) $questionData['default_time_seconds'],
                'questionable_type' => $questionable::class,
                'questionable_id' => $questionable->getKey(),
                'parent_question_id' => $questionData['parent_question_id'] ?? null,
            ]);

            return $question->load(['questionable']);
        });
    }

    /**
     * Clona un reactivo y su variante polimórfica transaccionalmente.
     *
     * @param Question $question Reactivo canónico a duplicar.
     * @return Question
     */
    public function duplicate(Question $question): Question
    {
        return DB::transaction(function () use ($question): Question {
            $question->loadMissing('questionable');

            $clonedQuestionable = null;
            if ($question->questionable !== null) {
                $clonedQuestionable = $question->questionable->replicate();
                $clonedQuestionable->save();
            }

            $clonedQuestion = $question->replicate();
            $clonedQuestion->title = "{$question->title} (Copia)";

            if ($clonedQuestionable !== null) {
                $clonedQuestion->questionable_id = $clonedQuestionable->getKey();
                $clonedQuestion->questionable_type = $clonedQuestionable::class;
            }

            $clonedQuestion->save();

            return $clonedQuestion->load(['questionable', 'paraphrases']);
        });
    }

    /**
     * Elimina lógicamente un reactivo (Soft Delete).
     *
     * @param Question $question Reactivo a eliminar.
     * @return bool
     */
    public function destroySafely(Question $question): bool
    {
        return (bool) $question->delete();
    }

    /**
     * Registra una nueva paráfrasis anti-copia asociada a un reactivo canónico.
     *
     * @param Question $question Reactivo canónico.
     * @param string   $stem     Redacción alterna de la pregunta.
     * @return QuestionParaphrase
     */
    public function storeParaphrase(Question $question, string $stem): QuestionParaphrase
    {
        return QuestionParaphrase::create([
            'question_id' => $question->id,
            'stem' => $stem,
        ]);
    }
}
