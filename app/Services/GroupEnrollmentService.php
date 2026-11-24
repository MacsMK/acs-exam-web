<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupEnrollmentService.php
 * Ubicación:   app/Services/GroupEnrollmentService.php
 * Descripción: Servicio de dominio para gestión de grupos, inscripción por
 *              folio/QR, verificación de pertenencia y cierre semestral
 *              seguro (RF-GRU-018, MPW-V3 §5.2.1).
 * ============================================================================
 */

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Scopes\TenantTeacherScope;
use App\Models\StudentAttempt;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GroupEnrollmentService
{
    public function __construct(
        private readonly SubscriptionLimitService $subscriptionLimitService,
        private readonly AttemptService $attemptService,
    ) {
    }

    /**
     * Crea un grupo para el docente con un folio único garantizado mediante
     * ciclo finito (MPW-V3 §5.2.1) validando cuotas de suscripción (RF-SUS-007, RF-SUS-010).
     *
     * @param array<string, mixed> $data
     */
    public function createGroup(User $teacher, array $data): Group
    {
        return DB::transaction(function () use ($teacher, $data): Group {
            $teacher = User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $this->subscriptionLimitService->ensureCanCreateGroup($teacher);
            if ($this->subscriptionLimitService->resolveActivePackage($teacher) === null) {
                $teacher->forceFill(['trial_group_used_at' => now()])->save();
            }

            return Group::create([
                'teacher_id' => $teacher->id,
                'name' => (string) $data['name'],
                'subject' => ! empty($data['subject']) ? (string) $data['subject'] : null,
                'description' => ! empty($data['description']) ? (string) $data['description'] : null,
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

        return DB::transaction(function () use ($student, $normalizedFolio, $errorField): Group {
            $group = Group::withoutGlobalScope(TenantTeacherScope::class)
                ->where('access_folio', $normalizedFolio)
                ->lockForUpdate()
                ->first();

            if (! $group) {
                throw ValidationException::withMessages([
                    $errorField => ['No encontramos ese folio. Verifica el código con tu docente'],
                ]);
            }

            if ($group->is_closed) {
                throw ValidationException::withMessages([
                    $errorField => ['Este grupo está cerrado y no admite nuevas inscripciones'],
                ]);
            }

            if ($group->students()->whereKey($student->id)->exists()) {
                throw ValidationException::withMessages([
                    $errorField => ['Ya te encuentras inscrito en este grupo escolar.'],
                ]);
            }

            $this->subscriptionLimitService->ensureCanEnrollStudent($group);
            $group->students()->syncWithoutDetaching([
                $student->id => ['joined_at' => now()],
            ]);

            return $group->loadCount('students');
        });
    }

    /**
     * Verifica que el alumno pertenezca al grupo activo identificado por folio/QR.
     *
     * @throws ValidationException
     */
    public function verifyMembership(User $student, string $folio, string $errorField = 'qr_payload'): Group
    {
        $normalizedFolio = strtoupper(trim($folio));

        $group = Group::withoutGlobalScope(TenantTeacherScope::class)
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
        DB::transaction(function () use ($group, $student): void {
            $group = Group::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            if (! $group->students()->whereKey($student->id)->exists()) {
                throw ValidationException::withMessages([
                    'group' => ['No perteneces a este grupo.'],
                ]);
            }

            if ($this->hasAttemptInProgress($group, $student)) {
                throw new DomainException(
                    'No puedes abandonar el grupo mientras tengas un examen en curso. '
                    .'Podrás hacerlo cuando ese intento haya finalizado.'
                );
            }

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
     * Cierra el grupo semestralmente verificando antes que no existan intentos
     * de examen en progreso asociados al grupo (RF-GRU-018 / P-109).
     *
     * @throws DomainException
     */
    /**
     * Retiro de un estudiante por el docente (RF-GRU-011, RF-GRU-016,
     * ACT-WEB-021). Elimina la pertenencia y con ella el acceso del estudiante
     * a los recursos y resultados del grupo; sus registros académicos se
     * conservan (CAM-008). No procede si tiene un intento en curso en el grupo.
     *
     * @param Group $group Grupo administrado por el docente.
     * @param User $student Estudiante a retirar.
     * @param User $teacher Docente que realiza el retiro (bitácora).
     * @return void
     *
     * @throws ValidationException Si el estudiante no pertenece al grupo.
     * @throws DomainException Si el estudiante tiene un intento en curso.
     */
    public function removeStudent(Group $group, User $student, User $teacher): void
    {
        DB::transaction(function () use ($group, $student, $teacher): void {
            $group = Group::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            if (! $group->students()->whereKey($student->id)->exists()) {
                throw ValidationException::withMessages([
                    'student' => ['El estudiante no pertenece a este grupo.'],
                ]);
            }

            if ($this->hasAttemptInProgress($group, $student)) {
                throw new DomainException(
                    'No es posible retirar al estudiante mientras tenga un examen en curso en este grupo. '
                    .'Podrás hacerlo cuando ese intento haya finalizado.'
                );
            }

            $group->students()->detach($student->id);

            AuditLog::create([
                'user_id' => $teacher->id,
                'action' => 'group.student_removed',
                'auditable_type' => Group::class,
                'auditable_id' => $group->id,
                'old_values' => ['student_id' => $student->id, 'group_id' => $group->id],
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            ]);
        });
    }

    /**
     * Comprueba la vigencia de los intentos del grupo ya bloqueado.
     *
     * @param Group $group Grupo que se está modificando.
     * @param User|null $student Limita la comprobación al alumno que saldría del grupo.
     * @return bool
     */
    private function hasAttemptInProgress(Group $group, ?User $student = null): bool
    {
        return $group->studentAttempts()
            ->current()
            ->when($student, fn ($query) => $query->where('student_id', $student->id))
            ->where('status', 'in_progress')
            ->with('exam')
            ->get()
            ->contains(fn (StudentAttempt $attempt): bool => $this->attemptService->isInProgress($attempt));
    }

    /**
     * Modifica nombre, materia y descripción de un grupo activo (RF-GRU-003, ACT-WEB-020).
     *
     * @param array<string, mixed> $data
     *
     * @throws DomainException Si el grupo está cerrado.
     */
    public function updateGroup(Group $group, array $data): Group
    {
        if ($group->is_closed) {
            throw new DomainException('Solo puedes modificar grupos activos. Reabre el grupo para editarlo.');
        }

        $group->update([
            'name' => (string) $data['name'],
            'subject' => ! empty($data['subject']) ? (string) $data['subject'] : null,
            'description' => ! empty($data['description']) ? (string) $data['description'] : null,
        ]);

        return $group;
    }

    public function closeGroup(Group $group): void
    {
        DB::transaction(function () use ($group): void {
            $group = Group::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            if ($group->is_closed) {
                return;
            }

            if ($this->hasAttemptInProgress($group)) {
                throw new DomainException('Hay estudiantes de este grupo con un examen en curso. Intenta cerrar el grupo cuando terminen.');
            }

            $group->update([
                'is_closed' => true,
                'closed_at' => now(),
            ]);
        });
    }

    /**
     * Reabre un grupo bajo el mismo bloqueo usado para la purga.
     *
     * @param Group $group Grupo cerrado del docente.
     * @return void
     */
    public function reopenGroup(Group $group): void
    {
        DB::transaction(function () use ($group): void {
            $teacher = User::query()->whereKey($group->teacher_id)->lockForUpdate()->firstOrFail();
            $group = Group::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();

            abort_unless($group->is_closed, 409, 'El grupo ya está activo.');
            $this->subscriptionLimitService->ensureCanReopenGroup($teacher);

            $group->update([
                'is_closed' => false,
                'closed_at' => null,
            ]);
        });
    }

    /**
     * Genera un folio único con límite de reintentos finito (MPW-V3 §5.2.1).
     */
    public function generateUniqueFolio(): string
    {
        return Group::generateUniqueFolio();
    }
}
