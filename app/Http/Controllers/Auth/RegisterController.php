<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        RegisterController.php
 * Ubicación:      app/Http/Controllers/Auth/RegisterController.php
 * Requerimiento:  RF-AUT-001, RF-AUT-002, RF-AUT-011, RW-03, ADR-031, ADR-057
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Controlador Skinny para el registro web de cuentas docentes
 *                 (con acreditación híbrida) y de alumnos, validación de aviso
 *                 de privacidad versionado e inicio de sesión 2FA pendiente.
 * ============================================================================
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterStudentRequest;
use App\Http\Requests\RegisterTeacherRequest;
use App\Models\TeacherInvitationCode;
use App\Models\TeacherVerification;
use App\Services\AuthService;
use App\Services\ProfilePhotoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador de registro web para docentes y alumnos.
 *
 * @package App\Http\Controllers\Auth
 */
class RegisterController extends Controller
{
    /**
     * Inicializa el controlador inyectando el servicio transaccional de autenticación.
     *
     * @param AuthService $authService Servicio de registro y 2FA.
     */
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * Muestra el formulario web de registro docente con soporte de enlace directo (?codigo=).
     *
     * @param Request $request Solicitud HTTP entrante.
     * @return View
     */
    public function showTeacherForm(Request $request): View
    {
        $privacyNotice = $this->authService->currentPrivacyNotice();
        $prefilledInvitationCode = mb_substr(
            TeacherInvitationCode::normalizeCode((string) $request->query('codigo', '')),
            0,
            32,
        );

        return view('auth.register-teacher', [
            'privacyNotice' => $privacyNotice,
            'termsVersion' => \App\Models\TermsVersion::current(),
            'prefilledInvitationCode' => $prefilledInvitationCode,
            'educationalLevels' => TeacherVerification::EDUCATIONAL_LEVELS,
            'credentialTypes' => TeacherVerification::CREDENTIAL_TYPES,
        ]);
    }

    /**
     * Muestra el formulario web de registro de alumno con el aviso vigente.
     *
     * @return View
     */
    public function showStudentForm(): View
    {
        $privacyNotice = $this->authService->currentPrivacyNotice();

        return view('auth.register-student', [
            'privacyNotice' => $privacyNotice,
            'termsVersion' => \App\Models\TermsVersion::current(),
        ]);
    }

    /**
     * Procesa el alta web de una cuenta docente y redirige a la verificación 2FA.
     *
     * @param RegisterTeacherRequest $request Solicitud validada de registro docente.
     * @return RedirectResponse
     */
    public function storeTeacher(RegisterTeacherRequest $request): RedirectResponse
    {
        $user = $this->authService->registerWeb(
            $request->validated(),
            'teacher',
            $request->input('privacy_notice_id'),
            $request->ip(),
            $request->file('verification_document'),
            $request->userAgent(),
        );

        $request->session()->put('pending_2fa_user_id', $user->id);

        return redirect()
            ->route('auth.verify-2fa.show', ['user' => $user->id])
            ->with('status', 'Te enviamos un código de verificación.');
    }

    /**
     * Procesa el alta web de una cuenta de alumno y redirige a la verificación 2FA.
     *
     * @param RegisterStudentRequest $request Solicitud validada de registro de alumno.
     * @return RedirectResponse
     */
    public function storeStudent(RegisterStudentRequest $request, ProfilePhotoService $profilePhotoService): RedirectResponse
    {
        $user = $this->authService->registerWeb(
            collect($request->validated())->except('photo')->all(),
            'student',
            $request->input('privacy_notice_id'),
            $request->ip(),
        );

        // Fotografía opcional del estudiante (RF-AUT-008)
        if ($request->hasFile('photo')) {
            $profilePhotoService->store($user, $request->file('photo'));
        }

        $request->session()->put('pending_2fa_user_id', $user->id);

        return redirect()
            ->route('auth.verify-2fa.show', ['user' => $user->id])
            ->with('status', 'Te enviamos un código de verificación.');
    }
}
