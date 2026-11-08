<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionRepositoryInterface.php
 * Ubicación:      app/Repositories/Contracts/QuestionRepositoryInterface.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, MPW-V2 §2.4 y §6.7)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Contrato del repositorio del banco de preguntas polimórfico,
 *                 consultas filtradas, paráfrasis anti-copia, duplicación
 *                 segura y soft deletes.
 * ============================================================================
 */

namespace App\Repositories\Contracts;

use App\Models\Question;
use App\Models\QuestionParaphrase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Contrato de persistencia y consulta para la entidad Question y sus variantes.
 *
 * @package App\Repositories\Contracts
 */
interface QuestionRepositoryInterface
{
    /**
     * Obtiene los reactivos de un docente paginados en SQL con su variante polimórfica precargada.
     *
     * @param int $teacherId Identificador del docente autor.
     * @param int $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<Question>
     */
    public function paginateForTeacher(int $teacherId, int $perPage = 20): LengthAwarePaginator;

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
    ): LengthAwarePaginator;

    /**
     * Obtiene la colección de asignaturas / materias únicas registradas por el docente.
     *
     * @param int $teacherId Identificador del docente autor.
     * @return Collection<int, string>
     */
    public function getDistinctSubjectsForTeacher(int $teacherId): Collection;

    /**
     * Busca un reactivo por su identificador precargando su variante polimórfica y paráfrasis.
     *
     * @param int $questionId Identificador del reactivo.
     * @return Question|null
     */
    public function findByIdWithVariant(int $questionId): ?Question;

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
    ): Collection;

    /**
     * Crea un reactivo raíz vinculándolo transaccionalmente con su variante polimórfica.
     *
     * @param int                  $teacherId    Identificador del docente autor.
     * @param array<string, mixed> $questionData Atributos del reactivo raíz.
     * @param Model                $questionable Instancia persistida de la variante polimórfica.
     * @return Question
     */
    public function createWithVariant(int $teacherId, array $questionData, Model $questionable): Question;

    /**
     * Clona un reactivo y su variante polimórfica transaccionalmente.
     *
     * @param Question $question Reactivo canónico a duplicar.
     * @return Question
     */
    public function duplicate(Question $question): Question;

    /**
     * Elimina lógicamente un reactivo (Soft Delete).
     *
     * @param Question $question Reactivo a eliminar.
     * @return bool
     */
    public function destroySafely(Question $question): bool;

    /**
     * Registra una nueva paráfrasis anti-copia asociada a un reactivo canónico.
     *
     * @param Question $question Reactivo canónico.
     * @param string   $stem     Redacción alterna de la pregunta.
     * @return QuestionParaphrase
     */
    public function storeParaphrase(Question $question, string $stem): QuestionParaphrase;
}
