<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherAiCreditsController.php
 * Ubicación:      app/Http/Controllers/TeacherAiCreditsController.php
 * Requerimiento:  RF-SUS-013, RF-IAR-001..014, ADR-059 (Monitor de Créditos IA)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Controlador Skinny para el monitor de créditos y evaluaciones
 *                 asistidas por IA, delegando la agregación a AiEvaluationService.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AiEvaluationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controlador delgado del tablero docente de créditos de inteligencia artificial.
 *
 * @package App\Http\Controllers
 */
class TeacherAiCreditsController extends Controller
{
    /**
     * Inicializa el controlador inyectando el servicio de evaluación con IA.
     *
     * @param AiEvaluationService $aiEvaluationService Servicio de métricas y evaluación IA.
     */
    public function __construct(
        private readonly AiEvaluationService $aiEvaluationService,
    ) {
    }

    /**
     * Muestra el resumen de consumo de créditos, saldo disponible y driver activo de IA.
     *
     * @param Request $request Solicitud HTTP del docente autenticado.
     * @return View
     */
    public function index(Request $request): View
    {
        /** @var User $teacher */
        $teacher = $request->user();

        return view(
            'teacher.ai-credits.index',
            $this->aiEvaluationService->getTeacherAiCreditsOverview($teacher),
        );
    }
}
