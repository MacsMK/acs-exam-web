<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        GradebookEntryService.php
 * Ubicación:      app/Services/GradebookEntryService.php
 * Requerimiento:  RF-CAL-013, RF-CAL-023, RF-REP-017
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Guarda las capturas, motivos de corrección y avisos asociados a una sábana.
 * ============================================================================
 */

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EvaluationPeriod;
use App\Models\Group;
use App\Models\StudentPeriodGrade;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradebookEntryService
{
    public const PAGE_SIZE = 40;

    /**
     * Recibe los servicios de dominio necesarios para la operación.
     *
     * @param GroupGradeResults $results Calculador de resultados del grupo.
     * @param GradeCalculatorService $gradeCalculator Calculador de fórmulas históricas.
     * @return void
     */
    public function __construct(
        private readonly GroupGradeResults $results,
        private readonly GradeCalculatorService $gradeCalculator,
    ) {
    }

    /**
     * Orden estable del padrón para la captura y su validación.
     */
    public function students(Group $group): BelongsToMany
    {
        return $group->students()->where('acs_users.role', 'student')
            ->orderBy('acs_users.last_name')
            ->orderBy('acs_users.first_name')
            ->orderBy('acs_users.id');
    }

    /**
     * Persiste las capturas y registra las correcciones y los avisos en una transacción.
     *
     * @param Group $group Grupo al que pertenece la operación.
     * @param EvaluationPeriod $period Periodo de evaluación, opcional en el plan del grupo.
     * @param array $input Valores validados del formulario.
     * @param int $actorId Identificador del docente que guarda el cambio.
     * @param int|null $page Página de la sábana enviada por el formulario.
     * @return void
     *
     * @throws ValidationException Si la captura requiere corregir valores o motivo.
     */
    public function save(Group $group, EvaluationPeriod $period, array $input, int $actorId, ?int $page = null): void
    {
        DB::transaction(function () use ($input, $actorId, $group, $period, $page): void {
            $validatedScale = $group->grading_scale;
            $group = Group::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $period->refresh();
            abort_unless($group->teacher_id === $actorId && $period->group_id === $group->id, 403);
            if (! $period->hasStarted()) {
                throw ValidationException::withMessages([
                    'grades' => 'Podrás guardar calificaciones a partir del ' . $period->starts_at->format('d/m/Y'),
                ]);
            }
            if ($validatedScale !== $group->grading_scale) {
                throw ValidationException::withMessages(['grades' => 'La escala de calificación cambió. Recarga la página antes de guardar']);
            }
            $ids = array_map('intval', array_column($input, 'student_id'));
            if ($page !== null) {
                $expectedIds = $this->students($group)->forPage($page, self::PAGE_SIZE)
                    ->pluck('acs_users.id')->map(fn ($id) => (int) $id)->all();
                if ($expectedIds !== $ids) {
                    throw ValidationException::withMessages(['grades' => 'La lista de estudiantes cambió o la captura llegó incompleta. Recarga la página antes de guardar.']);
                }
            } elseif ($group->students()->where('role', 'student')->whereIn('acs_users.id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['grades' => 'Solo puedes calificar a estudiantes inscritos en este grupo']);
            }

            $calculator = $this->results;
            $before = $calculator->calculate($group, publishedOnly: true);
            $weighted = in_array($period->id, $before['weightedPeriods'], true);
            $weights = $period->parsed_weights;
            $examDelivery = ! $weighted && ($weights['exams'] ?? 0) > 0
                ? $calculator->examDeliveryProgress($group, $period, $ids)
                : null;
            foreach ($input as $index => $row) {
                $grade = StudentPeriodGrade::firstOrNew(['evaluation_period_id' => $period->id, 'student_id' => $row['student_id']]);
                $changes = [];
                $components = [];
                $storedComments = $grade->manual_comments ?? [];
                $comments = $row['manual_comments'] ?? [];
                foreach (GradePlanService::COMPONENTS as $key) {
                    $field = $key.'_component';
                    $old = $grade->getAttribute($field);
                    if (($weights[$key] ?? 0) <= 0) {
                        $components[$key] = $old;
                        if (array_key_exists($field, $row) && ! $this->sameGrade($old, $row[$field])) {
                            throw ValidationException::withMessages(["grades.$index.$field" => 'La ponderación del rubro es 0 %. Su calificación registrada se conserva.']);
                        }
                        if (array_key_exists($key, $comments) && $comments[$key] !== ($storedComments[$key] ?? null)) {
                            throw ValidationException::withMessages(["grades.$index.manual_comments.$key" => 'La ponderación del rubro es 0 %. Su comentario registrado se conserva.']);
                        }
                        unset($comments[$key]);
                        continue;
                    }
                    if ($weighted && $key === 'exams') {
                        $components[$key] = $before['rows'][$period->id][$row['student_id']]->exams_component;
                        continue;
                    }
                    $value = array_key_exists($field, $row) ? $row[$field] : $old;
                    $changed = ! $this->sameGrade($old, $value);
                    if ($key === 'exams' && $changed && $examDelivery !== null && ($examDelivery['requiredCount'] === 0
                        || $examDelivery['missingByStudent'][$row['student_id']] !== [])) {
                        $noExamsMessage = $period->uses_exam_selection
                            ? 'Selecciona al menos un examen en Editar periodo para capturar la nota de Exámenes.'
                            : 'Publica un examen del grupo dentro del periodo o selecciónalo en Editar periodo.';
                        throw ValidationException::withMessages([
                            "grades.$index.$field" => $examDelivery['requiredCount'] === 0
                                ? $noExamsMessage
                                : 'El estudiante debe entregar todos los exámenes del periodo antes de registrar o corregir la nota.',
                        ]);
                    }
                    if ($old !== null && $changed) {
                        $changes[$key] = ['old' => $old, 'new' => $value];
                    }
                    $grade->setAttribute($field, $value);
                    $components[$key] = $value;
                }
                $reason = trim((string) ($row['reason'] ?? ''));
                if ($changes !== [] && $reason === '') {
                    throw ValidationException::withMessages(["grades.$index.reason" => 'Indica el motivo de la corrección']);
                }
                if ($weighted && array_key_exists('exams', $comments)) {
                    throw ValidationException::withMessages(["grades.$index.manual_comments.exams" => 'El rubro de exámenes utiliza aplicaciones ponderadas']);
                }
                $grade->manual_comments = array_replace($storedComments, $comments);
                if ($group->grading_scale === null && ! $period->uses_grade_plan) {
                    if ($period->is_valid_formula) {
                        $grade->final_grade = $period->hasCompleteManualGrades($components)
                            ? $this->gradeCalculator->calculate($period->formula, $components) : null;
                    } elseif ($grade->isDirty(array_map(fn ($key) => $key.'_component', GradePlanService::COMPONENTS))) {
                        throw ValidationException::withMessages(['grades' => 'Configura una fórmula válida del periodo antes de modificar las notas']);
                    }
                }
                $grade->save();
                foreach ($changes as $key => $change) {
                    AuditLog::create(['user_id' => $actorId, 'action' => 'grades.manual_corrected',
                        'auditable_type' => StudentPeriodGrade::class, 'auditable_id' => $grade->id,
                        'old_values' => ['component' => $key, 'score' => $change['old']],
                        'new_values' => ['component' => $key, 'score' => $change['new'], 'reason' => $reason]]);
                }
            }
            if ($group->grading_scale !== null || $period->uses_grade_plan) {
                $after = $calculator->calculate($group);
                foreach ($ids as $id) {
                    $derived = $after['rows'][$period->id][$id];
                    StudentPeriodGrade::whereKey($derived->id)->update(['final_grade' => $derived->final_grade]);
                }
            }
            if (! $group->is_closed) {
                $calculator->notifyChanges($before['published'], $calculator->calculate($group, publishedOnly: true)['published']);
            }
        });
    }

    private function sameGrade(int|float|string|null $stored, int|float|string|null $submitted): bool
    {
        if ($stored === null || $submitted === null) {
            return $stored === $submitted;
        }

        return bccomp((string) $stored, (string) $submitted, 2) === 0;
    }
}
