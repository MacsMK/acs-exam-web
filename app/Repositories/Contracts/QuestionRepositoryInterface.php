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
 * Versión:        1.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Contrato del repositorio del banco de preguntas polimórfico
 *                 y sus paráfrasis anti-copia.
 * ============================================================================
 */

namespace App\Repositories\Contracts;

use App\Models\Question;
use App\Models\QuestionParaphrase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
     * Registra una nueva paráfrasis anti-copia asociada a un reactivo canónico.
     *
     * @param Question $question Reactivo canónico.
     * @param string   $stem     Redacción alterna de la pregunta.
     * @return QuestionParaphrase
     */
    public function storeParaphrase(Question $question, string $stem): QuestionParaphrase;
}
