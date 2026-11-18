<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        EnsureTeacherIsVerified.php
 * Ubicación:      app/Http/Middleware/EnsureTeacherIsVerified.php
 * Requerimiento:  RF-AUT-001, ADR-057 (Verificación Híbrida de Identidad Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Middleware de compuerta que impide a docentes con acreditación
 *                 pendiente o rechazada acceder a rutas operativas web o API v1,
 *                 redirigiendo en web al estatus de verificación o devolviendo
 *                 HTTP 403 uniforme y diferenciado vía ApiResponse en JSON/API.
 * ============================================================================
 */

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe el acceso operativo a cuentas docentes cuya identidad ya fue verificada.
 *
 * @package App\Http\Middleware
 */
class EnsureTeacherIsVerified
{
    /**
     * Intercepta la solicitud y verifica la acreditación si el usuario es docente.
     *
     * @param Request $request Solicitud HTTP entrante.
     * @param Closure(Request): Response $next Siguiente eslabón de la cadena middleware.
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->role === 'teacher' && ! $user->isVerifiedTeacher()) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $message = $user->isRejectedTeacherVerification()
                    ? 'Tu solicitud de acreditación docente fue rechazada. Revisa el motivo y reenvía tu comprobante desde la plataforma web.'
                    : 'Tu cuenta docente está pendiente de verificación. Completa la acreditación desde la plataforma web.';

                return ApiResponse::error(
                    $message,
                    403,
                    ['verification_status' => $user->teacher_verification_status],
                );
            }

            return redirect()->route('teacher.verification.status');
        }

        return $next($request);
    }
}
