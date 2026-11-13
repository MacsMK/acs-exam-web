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
 * Versión:        2.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Contrato del repositorio del banco de preguntas polimórfico,
 *                 catálogo normalizado de materias, similitud fuzzy/fusión,
 *                 linaje Copy-On-Write, paráfrasis anti-copia y soft deletes.
 * ============================================================================
 */

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Models\Subject;

/**
 * Contrato de persistencia y consulta para la entidad Question, Subject y sus variantes.
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
     * @param array<string, mixed> $filters   Filtros de consulta (search, question_type, subject_id, subject_tag, paraphrase_status, sort_by).
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
     * Obtiene candidatos aleatorios del banco de un docente para sorteo de apartado (RW-13/14),
     * excluyendo los IDs ya incluidos y todo su linaje Copy-On-Write.
     *
     * @param int             $teacherId     Identificador del docente autor.
     * @param string          $questionType  Tipo de reactivo (multiple_choice, matching, open, code).
     * @param array<int, int> $excludedIds   Identificadores de reactivos ya utilizados en el examen.
     * @param int             $limit         Cantidad solicitada de reactivos.
     * @param string|null     $subjectFilter Nombre de materia opcional para filtrar el sorteo.
     * @param int|null        $subjectId     Identificador de materia normalizada opcional.
     * @return Collection<int, Question>
     */
    public function getRandomCandidatesForSection(
        int $teacherId,
        string $questionType,
        array $excludedIds,
        int $limit,
        ?string $subjectFilter = null,
        ?int $subjectId = null
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

    /**
     * Resuelve o crea de manera idempotente la materia normalizada del docente.
     *
     * @param int         $teacherId Identificador del docente propietario.
     * @param string|null $rawName   Nombre sin procesar ingresado por el docente.
     * @return Subject|null
     */
    public function resolveOrCreateSubject(int $teacherId, ?string $rawName): ?Subject;

    /**
     * Lista las materias del docente con conteo de reactivos y sugerencias de posibles
     * duplicados por similitud fuzzy.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return Collection<int, Subject>
     */
    public function getSubjectsWithStatsForTeacher(int $teacherId): Collection;

    /**
     * Busca materias del docente con alta similitud léxica frente a un nombre candidato.
     *
     * @param int      $teacherId        Identificador del docente propietario.
     * @param string   $candidateName    Nombre candidato a comparar.
     * @param int|null $excludeSubjectId Identificador de materia a excluir de la búsqueda.
     * @return Collection<int, Subject>
     */
    public function findSimilarSubjects(
        int $teacherId,
        string $candidateName,
        ?int $excludeSubjectId = null
    ): Collection;

    /**
     * Fusiona transaccionalmente múltiples materias del docente hacia una materia destino,
     * reasignando reactivos y apartados de examen y eliminando las materias absorbidas.
     *
     * @param int             $teacherId        Identificador del docente propietario.
     * @param array<int, int> $sourceSubjectIds Identificadores de materias origen a fusionar.
     * @param string          $targetName       Nombre legible destino.
     * @return Subject
     */
    public function mergeTeacherSubjects(int $teacherId, array $sourceSubjectIds, string $targetName): Subject;

    /**
     * Calcula los totales agregados del banco de reactivos de un docente en SQL.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return array{
     *     total_active: int,
     *     with_paraphrases: int,
     *     without_paraphrases: int,
     *     by_type: array{multiple_choice: int, matching: int, open: int, code: int},
     *     subjects_count: int,
     *     duplicate_subject_groups_count: int
     * }
     */
    public function getBankSummaryMetrics(int $teacherId): array;

    /**
     * Indica si el reactivo está vinculado a algún examen en acs_exam_question.
     *
     * @param Question $question Reactivo a consultar.
     * @return bool
     */
    public function hasExamLinks(Question $question): bool;

    /**
     * Indica si el reactivo está vinculado a un examen publicado o que ya tiene intentos registrados.
     *
     * @param Question $question Reactivo a consultar.
     * @return bool
     */
    public function hasActiveOrPublishedExamLinks(Question $question): bool;

    /**
     * Obtiene todos los IDs de la familia de versionado Copy-On-Write vinculada al reactivo.
     *
     * @param int $questionId Identificador del reactivo semilla.
     * @return array<int, int>
     */
    public function getLineageQuestionIds(int $questionId): array;

    /**
     * Busca una paráfrasis específica perteneciente al reactivo indicado.
     *
     * @param Question $question     Reactivo canónico propietario.
     * @param int      $paraphraseId Identificador de la paráfrasis.
     * @return QuestionParaphrase|null
     */
    public function findParaphraseForQuestion(Question $question, int $paraphraseId): ?QuestionParaphrase;

    /**
     * Elimina una paráfrasis anti-copia.
     *
     * @param QuestionParaphrase $paraphrase Paráfrasis a eliminar.
     * @return bool
     */
    public function deleteParaphrase(QuestionParaphrase $paraphrase): bool;
}
