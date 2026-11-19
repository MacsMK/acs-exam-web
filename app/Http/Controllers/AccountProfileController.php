<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AccountProfileController.php
 * Ubicación:      app/Http/Controllers/AccountProfileController.php
 * Requerimiento:  RF-AUT-013 (Perfil y ajustes)
 * Actividad:      ACT-WEB-050
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Sección "Mi perfil" del alumno y del superadministrador:
 *                 consulta de los datos de la cuenta, del aviso de privacidad
 *                 vigente y de la versión aceptada, y cierre de sesión desde
 *                 la misma sección. El docente conserva su propio perfil
 *                 (TeacherProfileController).
 * ============================================================================
 */

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $acceptance = $user->privacyAcceptances()
            ->with('privacyNotice')
            ->latest('accepted_at')
            ->first();

        return view('account.profile', [
            'user' => $user,
            'acceptance' => $acceptance,
        ]);
    }
}
