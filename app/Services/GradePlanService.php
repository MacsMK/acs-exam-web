<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GradePlanService.php
 * Ubicación:      app/Services/GradePlanService.php
 * Requerimiento:  RF-CAL-006 a 008, 010 y 011, 015, 016 y 018 a 020, 024, 025 y 028
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Valida ponderaciones y conserva los cambios de configuración bajo bloqueo del grupo.
 * ============================================================================
 */

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EvaluationPeriod;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\StudentPeriodGrade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Valida y versiona la configuración bajo el mismo bloqueo de grupo que la captura. */
class GradePlanService
{
    public const COMPONENTS = ['exams', 'homework', 'project', 'other'];

    /**
     * Recibe los servicios de dominio necesarios para la operación.
     *
     * @param GroupGradeResults $results Calculador de resultados del grupo.
     * @return void
     */
    public function __construct(private readonly GroupGradeResults $results)
    {
    }

    /**
     * Enumera las aplicaciones del grupo y el periodo asignado a cada una.
     *
     * @param Group $group Grupo al que pertenece la operación.
     * @return Collection Elementos consultados e indexados según la operación.
     */
    public function applications(Group $group): Collection
    {
        return DB::table('acs_exam_group as a')->join('acs_exams as e', 'e.id', '=', 'a.exam_id')
            ->leftJoin('acs_period_exam_weights as w', 'w.application_id', '=', 'a.id')
            ->where('a.group_id', $group->id)->where('e.teacher_id', $group->teacher_id)
            ->orderBy('a.id')->get(['a.id', 'a.exam_id', 'e.title', 'w.period_id as assigned_period_id']);
    }

    /**
     * Obtiene las ponderaciones actuales de un periodo y sus fuentes.
     *
     * @param EvaluationPeriod $period Periodo de evaluación, opcional en el plan del grupo.
     * @return array Datos estructurados de la operación.
     */
    public function periodPlan(EvaluationPeriod $period): array
    {
        return [
            'components' => array_replace(array_fill_keys(self::COMPONENTS, 0), $period->parsed_weights),
            'exams' => DB::table('acs_period_exam_weights')->where('period_id', $period->id)
                ->orderBy('application_id')->pluck('weight', 'application_id')->all(),
            'previous' => DB::table('acs_period_dependencies')->where('period_id', $period->id)
                ->orderBy('source_id')->pluck('weight', 'source_id')->all(),
        ];
    }

    /**
     * Obtiene la escala, el mínimo y los pesos finales de un grupo.
     *
     * @param Group $group Grupo al que pertenece la operación.
     * @return array Datos estructurados de la operación.
     */
    public function groupPlan(Group $group): array
    {
        return [
            'scale' => $group->grading_scale, 'minimum' => $group->passing_score,
            'periods' => $group->evaluationPeriods()->whereNotNull('group_final_weight')
                ->orderBy('id')->pluck('group_final_weight', 'id')->all(),
        ];
    }

