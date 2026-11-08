<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupController.php
 * Ubicación:   app/Http/Controllers/Api/V1/GroupController.php
 * Descripción: api.v1.groups.* (MPM-V4 Tabla 12, MPW-V2 §6.1, §6.3) para la
 *              app móvil: listar grupos (según rol), unirse por folio o QR,
 *              verificar pertenencia por QR/folio y listar alumnos de un grupo.
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
            ? $user->groupsAsTeacher()->withCount('students')->get()
            : $user->groupsAsStudent()->where('acs_groups.is_closed', false)->withCount('students')->get();

        return ApiResponse::success($groups->map(fn (Group $group) => $this->payload($group))->all());
    }

    /**
     * api.v1.groups.join. Acepta `folio` O `qr_payload` mediante
     * GroupJoinRequest y delega la inscripción a GroupEnrollmentService.
     */
    public function join(GroupJoinRequest $request): JsonResponse
    {
        $group = $this->groupEnrollmentService->joinByFolio(
            $request->user(),
            $request->resolvedFolio(),
            'folio',
        );

        return ApiResponse::success(
            $this->payload($group),
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
     * @return array<string, mixed>
     */
    private function payload(Group $group): array
    {
        return [
            'id' => (string) $group->id,
            'name' => $group->name,
            'folio' => $group->access_folio,
            'students_count' => $group->students_count ?? $group->students()->count(),
        ];
    }
}
