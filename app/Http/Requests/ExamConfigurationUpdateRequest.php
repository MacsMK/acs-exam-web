<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamConfigurationUpdateRequest.php
 * Ubicación:      app/Http/Requests/ExamConfigurationUpdateRequest.php
 * Requerimiento:  RW-17..20, RW-25, MPW-V2 §6.3, Tabla 30
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.2.0
 * Fecha Creación: 09/11/2026
 * Descripción:    Validación formal para la configuración de tiempos,
 *                 ponderaciones decimales exactas (100.00), ventana horaria,
 *                 escala inmutable con intentos y aislamiento multi-docente
 *                 en group_ids [CRÍTICA-01] (ADR-017, ADR-056).
 * ============================================================================
 */

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Scopes\TenantTeacherScope;

class ExamConfigurationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || $user->role !== 'teacher') {
            return false;
        }

        $exam = $this->route('exam');
        if ($exam instanceof Exam) {
            return (int) $exam->teacher_id === (int) $user->id;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teacherId = $this->user()?->id;

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'grading_scale' => ['required', 'in:0-10,0-100'],
            'result_visibility_mode' => [
                'required',
                'in:final_score,estimated_score,objective_only_pending,hidden',
            ],
            'max_allowed_incidents' => ['required', 'integer', 'min:0'],
            'randomize_question_order' => ['required', 'boolean'],
            'require_webcam' => ['sometimes', 'nullable', 'boolean'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('acs_groups', 'id')->where('teacher_id', $teacherId),
            ],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.points_weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'questions.*.time_limit_seconds' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $exam = $this->route('exam');
            $teacherId = $this->user()?->id;

            // 1. Validar suma exacta de ponderaciones (100.00 ± 0.01)
            $questions = $this->input('questions');
            if (is_array($questions) && $questions !== []) {
                $totalWeight = '0';
                $allNumeric = true;

                foreach ($questions as $item) {
                    if (! is_array($item) || ! isset($item['points_weight']) || ! is_numeric($item['points_weight'])) {
                        $allNumeric = false;
                        break;
                    }

                    $totalWeight = bcadd($totalWeight, number_format((float) $item['points_weight'], 2, '.', ''), 2);
                }

                if ($allNumeric && abs((float) $totalWeight - 100.0) > 0.01) {
                    $validator->errors()->add(
                        'questions',
                        'La suma de las ponderaciones debe ser exactamente 100.00 puntos'
                    );
                }
            }

            if (! ($exam instanceof Exam)) {
                return;
            }

            // 2. Impedir cambio de escala si ya existen intentos registrados
            if ($exam->studentAttempts()->exists()
                && (string) $this->input('grading_scale') !== (string) $exam->grading_scale
            ) {
                $validator->errors()->add(
                    'grading_scale',
                    'No puedes cambiar la escala de calificación porque el examen ya tiene intentos registrados'
                );
            }

            // 3. Validar coherencia de ventana horaria (starts_at < ends_at)
            $effectiveStart = $this->filled('starts_at') ? $this->input('starts_at') : $exam->starts_at;
            $effectiveEnd = $this->filled('ends_at') ? $this->input('ends_at') : $exam->ends_at;

            if ($effectiveStart !== null && $effectiveEnd !== null) {
                try {
                    $startCarbon = $effectiveStart instanceof Carbon ? $effectiveStart : Carbon::parse((string) $effectiveStart);
                    $endCarbon = $effectiveEnd instanceof Carbon ? $effectiveEnd : Carbon::parse((string) $effectiveEnd);

                    if ($endCarbon->lte($startCarbon)) {
                        $validator->errors()->add(
                            'ends_at',
                            'La fecha de cierre debe ser posterior a la fecha de inicio'
                        );
                    }
                } catch (\Throwable) {
                    // La regla 'date' reportará formato inválido.
                }
            }

            // 4. Validar que los grupos seleccionados estén abiertos o ya asignados al examen
            $submittedGroups = $this->input('group_ids');
            if ($teacherId !== null && is_array($submittedGroups) && $submittedGroups !== []) {
                $assignedIds = $exam->groups()->pluck('acs_groups.id')->map(static fn (mixed $id): int => (int) $id)->all();
                $numericIds = array_values(array_filter(array_map('intval', $submittedGroups), static fn (int $id): bool => $id > 0));

                $groups = Group::query()
                    ->withoutGlobalScope(TenantTeacherScope::class)
                    ->where('teacher_id', $teacherId)
                    ->whereIn('id', $numericIds)
                    ->get()
                    ->keyBy('id');

                foreach ($submittedGroups as $index => $rawGroupId) {
                    if (! is_numeric($rawGroupId)) {
                        continue;
                    }

                    $groupId = (int) $rawGroupId;
                    $group = $groups->get($groupId);

                    if ($group !== null && $group->is_closed && ! in_array($groupId, $assignedIds, true)) {
                        $validator->errors()->add(
                            "group_ids.{$index}",
                            'No puedes asignar un grupo cerrado que no pertenecía previamente al examen'
                        );
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El campo título es obligatorio',
            'title.string' => 'El título debe ser una cadena de texto válida',
            'title.max' => 'El título no debe exceder los 150 caracteres',
            'grading_scale.required' => 'El campo escala de calificación es obligatorio',
            'grading_scale.in' => 'La opción seleccionada en escala de calificación no es válida',
            'result_visibility_mode.required' => 'El campo visibilidad de resultados es obligatorio',
            'result_visibility_mode.in' => 'La opción seleccionada en visibilidad de resultados no es válida',
            'max_allowed_incidents.required' => 'El campo incidentes permitidos es obligatorio',
            'max_allowed_incidents.integer' => 'El número de incidentes permitidos debe ser un entero',
            'max_allowed_incidents.min' => 'El número de incidentes permitidos debe ser mayor o igual que 0',
            'randomize_question_order.required' => 'El campo aleatorizar orden es obligatorio',
            'randomize_question_order.boolean' => 'El campo aleatorizar orden debe ser verdadero o falso',
            'require_webcam.boolean' => 'El campo cámara requerida debe ser verdadero o falso',
            'starts_at.required' => 'El campo fecha de inicio es obligatorio',
            'starts_at.date' => 'La fecha de inicio no tiene un formato válido',
            'ends_at.required' => 'El campo fecha de cierre es obligatorio',
            'ends_at.date' => 'La fecha de cierre no tiene un formato válido',
            'group_ids.array' => 'Los grupos seleccionados deben enviarse como un arreglo',
            'group_ids.*.integer' => 'El identificador de grupo debe ser un número entero',
            'group_ids.*.distinct' => 'No puedes asignar el mismo grupo más de una vez',
            'group_ids.*.exists' => 'Alguno de los grupos seleccionados no existe o no pertenece a tu catálogo docente',
            'questions.required' => 'El examen debe incluir al menos una pregunta para configurarse',
            'questions.array' => 'Las preguntas configuradas deben enviarse como un arreglo',
            'questions.min' => 'El examen debe incluir al menos una pregunta para configurarse',
            'questions.*.points_weight.required' => 'La ponderación por pregunta es obligatoria',
            'questions.*.points_weight.numeric' => 'La ponderación debe ser un valor numérico',
            'questions.*.points_weight.min' => 'La ponderación mínima por reactivo es de 0.01 puntos',
            'questions.*.points_weight.max' => 'La ponderación máxima por reactivo es de 100 puntos',
            'questions.*.time_limit_seconds.required' => 'El tiempo límite por pregunta es obligatorio',
            'questions.*.time_limit_seconds.integer' => 'El tiempo límite por pregunta debe ser un número entero',
            'questions.*.time_limit_seconds.min' => 'El tiempo asignado debe ser mayor que 0 segundos',
        ];
    }
}
