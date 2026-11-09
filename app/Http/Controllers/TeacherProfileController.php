<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherProfileController.php
 * Ubicación:      app/Http/Controllers/TeacherProfileController.php
 * Requerimiento:  RW-03, RW-04 (Gestión de Perfil y Credenciales Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Controlador para actualizar datos personales, teléfono y contraseña.
 * ============================================================================
 */

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class TeacherProfileController extends Controller
{
    /**
     * Muestra el formulario de edición de perfil del usuario autenticado.
     */
    public function show(Request $request): View
    {
        return view('teacher.profile.index', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Actualiza la información personal del docente.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\d{10}$/'],
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'first_name.required' => 'El campo nombre es obligatorio',
            'last_name.required' => 'El campo apellidos es obligatorio',
            'phone.required' => 'El campo teléfono es obligatorio',
            'phone.regex' => 'El teléfono debe contener 10 dígitos numéricos',
            'current_password.current_password' => 'La contraseña actual no es correcta',
            'new_password.confirmed' => 'Los campos nueva contraseña y confirmar contraseña deben coincidir',
            'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres',
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->phone = $validated['phone'];

        if (!empty($validated['new_password'])) {
            $user->password = Hash::make($validated['new_password']);
        }

        $user->save();

        return back()->with('status', 'Perfil actualizado correctamente.');
    }
}
