<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TenantTeacherScope.php
 * Ubicación:      app/Models/Scopes/TenantTeacherScope.php
 * Requerimiento:  RF-AUT / Aislamiento Multi-Docente (MPW-V2 §2.3.4, §6.6)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Scope Eloquent de aislamiento multi-tenant por docente.
 *                 Restringe las consultas a teacher_id = Auth::id() cuando
 *                 existe una sesión autenticada con rol 'teacher', sin
 *                 interferir con consultas de alumnos, super_admin ni
 *                 ejecuciones por consola/seeders.
 * ============================================================================
 */

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Scope de aislamiento de datos para cuentas docentes en arquitectura SaaS.
 *
 * @package App\Models\Scopes
 */
class TenantTeacherScope implements Scope
{
    /**
     * Aplica la restricción de aislamiento por docente al constructor de consultas.
     *
     * @param Builder<Model> $builder Constructor de la consulta Eloquent.
     * @param Model          $model   Modelo sobre el cual se aplica el scope.
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $user = Auth::user();

        if ($user !== null && $user->role === 'teacher' && in_array('teacher_id', $model->getFillable(), true)) {
            $builder->where($model->qualifyColumn('teacher_id'), Auth::id());
        }
    }
}
