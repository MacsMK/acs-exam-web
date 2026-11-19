<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ProfilePhotoService.php
 * Ubicación:      app/Services/ProfilePhotoService.php
 * Requerimiento:  RF-AUT-008, RF-AUT-009
 * Actividad:      ACT-WEB-038
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Guarda la fotografía opcional del estudiante en el disco
 *                 privado (no accesible por URL pública) y decide quién
 *                 puede consultarla: el propio usuario, el superadministrador
 *                 y los docentes de los grupos a los que pertenece.
 * ============================================================================
 */

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfilePhotoService
{
    public const DISK = 'local';

    public const DIRECTORY = 'profile-photos';

    /**
     * Guarda la fotografía y la asocia a la cuenta, reemplazando la anterior.
     */
    public function store(User $user, UploadedFile $photo): void
    {
        $extension = strtolower($photo->getClientOriginalExtension()) === 'png' ? 'png' : 'jpg';
        $path = $photo->storeAs(self::DIRECTORY.'/'.$user->id, Str::uuid()->toString().'.'.$extension, self::DISK);

        $previous = $user->profile_photo_path;

        $user->forceFill(['profile_photo_path' => $path])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }
    }

    /**
     * Indica si el usuario autenticado puede ver la fotografía de la cuenta.
     */
    public function canView(User $viewer, User $owner): bool
    {
        if ($viewer->id === $owner->id || $viewer->role === 'super_admin') {
            return true;
        }

        if ($viewer->role !== 'teacher') {
            return false;
        }

        return $owner->groupsAsStudent()
            ->withoutGlobalScopes()
            ->where('acs_groups.teacher_id', $viewer->id)
            ->exists();
    }
}