    /**
     * Valida la configuración y guarda el cambio o devuelve la propuesta para confirmar.
     *
     * @param Group $group Grupo al que pertenece la operación.
     * @param ?EvaluationPeriod $period Periodo de evaluación, opcional en el plan del grupo.
     * @param array $input Valores validados del formulario.
     * @param int $actorId Identificador del docente que guarda el cambio.
     * @return ?array Propuesta pendiente de confirmación, o nulo al guardar.
     *
     * @throws ValidationException Si la propuesta no satisface las reglas.
     */
    public function change(Group $group, ?EvaluationPeriod $period, array $input, int $actorId): ?array
    {
        return DB::transaction(function () use ($group, $period, $input, $actorId) {
            $group = Group::whereKey($group->id)->lockForUpdate()->firstOrFail();
            abort_unless($group->teacher_id === $actorId && ! $group->is_closed, 403);
            if ($period !== null) {
                $period = $group->evaluationPeriods()->findOrFail($period->id);
                $old = $this->periodPlan($period);
                $new = $this->validatePeriod($group, $period, $input);
            } else {
                $old = $this->groupPlan($group);
                $new = $this->validateGroup($group, $input);
            }

            if ($old == $new) {
                return null;
            }
            $periodIds = $period === null ? $group->evaluationPeriods()->pluck('id')->all()
                : $this->relatedPeriodIds($group, $period, array_keys($new['previous']));
            $grades = StudentPeriodGrade::whereIn('evaluation_period_id', $periodIds);
            $hasGrades = (clone $grades)->where(function ($query) {
                foreach (self::COMPONENTS as $key) {
                    $query->orWhereNotNull($key.'_component');
                }
                $query->orWhereNotNull('final_grade');
            })->exists();
            if ($period === null && $old['scale'] !== $new['scale'] && $hasGrades) {
                $compatible = $old['scale'] === null && $new['scale'] === '0-100'
                    && ! (clone $grades)->where(function ($query) {
                        foreach ([...self::COMPONENTS, 'final'] as $key) {
                            $column = $key === 'final' ? 'final_grade' : $key.'_component';
                            $query->orWhereRaw("$column <> trunc($column)");
                        }
                    })->exists();
                if (! $compatible) {
                    throw ValidationException::withMessages(['scale' => 'Las notas existentes requieren una regla autorizada de conversión antes de cambiar su escala']);
                }
            }
            $applicationIds = DB::table('acs_period_exam_weights')->whereIn('period_id', $periodIds)->pluck('application_id');
            if ($period !== null) {
                $applicationIds = $applicationIds->merge(array_keys($new['exams']));
            }
            $gradedExams = StudentAttempt::current()->where('group_id', $group->id)->whereNotNull('final_score')
                ->whereIn('exam_id', DB::table('acs_exam_group')->whereIn('id', $applicationIds)->select('exam_id'))
                ->orderBy('id')->pluck('final_score', 'id')->all();
            $hasGrades = $hasGrades || $gradedExams !== [];
            $calculator = $this->results;
            $before = $hasGrades ? $calculator->calculate($group, publishedOnly: true)['published'] : [];
            if ($hasGrades) {
                $token = hash_hmac('sha256', serialize([$actorId, $group->id, $period?->id, $old, $new, $before, $gradedExams]), config('app.key'));
                if (! hash_equals($token, $input['confirmation'] ?? '')) {
                    return ['confirmation' => $token, 'previousPlan' => $old, 'proposedPlan' => $new];
                }
            }

            if ($period !== null) {
                $terms = [];
                foreach ($new['components'] as $key => $weight) {
                    $terms[] = $key.'*'.rtrim(rtrim(bcdiv((string) $weight, '100', 4), '0'), '.');
                }
                $period->forceFill(['formula' => implode('+', $terms), 'uses_grade_plan' => true])->save();
                $this->replaceWeights('acs_period_exam_weights', 'application_id', $period->id, $new['exams']);
                $this->replaceWeights('acs_period_dependencies', 'source_id', $period->id, $new['previous']);
            } else {
                $group->forceFill(['grading_scale' => $new['scale'], 'passing_score' => $new['minimum']])->save();
                $group->evaluationPeriods()->update(['group_final_weight' => null]);
                foreach ($new['periods'] as $id => $weight) {
                    $group->evaluationPeriods()->whereKey($id)->update(['group_final_weight' => $weight]);
                }
            }
            if ($hasGrades && ! $group->is_closed) {
                $calculator->notifyChanges($before, $calculator->calculate($group, publishedOnly: true)['published']);
            }
            AuditLog::create([
                'user_id' => $actorId, 'action' => 'grades.configuration_changed',
                'auditable_type' => $period ? EvaluationPeriod::class : Group::class,
                'auditable_id' => $period?->id ?? $group->id, 'old_values' => $old, 'new_values' => $new,
            ]);

            return null;
        });
    }

    /**
     * Reúne los periodos relacionados por la configuración existente o propuesta.
     *
     * @param Group $group Grupo propietario de las dependencias.
     * @param EvaluationPeriod $period Periodo editado.
     * @param array $sources Identificadores de las fuentes propuestas.
     * @return array Identificadores que pueden intervenir en el cambio.
     */
    private function relatedPeriodIds(Group $group, EvaluationPeriod $period, array $sources): array
    {
        $edges = DB::table('acs_period_dependencies')->whereIn('period_id', $group->evaluationPeriods()->select('id'))
            ->get(['period_id', 'source_id']);
        $neighbors = [];
        foreach ($edges as $edge) {
            $neighbors[$edge->period_id][] = $edge->source_id;
            $neighbors[$edge->source_id][] = $edge->period_id;
        }
        $pending = [$period->id, ...$sources];
        $related = [];
        while ($pending !== []) {
            $id = array_pop($pending);
            if (! isset($related[$id])) {
                $related[$id] = true;
                array_push($pending, ...($neighbors[$id] ?? []));
            }
        }

        return array_keys($related);
    }

