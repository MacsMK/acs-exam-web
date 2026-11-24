<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GradePlanController.php
 * Ubicación:      app/Http/Controllers/GradePlanController.php
 * Requerimiento:  RF-CAL-006 a 008, 010 y 011, 015, 016 y 018 a 020, 024, 025 y 028
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Edita y confirma la configuración de evaluación del grupo y sus periodos.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\GradePlanRequest;
use App\Models\EvaluationPeriod;
use App\Models\Group;
use App\Services\GradePlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class GradePlanController extends Controller
{
    /**
     * Recibe los servicios de dominio necesarios para la operación.
     *
     * @param GradePlanService $plans Servicio de configuración de calificaciones.
     * @return void
     */
    public function __construct(private readonly GradePlanService $plans)
    {
    }

    /**
     * Carga los periodos y aplicaciones disponibles para el docente propietario.
     *
     * @param Group $group Grupo al que pertenece la operación.
     * @param ?EvaluationPeriod $period Periodo de evaluación, opcional en el plan del grupo.
     * @return View Vista que contiene los datos autorizados.
     */
    public function edit(Group $group, ?EvaluationPeriod $period = null): View
    {
        abort_unless($group->teacher_id === request()->user()->id && ! $group->is_closed, 403);
        abort_if($period && $period->group_id !== $group->id, 404);

        return view('evaluation-periods.plan', [
            'group' => $group, 'period' => $period,
            'plan' => $period ? $this->plans->periodPlan($period) : $this->plans->groupPlan($group),
            'periods' => $group->evaluationPeriods()->orderBy('starts_at')->orderBy('id')->get(),
            'applications' => $period ? $this->plans->applications($group) : [],
        ]);
    }

    /**
     * Solicita la confirmación necesaria o guarda la configuración validada.
     *
     * @param GradePlanRequest $request Solicitud web validada.
     * @param Group $group Grupo al que pertenece la operación.
     * @param ?EvaluationPeriod $period Periodo de evaluación, opcional en el plan del grupo.
     * @return View|RedirectResponse Propuesta para confirmar o redirección al listado.
     */
    public function update(GradePlanRequest $request, Group $group, ?EvaluationPeriod $period = null): View|RedirectResponse
    {
        $input = $request->validated();
        if ($request->boolean('edit_plan')) {
            abort_if($group->is_closed, 403);
            abort_if($period && $period->group_id !== $group->id, 404);

            return ($period ? to_route('groups.periods.plan', [$group, $period]) : to_route('groups.final-plan', $group))
                ->withInput(collect($input)->except('confirmation')->all());
        }

        $review = $this->plans->change($group, $period, $input, $request->user()->id);
        if ($review !== null) {
            return view('evaluation-periods.confirm-plan', $review + [
                'input' => $input,
                'group' => $group,
                'period' => $period,
                'periodNames' => $group->evaluationPeriods()->pluck('name', 'id'),
                'applicationNames' => $period ? $this->plans->applications($group)->pluck('title', 'id') : collect(),
            ]);
        }

        return to_route('groups.periods.index', $group)->with('status', 'Configuración de calificaciones guardada');
    }
}
