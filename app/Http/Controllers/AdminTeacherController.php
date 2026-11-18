<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AdminTeacherController.php
 * Ubicación:      app/Http/Controllers/AdminTeacherController.php
 * Requerimiento:  RW-05, RW-06, RF-ADM-001..018, ADR-057 (Portal Superadministrador
 *                 y Verificación Híbrida de Identidad Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.1.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Controlador Skinny del panel administrativo para consultar
 *                 docentes, revisar comprobantes privados, dictaminar
 *                 acreditaciones (con purga automática LFPDPPP), suspender o
 *                 reactivar cuentas y asignar paquetes SaaS delegando toda la
 *                 lógica de negocio a TeacherVerificationService.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\AssignTeacherPackageRequest;
use App\Http\Requests\RejectTeacherVerificationRequest;
use App\Models\User;
use App\Services\TeacherVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador Skinny de gestión de docentes y dictamen de verificación.
 *
 * @package App\Http\Controllers
 */
class AdminTeacherController extends Controller
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
     * Lista todos los docentes registrados con su estatus de verificación y suscripción.
     *
     * @param Request $request Solicitud HTTP con filtros opcionales.
     * @return View
     */
    public function index(Request $request): View
    {
        $data = $this->verificationService->getAdminTeachersIndexData(
            $request->only(['search', 'status', 'verification']),
        );

        return view('admin.teachers.index', $data);
    }

    /**
     * Sirve de forma privada y endurecida el comprobante documental de un docente
     * pendiente de dictamen desde el disco local (LFPDPPP / OWASP).
     *
     * @param Request $request Solicitud HTTP del superadministrador.
     * @param User $teacher Cuenta docente titular del comprobante.
     * @return StreamedResponse
     */
    public function document(Request $request, User $teacher): StreamedResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $document = $this->verificationService->resolveAdminDocumentStream(
            $teacher,
            $admin,
            $request->ip(),
            $request->userAgent(),
        );

        return Storage::disk('local')->response($document['path'], $document['filename'], [
            'Content-Type' => $document['mime'],
            'Content-Disposition' => 'inline; filename="'.$document['filename'].'"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; sandbox",
        ]);
    }

    /**
     * Aprueba la verificación de identidad del docente y purga su comprobante.
     *
     * @param Request $request Solicitud HTTP del superadministrador.
     * @param User $teacher Cuenta docente a aprobar.
     * @return RedirectResponse
     */
    public function approveVerification(Request $request, User $teacher): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $this->verificationService->approve(
            $teacher,
            $admin,
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with(
            'status',
            "La identidad docente de {$teacher->full_name} fue aprobada y su comprobante fue purgado conforme a la LFPDPPP.",
        );
    }

    /**
     * Rechaza la verificación de identidad del docente indicando el motivo.
     *
     * @param RejectTeacherVerificationRequest $request Solicitud validada con motivo de rechazo.
     * @param User $teacher Cuenta docente a rechazar.
     * @return RedirectResponse
     */
    public function rejectVerification(RejectTeacherVerificationRequest $request, User $teacher): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $this->verificationService->reject(
            $teacher,
            $admin,
            $request->string('rejection_reason')->toString(),
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with(
            'status',
            "La solicitud docente de {$teacher->full_name} fue rechazada, notificada por correo y su comprobante fue purgado.",
        );
    }

    /**
     * Alterna la suspensión o activación de la cuenta del docente (RW-05).
     *
     * @param Request $request Solicitud HTTP del superadministrador.
     * @param User $teacher Cuenta docente objetivo.
     * @return RedirectResponse
     */
    public function toggleStatus(Request $request, User $teacher): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $updated = $this->verificationService->toggleTeacherActiveStatus(
            $teacher,
            $admin,
            $request->ip(),
            $request->userAgent(),
        );

        $statusMessage = $updated->is_active
            ? "La cuenta del docente {$updated->full_name} ha sido reactivada exitosamente."
            : "La cuenta del docente {$updated->full_name} ha sido suspendida exitosamente.";

        return back()->with('status', $statusMessage);
    }

    /**
     * Asigna o actualiza manualmente el paquete de suscripción del docente (RW-05).
     *
     * @param AssignTeacherPackageRequest $request Solicitud validada con paquete y estatus de pago.
     * @param User $teacher Cuenta docente objetivo.
     * @return RedirectResponse
     */
    public function assignPackage(AssignTeacherPackageRequest $request, User $teacher): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $this->verificationService->assignSubscriptionPackage(
            $teacher,
            $admin,
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('status', "Paquete de suscripción actualizado exitosamente para {$teacher->full_name}.");
    }
}
