<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        WebPrivacyController.php
 * Ubicación:      app/Http/Controllers/WebPrivacyController.php
 * Requerimiento:  RW-07, RF-AUT-003, LFPDPPP Art. 15 (Aviso de Privacidad Permanente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Controlador web para consulta pública y permanente del Aviso de Privacidad.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\PrivacyNotice;
use Illuminate\Contracts\View\View;

class WebPrivacyController extends Controller
{
    /**
     * Muestra el Aviso de Privacidad vigente de A-Code Solutions.
     */
    public function show(): View
    {
        $notice = PrivacyNotice::whereNotNull('published_at')
            ->latest('published_at')
            ->first();

        return view('legal.privacy-notice', [
            'notice' => $notice,
        ]);
    }
}
