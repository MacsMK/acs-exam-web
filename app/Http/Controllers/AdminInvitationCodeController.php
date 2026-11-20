<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AdminInvitationCodeController.php
 * Ubicación:      app/Http/Controllers/AdminInvitationCodeController.php
 * Requerimiento:  RW-05, ADR-057 (Gestión de Códigos Institucionales Docentes)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Controlador Skinny del portal Superadministrador para listar,
 *                 emitir y revocar/reactivar códigos de invitación institucional
 *                 para acreditación expedita de docentes delegando consultas y
 *                 operaciones transaccionales a TeacherVerificationService.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\SendInvitationCodeEmailRequest;
use App\Http\Requests\StoreInvitationCodeRequest;
use App\Models\TeacherInvitationCode;
use App\Models\User;
use App\Services\TeacherVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador administrativo de códigos de invitación institucional.
 *
 * @package App\Http\Controllers
 */
class AdminInvitationCodeController extends Controller
{
    /**
     * Inicializa el controlador inyectando el servicio de verificación docente.
     *
     * @param TeacherVerificationService $verificationService Servicio transaccional de verificación.
     */
    public function __construct(
        private readonly TeacherVerificationService $verificationService,
    ) {
    }

    /**
     * Muestra el catálogo paginado de códigos institucionales y métricas agregadas.
     *
     * @param Request $request Solicitud HTTP del superadministrador.
     * @return View
     */
    public function index(Request $request): View
    {
        return view(
            'admin.invitations.index',
            $this->verificationService->getInvitationCodesDashboard(),
        );
    }

    /**
     * Genera un nuevo código institucional de alta entropía.
     *
     * @param StoreInvitationCodeRequest $request Solicitud validada del nuevo código.
     * @return RedirectResponse
     */
    public function store(StoreInvitationCodeRequest $request): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $invitation = $this->verificationService->createInvitationCode(
            $admin,
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()
            ->route('admin.invitations.index')
            ->with(
                'status',
                "Código institucional {$invitation->code} generado correctamente para {$invitation->institution_name}.",
            );
    }

    /**
     * Alterna el estado activo (revocar o reactivar) de un código institucional.
     *
     * @param Request $request Solicitud HTTP del superadministrador.
     * @param TeacherInvitationCode $invitationCode Código institucional objetivo.
     * @return RedirectResponse
     */
    public function toggle(Request $request, TeacherInvitationCode $invitationCode): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $updated = $this->verificationService->toggleInvitationCode(
            $invitationCode,
            $admin,
            $request->ip(),
            $request->userAgent(),
        );

        $message = $updated->is_active
            ? "El código de invitación {$updated->code} fue reactivado correctamente."
            : "El código de invitación {$updated->code} fue revocado correctamente.";

        return redirect()
            ->route('admin.invitations.index')
            ->with('status', $message);
    }

    /**
     * Envía por correo electrónico un código institucional y su enlace directo de registro.
     *
     * @param SendInvitationCodeEmailRequest $request Solicitud validada con el correo destinatario.
     * @param TeacherInvitationCode $invitationCode Código institucional objetivo.
     * @return RedirectResponse
     */
    public function sendEmail(
        SendInvitationCodeEmailRequest $request,
        TeacherInvitationCode $invitationCode,
    ): RedirectResponse {
        /** @var User $admin */
        $admin = $request->user();

        $this->verificationService->sendInvitationCodeEmail(
            $invitationCode,
            $admin,
            $request->string('email')->toString(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()
            ->route('admin.invitations.index')
            ->with(
                'status',
                "Invitación con código {$invitationCode->code} enviada correctamente a {$request->string('email')->toString()}.",
            );
    }
}
