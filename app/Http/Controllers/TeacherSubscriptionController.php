<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherSubscriptionController.php
 * Ubicación:      app/Http/Controllers/TeacherSubscriptionController.php
 * Requerimiento:  RF-SUS-001..015, RF-SUS-008, RF-SUS-013, RW-01..04, ADR-058
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Controlador Skinny de autoservicio para consulta de cuotas,
 *                 onboarding post-verificación con Prueba Gratuita y checkout
 *                 instantáneo mediante pasarela de pago simulada (tarjeta/SPEI).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\ProcessSubscriptionCheckoutRequest;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\TeacherSubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controlador Skinny del panel de suscripción y pasarela de pago docente.
 *
 * @package App\Http\Controllers
 */
class TeacherSubscriptionController extends Controller
{
    /**
     * Inicializa el controlador delegando la lógica al servicio transaccional.
     *
     * @param TeacherSubscriptionService $teacherSubscriptionService Servicio de suscripciones y checkout.
     */
    public function __construct(
        private readonly TeacherSubscriptionService $teacherSubscriptionService,
    ) {
    }

    /**
     * Muestra el estado de suscripción, uso de cuotas, catálogo de planes y
     * el historial reciente de transacciones del docente.
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @return View
     */
    public function index(Request $request): View
    {
        /** @var User $teacher */
        $teacher = $request->user();

        $overview = $this->teacherSubscriptionService->getSubscriptionOverview(
            $teacher,
            $request->boolean('onboarding'),
        );

        return view('teacher.subscription.index', $overview);
    }

    /**
     * Muestra la pantalla de checkout para contratar o renovar un paquete SaaS.
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @param SubscriptionPackage $package Paquete SaaS seleccionado.
     * @return View
     */
    public function checkout(Request $request, SubscriptionPackage $package): View
    {
        /** @var User $teacher */
        $teacher = $request->user();

        $data = $this->teacherSubscriptionService->getCheckoutData($teacher, $package);

        return view('teacher.subscription.checkout', $data);
    }

    /**
     * Procesa el pago instantáneo del paquete seleccionado mediante la pasarela
     * configurada y redirige según el dictamen aprobado o rechazado.
     *
     * @param ProcessSubscriptionCheckoutRequest $request Solicitud validada del formulario de pago.
     * @param SubscriptionPackage $package Paquete SaaS objetivo del cobro.
     * @return RedirectResponse
     */
    public function processPayment(
        ProcessSubscriptionCheckoutRequest $request,
        SubscriptionPackage $package,
    ): RedirectResponse {
        /** @var User $teacher */
        $teacher = $request->user();

        $transaction = $this->teacherSubscriptionService->processInstantCheckout(
            $teacher,
            $package,
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        if ($transaction->status === PaymentTransaction::STATUS_PAID) {
            return redirect()
                ->route('teacher.subscription.index')
                ->with(
                    'status',
                    "¡Pago aprobado! Tu plan {$package->name} quedó activo de inmediato (Folio {$transaction->reference_code}).",
                );
        }

        return back()
            ->withInput($request->except(['card_number', 'card_cvv', 'card_expiry']))
            ->withErrors([
                'payment_gateway' => $transaction->failure_message
                    ?? 'No fue posible procesar el pago, intenta nuevamente.',
            ]);
    }

    /**
     * Completa el onboarding inicial eligiendo la Prueba Gratuita (Freemium)
     * y habilita el acceso operativo al panel docente.
     *
     * @param Request $request Solicitud HTTP del docente verificado.
     * @return RedirectResponse
     */
    public function startFreeTrial(Request $request): RedirectResponse
    {
        /** @var User $teacher */
        $teacher = $request->user();

        $this->teacherSubscriptionService->activateFreeTrialOnboarding(
            $teacher,
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()
            ->route('dashboard')
            ->with(
                'status',
                'Tu Prueba Gratuita (Freemium) está lista. Ya puedes crear tu primer grupo, banco de reactivos y evaluación.',
            );
    }
}
