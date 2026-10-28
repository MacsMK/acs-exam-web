<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        EloquentGroupRepository.php
 * Ubicación:      app/Repositories/Eloquent/EloquentGroupRepository.php
 * Requerimiento:  RF-GRU (Gestión de Grupos y Folios QR, MPW-V2 §2.4 y §6.7)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Implementación Eloquent del repositorio de grupos con
 *                 Eager Loading (with, withCount), paginación en SQL,
 *                 aislamiento multi-docente y generación acotada de folio.
 * ============================================================================
 */

namespace App\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\Group;
use App\Models\User;
use App\Repositories\Contracts\GroupRepositoryInterface;

/**
 * Repositorio Eloquent para la entidad Group.
 *
 * @package App\Repositories\Eloquent
 */
class EloquentGroupRepository implements GroupRepositoryInterface
{
    /**
     * Obtiene los grupos administrados por un docente paginados en SQL con conteo de alumnos.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @param int $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<Group>
     */
    public function paginateForTeacher(int $teacherId, int $perPage = 15): LengthAwarePaginator
    {
        return Group::query()
            ->forTeacher($teacherId)
            ->withCount(['students', 'exams'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Obtiene todos los grupos administrados por un docente con conteo de alumnos precargado.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return Collection<int, Group>
     */
    public function getForTeacher(int $teacherId): Collection
    {
        return Group::query()
            ->forTeacher($teacherId)
            ->withCount(['students', 'exams'])
            ->latest()
            ->get();
    }

    /**
     * Obtiene los grupos abiertos (no cerrados, RF-GRU-019) en los que está inscrito un alumno.
     *
     * @param int $studentId Identificador del alumno.
     * @return Collection<int, Group>
     */
    public function getOpenForStudent(int $studentId): Collection
    {
        return Group::query()
            ->active()
            ->whereHas('students', fn (Builder $query) => $query->where('acs_users.id', $studentId))
            ->withCount(['students'])
            ->latest()
            ->get();
    }

    /**
     * Busca un grupo por su identificador precargando alumnos, exámenes y conteos.
     *
     * @param int $groupId Identificador del grupo.
     * @return Group|null
     */
    public function findByIdWithStudents(int $groupId): ?Group
    {
        return Group::query()
            ->with(['students', 'exams', 'evaluationPeriods'])
            ->withCount(['students', 'exams'])
            ->find($groupId);
    }

    /**
     * Busca un grupo por su folio de acceso corto (insensible a mayúsculas/minúsculas).
     *
     * @param string $folio Folio de acceso o payload QR.
     * @return Group|null
     */
    public function findByFolio(string $folio): ?Group
    {
        return Group::query()
            ->withCount(['students'])
            ->where('access_folio', strtoupper(trim($folio)))
            ->first();
    }

    /**
     * Crea un nuevo grupo para un docente generando un folio único acotado.
     *
     * @param int                  $teacherId Identificador del docente propietario.
     * @param array<string, mixed> $data      Datos validados del grupo (name, subject).
     * @return Group
     */
    public function createForTeacher(int $teacherId, array $data): Group
    {
        $group = Group::create([
            'teacher_id' => $teacherId,
            'name' => (string) $data['name'],
            'subject' => ! empty($data['subject']) ? (string) $data['subject'] : null,
            'access_folio' => (string) ($data['access_folio'] ?? Group::generateUniqueFolio()),
            'is_closed' => false,
        ]);

        return $group->loadCount(['students']);
    }

    /**
     * Ejecuta el cierre semestral del grupo (RW-35).
     *
     * @param Group $group Grupo a cerrar.
     * @return Group
     */
    public function closeGroup(Group $group): Group
    {
        $group->update([
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        return $group;
    }

    /**
     * Inscribe a un alumno en el grupo sin duplicar registros existentes.
     *
     * @param Group $group     Grupo destino.
     * @param int   $studentId Identificador del alumno.
     * @return Group
     */
    public function enrollStudent(Group $group, int $studentId): Group
    {
        $group->students()->syncWithoutDetaching([
            $studentId => ['joined_at' => now()],
        ]);

        return $group->fresh()->loadCount(['students']);
    }

    /**
     * Verifica en SQL si un alumno pertenece al grupo indicado.
     *
     * @param Group $group     Grupo a consultar.
     * @param int   $studentId Identificador del alumno.
     * @return bool
     */
    public function hasStudent(Group $group, int $studentId): bool
    {
        return $group->students()
            ->where('acs_users.id', $studentId)
            ->exists();
    }

    /**
     * Obtiene la colección de alumnos inscritos en el grupo ordenados alfabéticamente.
     *
     * @param Group $group Grupo del cual se listan los alumnos.
     * @return Collection<int, User>
     */
    public function getStudentsOrderedByName(Group $group): Collection
    {
        return $group->students()
            ->orderBy('first_name')
            ->get();
    }
}
