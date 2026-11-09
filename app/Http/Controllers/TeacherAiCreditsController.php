<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherAiCreditsController.php
 * Ubicación:      app/Http/Controllers/TeacherAiCreditsController.php
 * Requerimiento:  RF-SUS-013, RF-IAR-001..014 (Monitoreo de Créditos y Evaluaciones IA)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Monitor de consumo de evaluaciones asistidas por IA (Gemini 1.5 Flash)
 *                 y saldo de solicitudes por paquete.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\StudentAnswer;
use App\Services\SubscriptionLimitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TeacherAiCreditsController extends Controller
{
    public function __construct(
        private readonly SubscriptionLimitService $subscriptionLimitService
    ) {
    }

    /**
     * Muestra el resumen de consumo de evaluaciones de inteligencia artificial.
     */
    public function index(Request $request): View
    {
        $teacher = $request->user();
        $activePackage = $this->subscriptionLimitService->resolveActivePackage($teacher);

        // Respuestas evaluadas con IA correspondientes a exámenes creados por este docente
        $aiAnswersQuery = StudentAnswer::whereHas('examQuestion.exam', function ($q) use ($teacher): void {
            $q->where('teacher_id', $teacher->id);
        })->whereNotNull('ai_evaluated_at');

        $totalAiEvaluations = (clone $aiAnswersQuery)->count();
        $recotejadasCount = (clone $aiAnswersQuery)->whereNotNull('teacher_recoteje_score')->count();
        $promedioSimilitud = (clone $aiAnswersQuery)->avg('ai_similarity_score') ?? 85.0;

        $aiRequestsLimit = $activePackage?->ai_requests_limit ?? 100; // Freemium: 100 eval/mes

        return view('teacher.ai-credits.index', [
            'teacher' => $teacher,
            'activePackage' => $activePackage,
            'totalAiEvaluations' => $totalAiEvaluations,
            'recotejadasCount' => $recotejadasCount,
            'promedioSimilitud' => round((float) $promedioSimilitud, 1),
            'aiRequestsLimit' => $aiRequestsLimit,
        ]);
    }
}