    /**
     * Valida los pesos, las aplicaciones y los periodos previos elegibles.
     *
     * @param Group $group Grupo al que pertenece la operación.
     * @param EvaluationPeriod $period Periodo de evaluación, opcional en el plan del grupo.
     * @param array $input Valores validados del formulario.
     * @return array Datos estructurados de la operación.
     *
     * @throws ValidationException Si la composición no es válida.
     */
    private function validatePeriod(Group $group, EvaluationPeriod $period, array $input): array
    {
        $components = array_replace(array_fill_keys(self::COMPONENTS, 0), $input['components']);
        $exams = $this->positiveWeights($input['exams'] ?? []);
        $previous = $this->positiveWeights($input['previous'] ?? []);
        $this->total(array_merge(array_values($components), array_values($previous)), 'components');
        if ($exams !== []) {
            $this->total($exams, 'exams');
            $allowed = $this->applications($group)->pluck('id')->all();
            if (array_diff(array_keys($exams), $allowed)) {
                throw ValidationException::withMessages(['exams' => 'La aplicación no pertenece al grupo']);
            }
            $alreadyUsed = DB::table('acs_period_exam_weights')->whereIn('application_id', array_keys($exams))
                ->where('period_id', '<>', $period->id)->exists();
            if ((float) $components['exams'] <= 0 || $alreadyUsed) {
                throw ValidationException::withMessages(['exams' => 'Selecciona aplicaciones disponibles de este grupo y asigna peso al rubro de exámenes']);
            }
        }
        // Cada arista debe apuntar estrictamente hacia atrás. Ningún ciclo puede satisfacer esta condición.
        $earlier = $group->evaluationPeriods()->where('starts_at', '<', $period->starts_at)->pluck('id')->all();
        if (array_diff(array_keys($previous), $earlier)) {
            throw ValidationException::withMessages(['previous' => 'Solo puedes elegir periodos anteriores del mismo grupo']);
        }

        return compact('components', 'exams', 'previous');
    }

    /**
     * Valida el mínimo dentro de la escala y la ponderación de los periodos.
     *
     * @param Group $group Grupo al que pertenece la operación.
     * @param array $input Valores validados del formulario.
     * @return array Datos estructurados de la operación.
     *
     * @throws ValidationException Si el mínimo o los periodos no son válidos.
     */
    private function validateGroup(Group $group, array $input): array
    {
        $scale = $input['scale'];
        $minimum = $input['minimum'];
        if (($scale === '0-10' && (float) $minimum > 10) || ($scale === '0-100' && (float) $minimum !== floor((float) $minimum))) {
            throw ValidationException::withMessages(['minimum' => 'El mínimo debe respetar la escala seleccionada']);
        }
        $periods = $this->positiveWeights($input['periods'] ?? []);
        if ($periods !== []) {
            $this->total($periods, 'periods');
            if (array_diff(array_keys($periods), $group->evaluationPeriods()->pluck('id')->all())) {
                throw ValidationException::withMessages(['periods' => 'Selecciona periodos de este grupo']);
            }
        }

        return compact('scale', 'minimum', 'periods');
    }

    /**
     * Selecciona y ordena las fuentes con aportación positiva.
     *
     * @param array $weights Porcentajes indexados por fuente.
     * @return array Datos estructurados de la operación.
     */
    private function positiveWeights(array $weights): array
    {
        $weights = array_filter($weights, static fn ($weight) => (float) $weight > 0);
        ksort($weights, SORT_NUMERIC);

        return $weights;
    }

    /**
     * Exige que los porcentajes sumen exactamente cien.
     *
     * @param array $weights Porcentajes indexados por fuente.
     * @param string $field Campo al que se atribuye el error.
     * @return void
     *
     * @throws ValidationException Si los porcentajes difieren de cien.
     */
    private function total(array $weights, string $field): void
    {
        $sum = '0.00';
        foreach ($weights as $weight) {
            $sum = bcadd($sum, (string) $weight, 2);
        }
        if (bccomp($sum, '100', 2) !== 0) {
            throw ValidationException::withMessages([$field => 'Las ponderaciones deben sumar exactamente 100 %']);
        }
    }

    /**
     * Reemplaza las aportaciones de un periodo dentro de la transacción de cambio.
     *
     * @param string $table Tabla de ponderaciones del periodo.
     * @param string $key Columna que identifica la fuente.
     * @param int $periodId Identificador del periodo de destino.
     * @param array $weights Porcentajes indexados por fuente.
     * @return void
     */
    private function replaceWeights(string $table, string $key, int $periodId, array $weights): void
    {
        DB::table($table)->where('period_id', $periodId)->delete();
        foreach ($weights as $id => $weight) {
            DB::table($table)->insert(['period_id' => $periodId, $key => $id, 'weight' => $weight]);
        }
    }
}
