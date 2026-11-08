<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupEnrollmentService.php
 * Ubicación:   app/Services/GroupEnrollmentService.php
 * Descripción: Servicio de dominio para gestión de grupos, inscripción por
 *              folio/QR, verificación de pertenencia, listado de exámenes
 *              disponibles y cierre semestral seguro (RF-GRU-018, MPW-V2 §5.2.1).
 * ============================================================================
 */

namespace App\Services;

use App\Models\Exam;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GroupEnrollmentService
{
    /**
     * Crea un grupo para el docente con un folio único garantizado mediante
     * ciclo finito (MPW-V2 §5.2.1).
     *
     * @param array<string, mixed> $data
     */
    public function createGroup(User $teacher, array $data): Group
    {
        return DB::transaction(function () use ($teacher, $data): Group {
            return Group::create([
                'teacher_id' => $teacher->id,
                'name' => (string) $data['name'],
                'subject' => ! empty($data['subject']) ? (string) $data['subject'] : null,
                'access_folio' => $this->generateUniqueFolio(),
            ]);
        });
    }

    /**
     * Inscribe al alumno en el grupo correspondiente al folio proporcionado,
     * validando existencia y estado abierto del grupo.
     *
     * @throws ValidationException
     */
    public function joinByFolio(User $student, string $folio, string $errorField = 'folio'): Group
    {
        $normalizedFolio = strtoupper(trim($folio));

        if ($normalizedFolio === '') {
            throw ValidationException::withMessages([
                $errorField => ['El campo folio de acceso es obligatorio'],
            ]);
        }

        $group = Group::withoutGlobalScopes()
            ->where('access_folio', $normalizedFolio)
            ->first();

        if (! $group) {
            throw ValidationException::withMessages([
                $errorField => ['Folio no encontrado.'],
            ]);
        }

        if ($group->is_closed) {
            throw ValidationException::withMessages([
                $errorField => ['Este grupo ya cerró el ciclo (RW-35).'],
            ]);
        }

        DB::transaction(static function () use ($group, $student): void {
            $group->students()->syncWithoutDetaching([
                $student->id => ['joined_at' => now()],
            ]);
        });

        return $group->fresh()->loadCount('students');
    }

    /**
     * Verifica que el alumno pertenezca al grupo activo identificado por folio/QR.
     *
     * @throws ValidationException
     */
    public function verifyMembership(User $student, string $folio, string $errorField = 'qr_payload'): Group
    {
        $normalizedFolio = strtoupper(trim($folio));

        $group = Group::withoutGlobalScopes()
            ->where('access_folio', $normalizedFolio)
            ->first();

        if (! $group || $group->is_closed) {
            throw ValidationException::withMessages([
                $errorField => ['El grupo no existe o ya cerró.'],
            ]);
        }

        $belongs = $group->students()->where('acs_users.id', $student->id)->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                $errorField => ['No perteneces a este grupo.'],
            ]);
        }

        return $group;
    }

    /**
     * Lista los exámenes publicados asignados a los grupos activos del alumno,
     * con carga ansiosa de apartados y reactivos para evitar lazy loading.
     *
     * @return Collection<int, Exam>
     */
    public function listAvailableExamsForStudent(User $student): Collection
    {
        return Exam::withoutGlobalScopes()
            ->where('is_published', true)
            ->whereHas('groups.students', fn ($q) => $q->where('acs_users.id', $student->id))
            ->with(['sections.examQuestions'])
            ->get();
    }

    /**
     * Cierra el grupo semestralmente verificando antes que no existan intentos
     * de examen en progreso asociados al grupo (RF-GRU-018 / P-109).
     *
     * @throws DomainException
     */
    public function closeGroup(Group $group): void
    {
        $hasActiveAttempts = $group->studentAttempts()
            ->where('status', 'in_progress')
            ->exists()
            || StudentAttempt::where('status', 'in_progress')
                ->whereHas('exam.groups', fn ($q) => $q->where('acs_groups.id', $group->id))
                ->exists();

        if ($hasActiveAttempts) {
            throw new DomainException('No es posible cerrar el grupo mientras existan exámenes en progreso.');
        }

        DB::transaction(static function () use ($group): void {
            $group->update([
                'is_closed' => true,
                'closed_at' => now(),
            ]);
        });
    }

    /**
     * Genera un folio único con límite de reintentos finito (MPW-V2 §5.2.1).
     */
    public function generateUniqueFolio(): string
    {
        return Group::generateUniqueFolio();
    }
}
