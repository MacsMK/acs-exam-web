<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        EnsureTeacherPlanOnboarded.php
 * Ubicación:      app/Http/Middleware/EnsureTeacherPlanOnboarded.php
 * Requerimiento:  RF-SUS-008, ADR-058, ADR-060 (Capa Bloqueante de Plan)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Middleware que redirige al docente recién verificado hacia
 *                 el Dashboard (donde opera el overlay bloqueante de selección
 *                 de plan o prueba gratuita) antes de acceder a los módulos
 *                 operativos web, manteniendo libres dashboard, suscripción,
 *                 verificación, aviso de privacidad, cierre de sesión y API v1.
 * ============================================================================
 */

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que los docentes verificados completen la selección inicial de plan SaaS.
 *
 * @package App\Http\Middleware
 */
class EnsureTeacherPlanOnboarded
{
    /**
     * Verifica si el docente autenticado ya completó su onboarding inicial de plan.
     *
     * @param Request $request Solicitud HTTP entrante.
     * @param Closure(Request): Response $next Siguiente eslabón de la cadena middleware.
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof User
            && $user->role === 'teacher'
            && $user->isVerifiedTeacher()
            && $user->needsPlanOnboarding()
            && ! $request->is('api/*')
            && ! $request->expectsJson()
            && ! $request->routeIs(
                'dashboard',
                'teacher.subscription.*',
                'teacher.verification.*',
                'privacy.show.public',
                'auth.logout',
            )
        ) {
            return redirect()
                ->route('dashboard')
                ->with('warning', 'Selecciona y confirma tu paquete o prueba gratuita para comenzar a usar la plataforma.');
        }

        return $next($request);
    }
}
