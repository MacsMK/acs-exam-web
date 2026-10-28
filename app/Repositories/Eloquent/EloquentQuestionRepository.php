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
 * Versión:        1.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Implementación Eloquent del repositorio del banco de
 *                 preguntas polimórfico con Eager Loading (with, withCount),
 *                 paginación en SQL y aislamiento multi-docente.
 * ============================================================================
 */

namespace App\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Repositories\Contracts\QuestionRepositoryInterface;

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
