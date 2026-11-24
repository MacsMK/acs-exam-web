<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GroupGradeResults.php
 * Ubicación:      app/Services/GroupGradeResults.php
 * Requerimiento:  RF-CAL-006 a 008, 010 y 011, 015, 016 y 018 a 020 y 028, RF-REP-008 y 017
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Calcula por estudiante los rubros, los periodos y la calificación final del grupo.
 * ============================================================================
 */

namespace App\Services;

use App\Models\EvaluationPeriod;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\StudentPeriodGrade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Obtiene resultados de la misma configuración para sábana, consulta y exportación. */
class GroupGradeResults
{
    /**
     * Calcula los resultados del grupo respetando ponderaciones, escala y visibilidad solicitada.
     *
     * @param Group $group Grupo al que pertenece la operación.
     * @param ?int $studentId Identificador del estudiante que limita la consulta.
     * @param bool $publishedOnly Aplica la visibilidad del resultado para el estudiante.
     * @param ?int $breakdownPeriodId Periodo para el que se detallan los aportes de cada fuente.
     * @return array Datos estructurados de la operación.
     */
    public function calculate(Group $group, ?int $studentId = null, bool $publishedOnly = false, ?int $breakdownPeriodId = null): array
    {
        $students = $group->students()->when($studentId !== null, fn ($q) => $q->where('acs_users.id', $studentId))
            ->pluck('acs_users.id');
        $periods = $group->evaluationPeriods()->orderBy('starts_at')->orderBy('id')->get();
        $ids = $periods->modelKeys();
        $records = StudentPeriodGrade::whereIn('evaluation_period_id', $ids)->whereIn('student_id', $students)
            ->get()->groupBy('evaluation_period_id')->map(fn ($rows) => $rows->keyBy('student_id'));
        $apps = DB::table('acs_period_exam_weights as w')->join('acs_exam_group as a', 'a.id', '=', 'w.application_id')
            ->join('acs_exams as e', 'e.id', '=', 'a.exam_id')->where('a.group_id', $group->id)
            ->whereIn('w.period_id', $ids)->get(['w.period_id', 'w.weight', 'e.id as exam_id', 'e.grading_scale']);
        $attempts = $this->submittedAttempts($group, $students, $apps->pluck('exam_id'))
            ->when($publishedOnly, fn ($q) => $q->whereHas('exam', fn ($exam) => $exam->where('result_visibility_mode', '<>', 'hidden')))
            ->whereNotNull('final_score')
            ->get(['student_id', 'exam_id', 'final_score'])->groupBy('student_id')->map(fn ($rows) => $rows->keyBy('exam_id'));
        $byPeriod = $apps->groupBy('period_id');
        $dependencies = DB::table('acs_period_dependencies')->whereIn('period_id', $ids)
            ->get(['period_id', 'source_id', 'weight'])->groupBy('period_id');
        $values = [];
        $rows = [];
        $public = [];
        $finals = [];
        $periodBreakdown = [];
        foreach ($students as $id) {
            $groupValues = [];
            $public[$id] = [];
            foreach ($periods as $period) {
                $pid = $period->id;
                $record = $records->get($pid)?->get($id) ?? new StudentPeriodGrade(['student_id' => $id, 'evaluation_period_id' => $pid]);
                $weighted = [];
                if ($byPeriod->has($pid)) {
                    $record->exams_component = $this->examScore($byPeriod[$pid], $attempts->get($id, collect()), $group);
                }
                $weights = $period->parsed_weights;
                $components = [];
                foreach (GradePlanService::COMPONENTS as $component) {
                    $weight = $weights[$component] ?? 0;
                    $score = $record->getAttribute($component.'_component');
                    $components[$component] = $score;
                    $public[$id][$pid.'.'.$component] = $score;
                    if ($weight > 0) {
                        $weighted[$component] = [$score, $weight];
                    }
                }
                foreach ($dependencies->get($pid, []) as $source) {
                    $weighted['period:'.$source->source_id] = [$values[$id][$source->source_id] ?? null, $source->weight];
                }
                $exact = $this->weighted($weighted, $group);
                if ($pid === $breakdownPeriodId) {
                    $periodBreakdown[$id] = array_map(static function (array $source): array {
                        [$score, $weight] = $source;

                        return [
                            'score' => $score,
                            'weight' => $weight,
                            'points' => $score === null ? null : bcdiv(bcmul((string) $score, (string) $weight, 6), '100', 8),
                        ];
                    }, $weighted);
                }
                $values[$id][$pid] = $exact;
                if ($group->grading_scale !== null || $period->uses_grade_plan) {
                    $record->final_grade = $exact;
                } elseif (! $period->hasCompleteManualGrades($components)) {
                    $record->final_grade = null;
                }
                $public[$id][$pid.'.period'] = $record->final_grade;
                $rows[$pid][$id] = $record;
                if ($period->group_final_weight !== null) {
                    $groupValues[] = [$exact, $period->group_final_weight];
                }
            }
            $finals[$id] = $group->grading_scale !== null ? $this->weighted($groupValues, $group) : null;
            $public[$id]['group'] = $finals[$id];
        }

        return ['rows' => $rows, 'finals' => $finals, 'published' => $public, 'periodBreakdown' => $periodBreakdown,
            'weightedPeriods' => $byPeriod->keys()->all()];
    }

