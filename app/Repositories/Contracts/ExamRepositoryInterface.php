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
 * Versión:        1.1.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Contrato del repositorio de exámenes para desacoplar las
 *                 operaciones de persistencia, KPIs, armado, monitoreo y
 *                 recoteje de la lógica de negocio en controladores y servicios.
 * ============================================================================
 */

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\StudentAttempt;

/**
 * Contrato de persistencia y consulta para la entidad Exam y sus apartados.
 *
 * @package App\Repositories\Contracts
 */
interface ExamRepositoryInterface
{
    /**
     * Obtiene la lista paginada de exámenes creados por un docente con filtros,
     * conteos y suma de ponderaciones precargados en SQL.
     *
     * @param int                  $teacherId Identificador del docente propietario.
     * @param int                  $perPage   Registros por página en SQL.
     * @param array<string, mixed> $filters   Filtros opcionales (search, status, group_id).
     * @return LengthAwarePaginator<Exam>
     */
    public function paginateForTeacher(int $teacherId, int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Obtiene la colección de exámenes creados por un docente con relaciones y conteos en SQL.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return Collection<int, Exam>
     */
    public function getForTeacher(int $teacherId): Collection;

    /**
     * Calcula los indicadores agregados en SQL para el tablero de exámenes del docente.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return array{
     *     total: int,
     *     drafts: int,
     *     scheduled: int,
     *     published_active: int,
     *     closed: int,
     *     pending_recoteje_exams: int
     * }
     */
    public function getTeacherExamStats(int $teacherId): array;

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
     * Busca un examen propiedad del docente precargando grupos, apartados ordenados,
     * reactivos con materia/paráfrasis, conteos y suma de ponderaciones sin N+1.
     *
     * @param int $examId    Identificador del examen.
     * @param int $teacherId Identificador del docente propietario.
     * @return Exam|null
     */
    public function findForTeacherWithDetails(int $examId, int $teacherId): ?Exam;

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
     * Actualiza transaccionalmente los metadatos de un examen y sincroniza sus grupos.
     *
     * @param Exam                 $exam     Examen a actualizar.
     * @param array<string, mixed> $data     Atributos validados a persistir.
     * @param array<int, int>      $groupIds Identificadores de grupos asignados.
     * @return Exam
     */
    public function updateExam(Exam $exam, array $data, array $groupIds): Exam;

    /**
     * Elimina de forma transaccional un examen en borrador junto con sus pivotes,
     * reactivos asignados y apartados.
     *
     * @param Exam $exam Examen en borrador a eliminar.
     * @return bool
     */
    public function deleteDraftExam(Exam $exam): bool;

    /**
     * Crea un apartado dentro del examen calculando su orden de aparición.
     *
     * @param Exam                 $exam Examen al que pertenece el apartado.
     * @param array<string, mixed> $data Datos validados del apartado.
     * @return ExamSection
     */
    public function createSection(Exam $exam, array $data): ExamSection;

    /**
     * Actualiza los metadatos de un apartado existente del examen.
     *
     * @param ExamSection          $section Apartado a modificar.
     * @param array<string, mixed> $data    Atributos validados del apartado.
     * @return ExamSection
     */
    public function updateSection(ExamSection $section, array $data): ExamSection;

    /**
     * Elimina transaccionalmente un apartado y sus reactivos asignados, reindexando
     * el campo sort_order de los apartados restantes del examen.
     *
     * @param ExamSection $section Apartado a eliminar.
     * @return void
     */
    public function deleteSection(ExamSection $section): void;

    /**
     * Remueve un reactivo asignado de su apartado dentro de una transacción y
     * reindexa el campo sort_order de los reactivos restantes en esa sección.
     *
     * @param ExamQuestion $examQuestion Vínculo reactivo-examen a eliminar.
     * @return void
     */
    public function removeExamQuestion(ExamQuestion $examQuestion): void;

    /**
     * Marca un examen como publicado.
     *
     * @param Exam $exam Instancia del examen a publicar.
     * @return Exam
     */
    public function publish(Exam $exam): Exam;

    /**
     * Revierte la publicación de un examen regresándolo a estado borrador.
     *
     * @param Exam $exam Instancia del examen a despublicar.
     * @return Exam
     */
    public function unpublish(Exam $exam): Exam;

    /**
     * Cierra anticipadamente la ventana de aplicación de un examen publicado.
     *
     * @param Exam $exam Instancia del examen a cerrar.
     * @return Exam
     */
    public function closeEarly(Exam $exam): Exam;

    /**
     * Calcula en SQL las métricas de avance (intentos terminales vs total de alumnos asignados).
     *
     * @param Exam $exam Examen del cual se consulta el progreso.
     * @return array{answered_count: int, total_count: int}
     */
    public function getProgressStats(Exam $exam): array;

    /**
     * Carga el grafo completo requerido para el tablero de monitoreo en vivo sin N+1.
     *
     * @param Exam $exam Examen supervisado.
     * @return Exam
     */
    public function getMonitorDashboardData(Exam $exam): Exam;

    /**
     * Pagina en SQL los intentos de un examen para revisión y recoteje docente.
     *
     * @param Exam                 $exam    Examen evaluado.
     * @param array<string, mixed> $filters Filtros opcionales (search, status).
     * @param int                  $perPage Registros por página en SQL.
     * @return LengthAwarePaginator<StudentAttempt>
     */
    public function paginateAttemptsForReview(Exam $exam, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Busca un intento específico acotado al examen indicado con todas las relaciones
     * requeridas para la vista de recoteje docente sin lazy loading.
     *
     * @param Exam $exam      Examen propietario del intento.
     * @param int  $attemptId Identificador del intento de alumno.
     * @return StudentAttempt|null
     */
    public function findAttemptForReviewWithAnswers(Exam $exam, int $attemptId): ?StudentAttempt;

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
