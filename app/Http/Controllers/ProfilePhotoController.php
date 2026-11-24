<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ProfilePhotoController.php
 * Ubicación:      app/Http/Controllers/ProfilePhotoController.php
 * Requerimiento:  RF-AUT-008, RF-AUT-009
 * Actividad:      ACT-WEB-038
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Entrega la fotografía de perfil desde el disco privado solo
 *                 a quien tiene permiso (propio usuario, superadministrador o
 *                 docentes de sus grupos).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProfilePhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function __invoke(Request $request, User $user, ProfilePhotoService $photos): StreamedResponse
    {
        abort_unless($photos->canView($request->user(), $user), 403);

        $path = $user->profile_photo_path;
        $disk = Storage::disk(ProfilePhotoService::DISK);

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
