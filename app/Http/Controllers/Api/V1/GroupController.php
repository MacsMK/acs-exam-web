<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupController.php
 * Ubicación:   app/Http/Controllers/Api/V1/GroupController.php
 * Descripción: api.v1.groups.* (MPM-V4 Tabla 12) para la app móvil: listar
 *              grupos (según rol), unirse por folio o QR, verificar
 *              pertenencia por QR y listar alumnos de un grupo. El
 *              servidor decide el alcance según el rol del token Sanctum,
 *              igual criterio que ExamController (el cliente no filtra
 *              por rol).
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    /**
     * api.v1.groups.index. Para el alumno, oculta grupos ya cerrados
     * (RF-GRU-019, "Ocultamiento de grupos cerrados al estudiante") — es
     * el servidor quien filtra, el cliente móvil no trae ningún campo de
     * estado de grupo ni aplica ningún filtro propio (ver
     * docs/04_DECISIONES_ADR.md). El docente sigue viendo sus grupos
     * cerrados, para poder administrarlos.
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
     * api.v1.groups.join. Acepta `folio` O `qr_payload` (el cliente móvil
     * manda exactamente uno de los dos, nunca ambos); el QR del docente
     * solo codifica el folio (MPM-V4 §6.9), así que ambos campos se tratan
     * igual del lado del servidor.
     */
    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folio' => ['nullable', 'string'],
            'qr_payload' => ['nullable', 'string'],
        ]);

        $folio = $data['folio'] ?? $data['qr_payload'] ?? null;

        if ($folio === null || $folio === '') {
            throw ValidationException::withMessages(['folio' => ['Falta el folio o el código QR.']]);
        }

        $group = Group::where('access_folio', strtoupper($folio))->first();

        if (! $group) {
            throw ValidationException::withMessages(['folio' => ['Folio no encontrado.']]);
        }

        if ($group->is_closed) {
            throw ValidationException::withMessages(['folio' => ['Este grupo ya cerró el ciclo (RW-35).']]);
        }

        $group->students()->syncWithoutDetaching([
            $request->user()->id => ['joined_at' => now()],
        ]);

        return ApiResponse::success(
            $this->payload($group->fresh()->loadCount('students')),
            'Te uniste al grupo.',
        );
    }

    /**
     * api.v1.groups.verify (Tabla 5: verificación de pertenencia por
     * escaneo, distinto de unirse). Sin pantalla conectada todavía del
     * lado móvil, pero implementado contra el contrato completo.
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['qr_payload' => ['required', 'string']]);

        $group = Group::where('access_folio', strtoupper($data['qr_payload']))->first();

        if (! $group || $group->is_closed) {
            throw ValidationException::withMessages(['qr_payload' => ['El grupo no existe o ya cerró.']]);
        }

        $belongs = $group->students()->where('acs_users.id', $request->user()->id)->exists();

        if (! $belongs) {
            throw ValidationException::withMessages(['qr_payload' => ['No perteneces a este grupo.']]);
        }

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
