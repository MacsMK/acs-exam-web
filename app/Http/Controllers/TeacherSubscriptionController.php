<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherSubscriptionController.php
 * Ubicación:      app/Http/Controllers/TeacherSubscriptionController.php
 * Requerimiento:  RF-SUS-001..015, RW-01..04 (Panel de Suscripción Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Controlador de autoservicio para consulta de cuotas SaaS,
 *                 comparativa de paquetes y contacto de contratación por WhatsApp.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Question;
use App\Models\SubscriptionPackage;
use App\Services\SubscriptionLimitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TeacherSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionLimitService $subscriptionLimitService
    ) {
    }

    /**
     * Muestra el estado de la suscripción, uso de cuotas y opciones de contratación.
     */
    public function index(Request $request): View
    {
        $teacher = $request->user();
        $activePackage = $this->subscriptionLimitService->resolveActivePackage($teacher);

        // Cuotas actuales del docente
        $activeGroupsCount = Group::withoutGlobalScopes()->where('teacher_id', $teacher->id)->where('is_closed', false)->count();
        $cumulativeGroupsCount = Group::withoutGlobalScopes()->where('teacher_id', $teacher->id)->count();
        $questionsCount = Question::withoutGlobalScopes()->where('teacher_id', $teacher->id)->count();
        $examsCount = Exam::withoutGlobalScopes()->where('teacher_id', $teacher->id)->where('is_published', true)->count();
        $cumulativeExamsCount = Exam::withoutGlobalScopes()->where('teacher_id', $teacher->id)->count();

        // Catálogo de paquetes activos (Tabla 15 MPW-V2)
        $packages = SubscriptionPackage::where('is_active', true)
            ->orderBy('monthly_price')
            ->get();

        // Teléfono oficial de A-Code Solutions para contratación comercial
        $whatsappNumber = config('services.acode.whatsapp_sales', '5215500000000');

        return view('teacher.subscription.index', [
            'teacher' => $teacher,
            'activePackage' => $activePackage,
            'activeGroupsCount' => $activeGroupsCount,
            'cumulativeGroupsCount' => $cumulativeGroupsCount,
            'questionsCount' => $questionsCount,
            'examsCount' => $examsCount,
            'cumulativeExamsCount' => $cumulativeExamsCount,
            'packages' => $packages,
            'whatsappNumber' => $whatsappNumber,
        ]);
    }
}
