<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupController.php
 * Ubicación:   app/Http/Controllers/Api/V1/GroupController.php
 * Descripción: Un alumno se une a un grupo capturando el folio (o
 *              escaneando el QR, que en el móvil decodifica al mismo
 *              folio) desde la app móvil.
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'access_folio' => ['required', 'string'],
        ]);

        $group = Group::where('access_folio', strtoupper($data['access_folio']))->first();

        if (! $group) {
            throw ValidationException::withMessages(['access_folio' => ['Folio no encontrado.']]);
        }

        if ($group->is_closed) {
            throw ValidationException::withMessages(['access_folio' => ['Este grupo ya cerró el ciclo (RW-35).']]);
        }

        $group->students()->syncWithoutDetaching([
            $request->user()->id => ['joined_at' => now()],
        ]);

        return response()->json(['message' => 'Te uniste al grupo.', 'group' => $group]);
    }

    public function mine(Request $request): JsonResponse
    {
        $groups = $request->user()->groupsAsStudent()->get();

        return response()->json(['groups' => $groups]);
    }
}