    /**
     * Compara las entregas de cada estudiante con todos los exámenes del periodo.
     *
     * @param list<int> $studentIds
     * @return array{requiredCount: int, missingByStudent: array<int, list<string>>, suggestedScores: Collection<int, float>}
     */
    public function examDeliveryProgress(Group $group, EvaluationPeriod $period, array $studentIds, bool $weightedExams = false): array
    {
        $applications = DB::table('acs_exam_group as a')
            ->join('acs_exams as e', 'e.id', '=', 'a.exam_id')
            ->leftJoin('acs_period_exam_weights as w', 'w.application_id', '=', 'a.id')
            ->where('a.group_id', $group->id)->where('e.teacher_id', $group->teacher_id);

        if ($weightedExams) {
            $applications->where('w.period_id', $period->id);
        } elseif ($period->uses_exam_selection) {
            $applications->join('acs_period_required_exams as r', 'r.application_id', '=', 'a.id')
                ->where('r.period_id', $period->id);
        } else {
            // Los periodos sin selección mantienen la regla por fecha programada.
            $applications->whereNull('w.period_id')->where('e.is_published', true)
                ->where('e.starts_at', '>=', $period->starts_at->copy()->startOfDay())
                ->where('e.starts_at', '<', $period->ends_at->copy()->addDay()->startOfDay());
        }

        $exams = $applications->orderBy('e.starts_at')->orderBy('e.id')
            ->get(['e.id', 'e.title', 'e.grading_scale'])->keyBy('id');
        $submissions = $this->submittedAttempts($group, $studentIds, $exams->keys())
            ->get(['student_id', 'exam_id', 'final_score'])->groupBy('student_id')
            ->map(fn (Collection $attempts) => $attempts->keyBy('exam_id'));

        $missing = [];
        $suggested = collect();
        $canSuggest = ! $weightedExams && $group->grading_scale === null && $exams->isNotEmpty()
            && $exams->every(fn ($exam) => $exam->grading_scale === '0-100');

        foreach ($studentIds as $studentId) {
            $delivered = $submissions->get($studentId, collect());
            $missing[$studentId] = $exams->diffKeys($delivered)->pluck('title')->all();

            if ($canSuggest && $missing[$studentId] === [] && $delivered->every(fn ($attempt) => $attempt->final_score !== null)) {
                $suggested->put($studentId, round((float) $delivered->avg('final_score'), 2));
            }
        }

        return ['requiredCount' => $exams->count(), 'missingByStudent' => $missing, 'suggestedScores' => $suggested];
    }

    /** @return Builder<StudentAttempt> */
    private function submittedAttempts(Group $group, array|Collection $studentIds, array|Collection $examIds): Builder
    {
        return StudentAttempt::current()->where('group_id', $group->id)
            ->whereIn('student_id', $studentIds)->whereIn('exam_id', $examIds)
            ->where('status', 'submitted')->whereNotNull('submitted_at');
    }

    /**
     * Combina los resultados de las aplicaciones seleccionadas en la escala del grupo.
     *
     * @param Collection $apps Aplicaciones y pesos asignados.
     * @param Collection $attempts Intentos actuales indexados por examen.
     * @param Group $group Grupo al que pertenece la operación.
     * @return ?string Calificación exacta, o nulo si queda pendiente.
     */
    private function examScore(Collection $apps, Collection $attempts, Group $group): ?string
    {
        if ($group->grading_scale === null) {
            return null;
        }
        $weighted = [];
        foreach ($apps as $app) {
            $score = $attempts->get($app->exam_id)?->final_score;
            if ($score === null) {
                return null;
            }
            if ($app->grading_scale !== $group->grading_scale) {
                $score = $group->grading_scale === '0-100' ? bcmul($score, '10', 3) : bcdiv($score, '10', 3);
            }
            // Una conversión que requiere elegir un redondeo no se publica como definitiva.
            if (! $this->representable($score, $group)) {
                return null;
            }
            $weighted[] = [$score, $app->weight];
        }

        return $this->weighted($weighted, $group);
    }

    /**
     * Aplica cada porcentaje y devuelve un resultado solo si tiene representación exacta.
     *
     * @param array $items Pares de calificación y porcentaje.
     * @param Group $group Grupo al que pertenece la operación.
     * @return ?string Calificación exacta, o nulo si queda pendiente.
     */
    private function weighted(array $items, Group $group): ?string
    {
        $total = '0';
        $weights = '0';
        foreach ($items as [$score, $weight]) {
            if ($score === null) {
                return null;
            }
            $weights = bcadd($weights, (string) $weight, 4);
            $total = bcadd($total, bcmul((string) $score, (string) $weight, 6), 6);
        }
        if (bccomp($weights, '100', 4) !== 0) {
            return null;
        }
        $result = bcdiv($total, '100', 8);

        return $this->representable($result, $group) ? bcadd($result, '0', 2) : null;
    }

    /**
     * Comprueba el rango y la precisión del valor en la escala del grupo.
     *
     * @param string $score Calificación que se contrasta con la escala.
     * @param Group $group Grupo al que pertenece la operación.
     * @return bool Resultado de la comprobación.
     */
    private function representable(string $score, Group $group): bool
    {
        $precision = $group->grading_scale === '0-100' ? 0 : 2;

        $maximum = $group->grading_scale === '0-10' ? '10' : '100';

        return bccomp($score, '0', 8) >= 0 && bccomp($score, $maximum, 8) <= 0
            && bccomp($score, bcadd($score, '0', $precision), 8) === 0;
    }

    /**
     * Registra por estudiante los nuevos valores de calificaciones publicadas que cambiaron.
     *
     * @param array $before Valores publicados antes de la operación.
     * @param array $after Valores publicados tras la operación.
     * @return void
     */
    public function notifyChanges(array $before, array $after): void
    {
        foreach ($after as $studentId => $grades) {
            foreach ($grades as $key => $value) {
                $old = $before[$studentId][$key] ?? null;
                if ($old !== null && $value !== null && bccomp((string) $old, (string) $value, 2) !== 0) {
                    DB::table('acs_grade_notices')->insert(['student_id' => $studentId, 'new_grade' => $value]);
                }
            }
        }
    }
}
