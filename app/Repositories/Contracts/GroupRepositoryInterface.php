<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GroupRepositoryInterface.php
 * Ubicación:      app/Repositories/Contracts/GroupRepositoryInterface.php
 * Requerimiento:  RF-GRU (Gestión de Grupos y Folios QR, MPW-V2 §2.4 y §6.7)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Contrato del repositorio de grupos para encapsular las
 *                 consultas con withCount(), inscripción de alumnos por
 *                 folio/QR y cierre semestral.
 * ============================================================================
 */

namespace App\Repositories\Contracts;

use App\Models\Group;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato de persistencia y consulta para la entidad Group.
 *
 * @package App\Repositories\Contracts
 */
interface GroupRepositoryInterface
{
    /**
     * Obtiene los grupos administrados por un docente paginados en SQL con conteo de alumnos.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @param int $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<Group>
     */
    public function paginateForTeacher(int $teacherId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtiene todos los grupos administrados por un docente con conteo de alumnos precargado.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return Collection<int, Group>
     */
    public function getForTeacher(int $teacherId): Collection;

    /**
     * Obtiene los grupos abiertos (no cerrados, RF-GRU-019) en los que está inscrito un alumno.
     *
     * @param int $studentId Identificador del alumno.
     * @return Collection<int, Group>
     */
    public function getOpenForStudent(int $studentId): Collection;

    /**
     * Busca un grupo por su identificador precargando alumnos, exámenes y conteos.
     *
     * @param int $groupId Identificador del grupo.
     * @return Group|null
     */
    public function findByIdWithStudents(int $groupId): ?Group;

    /**
     * Busca un grupo por su folio de acceso corto (insensible a mayúsculas/minúsculas).
     *
     * @param string $folio Folio de acceso o payload QR.
     * @return Group|null
     */
    public function findByFolio(string $folio): ?Group;

    /**
     * Crea un nuevo grupo para un docente generando un folio único acotado.
     *
     * @param int                  $teacherId Identificador del docente propietario.
     * @param array<string, mixed> $data      Datos validados del grupo (name, subject).
     * @return Group
     */
    public function createForTeacher(int $teacherId, array $data): Group;

    /**
     * Ejecuta el cierre semestral del grupo (RW-35).
     *
     * @param Group $group Grupo a cerrar.
     * @return Group
     */
    public function closeGroup(Group $group): Group;

    /**
     * Inscribe a un alumno en el grupo sin duplicar registros existentes.
     *
     * @param Group $group     Grupo destino.
     * @param int   $studentId Identificador del alumno.
     * @return Group
     */
    public function enrollStudent(Group $group, int $studentId): Group;

    /**
     * Verifica en SQL si un alumno pertenece al grupo indicado.
     *
     * @param Group $group     Grupo a consultar.
     * @param int   $studentId Identificador del alumno.
     * @return bool
     */
    public function hasStudent(Group $group, int $studentId): bool;

    /**
     * Obtiene la colección de alumnos inscritos en el grupo ordenados alfabéticamente.
     *
     * @param Group $group Grupo del cual se listan los alumnos.
     * @return Collection<int, User>
     */
    public function getStudentsOrderedByName(Group $group): Collection;
}
