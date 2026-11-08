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

use App\Models\AuditLog;
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
    public function __construct(private readonly AttemptService $attemptService)
    {
    }

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
     * El alumno abandona un grupo al que pertenece (RF-GRU-012). La salida se
     * rechaza mientras tenga un intento de examen en curso asociado al grupo
     * (RF-GRU-017) y no finaliza ese intento. Al completarse pierde la
     * pertenencia y, con ella, el acceso a los recursos y resultados del
     * grupo; los intentos y calificaciones se conservan como evidencia
     * académica (ADR-036) y la salida queda en la bitácora de auditoría.
     *
     * @throws ValidationException Si el alumno no pertenece al grupo.
     * @throws DomainException     Si tiene un intento en curso en el grupo.
     */
    public function leaveGroup(User $student, Group $group): void
    {
        $belongs = $group->students()->where('acs_users.id', $student->id)->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'group' => ['No perteneces a este grupo.'],
            ]);
        }

        $hasAttemptInProgress = $group->studentAttempts()
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->with('exam')
            ->get()
            ->contains(fn (StudentAttempt $attempt): bool => $this->attemptService->isInProgress($attempt));

        if ($hasAttemptInProgress) {
            throw new DomainException(
                'No puedes abandonar el grupo mientras tengas un examen en curso. '
                .'Podrás hacerlo cuando ese intento haya finalizado.'
            );
        }

        DB::transaction(static function () use ($group, $student): void {
            $group->students()->detach($student->id);

            AuditLog::create([
                'user_id' => $student->id,
                'action' => 'group.left',
                'auditable_type' => Group::class,
                'auditable_id' => $group->id,
                'old_values' => ['student_id' => $student->id, 'group_id' => $group->id],
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            ]);
        });
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
