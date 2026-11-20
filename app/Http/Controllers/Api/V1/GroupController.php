<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupController.php
 * Ubicación:   app/Http/Controllers/Api/V1/GroupController.php
 * Descripción: api.v1.groups.* (MPM-V3 Tabla 12, MPW-V2 §6.1, §6.3) para la
 *              app móvil: listar grupos (según rol), unirse por folio o QR,
 *              verificar pertenencia por QR/folio, listar alumnos de un grupo
 *              y salir de un grupo (alumno). Cada grupo incluye su materia, el
 *              nombre del docente y, para el alumno, la fecha en que se unió
 *              (ADR-061).
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GroupJoinRequest;
use App\Http\Requests\GroupVerifyFolioRequest;
use App\Models\Group;
use App\Services\GroupEnrollmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GroupController extends Controller
{
    public function __construct(private readonly GroupEnrollmentService $groupEnrollmentService)
    {
    }

    /**
     * api.v1.groups.index. Para el alumno, oculta grupos ya cerrados
     * (RF-GRU-019, "Ocultamiento de grupos cerrados al estudiante").
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $groups = $user->role === 'teacher'
            ? $user->groupsAsTeacher()->with('teacher')->withCount('students')->get()
            : $user->groupsAsStudent()->where('acs_groups.is_closed', false)->with('teacher')->withCount('students')->get();

        return ApiResponse::success($groups->map(fn (Group $group) => $this->payload($group))->all());
    }

    /**
     * api.v1.groups.join. Acepta `folio` O `qr_payload` mediante
     * GroupJoinRequest y delega la inscripción a GroupEnrollmentService.
     * Responde con el grupo tal como aparece en api.v1.groups.index,
     * incluida la fecha de ingreso que se acaba de registrar.
     */
    public function join(GroupJoinRequest $request): JsonResponse
    {
        $student = $request->user();

        $group = $this->groupEnrollmentService->joinByFolio(
            $student,
            $request->resolvedFolio(),
            'folio',
        );

        $joinedGroup = $student->groupsAsStudent()
            ->where('acs_groups.id', $group->id)
            ->with('teacher')
            ->withCount('students')
            ->first();

        return ApiResponse::success(
            $this->payload($joinedGroup ?? $group->load('teacher')),
            'Te uniste al grupo.',
        );
    }

    /**
     * api.v1.groups.verify (Tabla 5: verificación de pertenencia por
     * escaneo o folio, distinto de unirse).
     */
    public function verify(GroupVerifyFolioRequest $request): JsonResponse
    {
        $this->groupEnrollmentService->verifyMembership(
            $request->user(),
            $request->resolvedFolio(),
            'qr_payload',
        );

        return ApiResponse::success(null, 'Pertenencia verificada.');
    }

    /**
     * api.v1.groups.students — solo el docente dueño del grupo.
     */
    public function students(Request $request, Group $group): JsonResponse
    {
        abort_unless($group->teacher_id === $request->user()->id, 403);

        $students = $group->students()->orderBy('first_name')->get();

        return ApiResponse::success(
            $students->map(fn ($student) => ['id' => (string) $student->id, 'name' => $student->full_name])->all(),
        );
    }

    /**
     * api.v1.groups.leave (RF-GRU-012 y RF-GRU-017, ADR-036): el alumno
     * abandona un grupo al que pertenece. Rechaza (409) si tiene un intento de
     * examen en curso asociado al grupo.
     */
    public function leave(Request $request, Group $group): JsonResponse
    {
        abort_unless($request->user()->role === 'student', 403);

        $this->groupEnrollmentService->leaveGroup($request->user(), $group);

        return ApiResponse::success(null, 'Saliste del grupo.');
    }

    /**
     * Datos de un grupo para la app (ADR-061). `teacher` debe venir
     * cargado. `joined_at` solo existe cuando el grupo se obtuvo desde la
     * relación del alumno (tabla acs_group_student); para el docente es null.
     *
     * @return array<string, mixed>
     */
    private function payload(Group $group): array
    {
        $joinedAt = $group->relationLoaded('pivot') ? $group->pivot->getAttribute('joined_at') : null;

        return [
            'id' => (string) $group->id,
            'name' => $group->name,
            'folio' => $group->access_folio,
            'students_count' => $group->students_count ?? $group->students()->count(),
            'subject' => $group->subject,
            'teacher_name' => $group->teacher?->full_name,
            'joined_at' => $joinedAt === null ? null : Carbon::parse($joinedAt),
        ];
    }
}
