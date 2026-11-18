<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        EnsureTeacherPlanOnboarded.php
 * Ubicación:      app/Http/Middleware/EnsureTeacherPlanOnboarded.php
 * Requerimiento:  RF-SUS-008, ADR-058 (Onboarding Post-Verificación Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Middleware que redirige al docente recién verificado hacia la
 *                 selección inicial de plan o prueba gratuita antes de entrar
 *                 a los módulos operativos web, manteniendo libres las rutas de
 *                 suscripción, verificación, cierre de sesión y API v1.
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
            && ! $request->routeIs('teacher.subscription.*', 'teacher.verification.*', 'auth.logout')
        ) {
            return redirect()->route('teacher.subscription.index', ['onboarding' => 1]);
        }

        return $next($request);
    }
}
