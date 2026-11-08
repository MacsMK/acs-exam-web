<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        LandingController.php
 * Ubicación:      app/Http/Controllers/LandingController.php
 * Requerimiento:  RF-SUS-001 (Consulta de paquetes), RW-01, MC-V2
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 08/11/2026
 * Descripción:    Controlador de la Landing Page institucional de bienvenida.
 *                 Expone la propuesta de valor, capacidades del ecosistema
 *                 y el catálogo de paquetes de suscripción SaaS.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\PrivacyNotice;
use App\Models\SubscriptionPackage;
use Illuminate\Contracts\View\View;

/**
 * Controlador de la página principal pública del SaaS.
 *
 * @package App\Http\Controllers
 */
class LandingController extends Controller
{
    /**
     * Muestra la Landing Page institucional con los paquetes activos y aviso vigente.
     *
     * @return View
     */
    public function index(): View
    {
        $packages = SubscriptionPackage::where('is_active', true)
            ->orderBy('monthly_price')
            ->get();

        $privacyNotice = PrivacyNotice::whereNotNull('published_at')
            ->latest('published_at')
            ->first();

        return view('landing', compact('packages', 'privacyNotice'));
    }
}
