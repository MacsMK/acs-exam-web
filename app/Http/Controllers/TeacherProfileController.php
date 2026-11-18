<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherProfileController.php
 * Ubicación:      app/Http/Controllers/TeacherProfileController.php
 * Requerimiento:  RW-03, RW-04, ADR-057 (Gestión de Perfil y Credenciales Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Controlador Skinny para consultar el perfil y resumen de
 *                 acreditación del docente autenticado y actualizar datos
 *                 personales, teléfono y contraseña vía Form Request.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeacherProfileRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador de consulta y edición del perfil docente autenticado.
 *
 * @package App\Http\Controllers
 */
class TeacherProfileController extends Controller
{
    /**
     * Muestra el formulario de edición de perfil del usuario autenticado con
     * precarga de su expediente de acreditación docente.
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @return View
     */
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('teacherVerification.invitationCode');

        return view('teacher.profile.index', [
            'user' => $user,
        ]);
    }

    /**
     * Actualiza la información personal y credenciales del docente autenticado.
     *
     * @param UpdateTeacherProfileRequest $request Solicitud validada del perfil docente.
     * @return RedirectResponse
     */
    public function update(UpdateTeacherProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $user->first_name = (string) $validated['first_name'];
        $user->last_name = (string) $validated['last_name'];
        $user->phone = (string) $validated['phone'];

        if (! empty($validated['new_password'])) {
            $user->password = (string) $validated['new_password'];
        }

        $user->save();

        return back()->with('status', 'Perfil actualizado correctamente.');
    }
}
