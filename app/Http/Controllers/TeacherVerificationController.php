<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherVerificationController.php
 * Ubicación:      app/Http/Controllers/TeacherVerificationController.php
 * Requerimiento:  RF-AUT-001, ADR-057 (Verificación Híbrida de Identidad Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Controlador Skinny para que los docentes en espera o
 *                 rechazados consulten el estatus de su acreditación, reenvíen
 *                 su comprobante o canjeen un código institucional vigente.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\RedeemInvitationCodeRequest;
use App\Http\Requests\ResubmitTeacherVerificationRequest;
use App\Models\TeacherVerification;
use App\Models\User;
use App\Services\TeacherVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Controlador de seguimiento, reenvío y canje de código de verificación docente.
 *
 * @package App\Http\Controllers
 */
class TeacherVerificationController extends Controller
{
    /**
     * Inicializa el controlador delegando la lógica al servicio transaccional.
     *
     * @param TeacherVerificationService $verificationService Servicio de verificación docente.
     */
    public function __construct(
        private readonly TeacherVerificationService $verificationService,
    ) {
    }

    /**
     * Muestra el estado actual del expediente de verificación del docente o
     * redirige al panel operativo si su cuenta ya fue aprobada.
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @return View|RedirectResponse
     */
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isVerifiedTeacher()) {
            return redirect()->route('dashboard');
        }

        $user->load('teacherVerification.invitationCode');

        return view('teacher.verification.status', [
            'user' => $user,
            'verification' => $user->teacherVerification,
            'educationalLevels' => TeacherVerification::EDUCATIONAL_LEVELS,
            'credentialTypes' => TeacherVerification::CREDENTIAL_TYPES,
        ]);
    }

    /**
     * Procesa el reenvío de comprobante documental para docentes rechazados.
     *
     * @param ResubmitTeacherVerificationRequest $request Solicitud validada con nuevo comprobante.
     * @return RedirectResponse
     */
    public function resubmit(ResubmitTeacherVerificationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var UploadedFile $document */
        $document = $request->file('verification_document');

        $this->verificationService->resubmit(
            $user,
            $request->validated(),
            $document,
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()
            ->route('teacher.verification.status')
            ->with(
                'status',
                'Tu nuevo comprobante fue enviado correctamente. El equipo de A-Code Solutions lo revisará a la brevedad.',
            );
    }

    /**
     * Acredita inmediatamente al docente mediante un código de invitación vigente.
     *
     * @param RedeemInvitationCodeRequest $request Solicitud validada con el código institucional.
     * @return RedirectResponse
     */
    public function redeemCode(RedeemInvitationCodeRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->verificationService->redeemInvitationCodeForTeacher(
            $user,
            $request->string('invitation_code')->toString(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()
            ->route('dashboard')
            ->with(
                'status',
                '¡Bienvenido! Tu cuenta docente fue acreditada con éxito mediante código institucional. Elige y confirma tu paquete o inicia tu prueba gratuita para comenzar.',
            );
    }
}
