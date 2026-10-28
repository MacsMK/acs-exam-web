<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamRepositoryInterface.php
 * Ubicación:      app/Repositories/Contracts/ExamRepositoryInterface.php
 * Requerimiento:  RF-EXA (Armado y Aplicación de Exámenes, MPW-V2 §2.4 y §6.7)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Contrato del repositorio de exámenes para desacoplar las
 *                 operaciones de persistencia y consulta Eloquent de la
 *                 lógica de negocio en controladores y servicios.
 * ============================================================================
 */

namespace App\Repositories\Contracts;

use App\Models\Exam;
use App\Models\ExamSection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato de persistencia y consulta para la entidad Exam y sus apartados.
 *
 * @package App\Repositories\Contracts
 */
interface ExamRepositoryInterface
{
    /**
     * Obtiene la lista paginada de exámenes creados por un docente con conteos precargados.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @param int $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<Exam>
     */
    public function paginateForTeacher(int $teacherId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtiene la colección de exámenes creados por un docente con relaciones y conteos en SQL.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return Collection<int, Exam>
     */
    public function getForTeacher(int $teacherId): Collection;

    /**
     * Obtiene los exámenes publicados asignados a los grupos donde está inscrito el alumno.
     *
     * @param int $studentId Identificador del alumno.
     * @return Collection<int, Exam>
     */
    public function getPublishedForStudent(int $studentId): Collection;

    /**
     * Busca un examen por su identificador precargando apartados, reactivos y variantes (MPW-V2 §6.7).
     *
     * @param int $examId Identificador del examen.
     * @return Exam|null
     */
    public function findByIdWithQuestions(int $examId): ?Exam;

    /**
     * Obtiene los exámenes publicados y vigentes dentro de su ventana horaria para un grupo (MPW-V2 §6.7).
     *
     * @param int $groupId Identificador del grupo.
     * @return Collection<int, Exam>
     */
    public function getActiveExamsForGroup(int $groupId): Collection;

    /**
     * Crea un examen para un docente y sincroniza atómicamente los grupos asignados.
     *
     * @param int                  $teacherId Identificador del docente propietario.
     * @param array<string, mixed> $data      Atributos validados del examen.
     * @param array<int, int>      $groupIds  Identificadores de los grupos asignados.
     * @return Exam
     */
    public function createForTeacher(int $teacherId, array $data, array $groupIds = []): Exam;

    /**
     * Crea un apartado dentro del examen calculando su orden de aparición.
     *
     * @param Exam                 $exam Examen al que pertenece el apartado.
     * @param array<string, mixed> $data Datos validados del apartado.
     * @return ExamSection
     */
    public function createSection(Exam $exam, array $data): ExamSection;

    /**
     * Marca un examen como publicado.
     *
     * @param Exam $exam Instancia del examen a publicar.
     * @return Exam
     */
    public function publish(Exam $exam): Exam;

    /**
     * Calcula en SQL las métricas de avance (intentos terminales vs total de alumnos asignados).
     *
     * @param Exam $exam Examen del cual se consulta el progreso.
     * @return array{answered_count: int, total_count: int}
     */
    public function getProgressStats(Exam $exam): array;

    /**
     * Registra de forma transaccional un intento de examen enviado por un alumno (MPW-V2 §6.7).
     *
     * @param int                  $examId    Identificador del examen.
     * @param int                  $studentId Identificador del alumno.
     * @param array<string, mixed> $answers   Respuestas asociadas al intento.
     * @return Exam
     */
    public function saveExamAttempt(int $examId, int $studentId, array $answers): Exam;
}
