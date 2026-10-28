<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AttemptRepositoryInterface.php
 * Ubicación:      app/Repositories/Contracts/AttemptRepositoryInterface.php
 * Requerimiento:  RF-EXA / RF-CAL (Aplicación y Calificación, MPW-V2 §2.4 y §6.7)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Contrato del repositorio de intentos de examen y respuestas
 *                 de alumnos (StudentAttempt y StudentAnswer).
 * ============================================================================
 */

namespace App\Repositories\Contracts;

use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato de persistencia y consulta para intentos de examen de alumnos.
 *
 * @package App\Repositories\Contracts
 */
interface AttemptRepositoryInterface
{
    /**
     * Busca el intento de un alumno para un examen específico con relaciones precargadas.
     *
     * @param int $studentId Identificador del alumno.
     * @param int $examId    Identificador del examen.
     * @return StudentAttempt|null
     */
    public function findForStudentAndExam(int $studentId, int $examId): ?StudentAttempt;

    /**
     * Busca un intento por su identificador con examen, grupo, alumno y respuestas precargadas.
     *
     * @param int $attemptId Identificador del intento.
     * @return StudentAttempt|null
     */
    public function findByIdWithDetails(int $attemptId): ?StudentAttempt;

    /**
     * Obtiene o crea de forma idempotente el intento en curso de un alumno para un examen.
     *
     * @param int             $examId           Identificador del examen.
     * @param int             $studentId        Identificador del alumno.
     * @param int             $groupId          Identificador del grupo del alumno.
     * @param array<int, int> $questionSequence Secuencia de ids de acs_exam_question.
     * @return StudentAttempt
     */
    public function firstOrCreateAttempt(
        int $examId,
        int $studentId,
        int $groupId,
        array $questionSequence
    ): StudentAttempt;

    /**
     * Registra o actualiza la respuesta de un alumno a un reactivo y avanza el índice activo.
     *
     * @param StudentAttempt       $attempt        Intento activo del alumno.
     * @param int                  $examQuestionId Identificador del reactivo dentro del examen.
     * @param array<string, mixed> $attributes     Atributos calificados y payload de respuesta.
     * @return StudentAnswer
     */
    public function saveAnswer(StudentAttempt $attempt, int $examQuestionId, array $attributes): StudentAnswer;

    /**
     * Marca un intento como enviado (submitted) almacenando sus puntajes calculados.
     *
     * @param StudentAttempt $attempt          Intento a finalizar.
     * @param float          $objectiveScore   Puntaje de reactivos objetivos.
     * @param float          $aiEstimatedScore Puntaje estimado por IA.
     * @param float|null     $finalScore       Calificación final (null si hay pendientes de IA).
     * @param int            $pendingAiCount   Cantidad de respuestas pendientes de evaluación IA.
     * @return StudentAttempt
     */
    public function markSubmitted(
        StudentAttempt $attempt,
        float $objectiveScore,
        float $aiEstimatedScore,
        ?float $finalScore,
        int $pendingAiCount
    ): StudentAttempt;

    /**
     * Incrementa el contador de incidentes y cancela el intento si supera el límite permitido.
     *
     * @param StudentAttempt $attempt             Intento en curso.
     * @param int            $maxAllowedIncidents Máximo de incidentes tolerados por el examen.
     * @return StudentAttempt
     */
    public function recordIncident(StudentAttempt $attempt, int $maxAllowedIncidents): StudentAttempt;

    /**
     * Obtiene los intentos calificados de un alumno paginados en SQL con el examen precargado.
     *
     * @param int $studentId Identificador del alumno.
     * @param int $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<StudentAttempt>
     */
    public function paginateGradedForStudent(int $studentId, int $perPage = 20): LengthAwarePaginator;

    /**
     * Obtiene todos los intentos calificados de un alumno con el examen precargado (estadísticas).
     *
     * @param int $studentId Identificador del alumno.
     * @return Collection<int, StudentAttempt>
     */
    public function getGradedForStudent(int $studentId): Collection;

    /**
     * Obtiene los intentos de un examen paginados en SQL con alumno y grupo precargados.
     *
     * @param int $examId  Identificador del examen.
     * @param int $perPage Registros por página en SQL.
     * @return LengthAwarePaginator<StudentAttempt>
     */
    public function paginateAttemptsForExam(int $examId, int $perPage = 20): LengthAwarePaginator;
}
