<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamUpdateRequest.php
 * Ubicación:      app/Http/Requests/ExamUpdateRequest.php
 * Requerimiento:  RW-19..27 (Edición Segura de Examen, MPW-V3 §6.3, Tabla 18)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    FormRequest para actualización de metadatos, ventana y grupos
 *                 de un examen docente, protegiendo escala, estructura e IDOR.
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesExamWindowChanges;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Scopes\TenantTeacherScope;

/**
 * Validación formal de edición de examen docente.
 */
class ExamUpdateRequest extends FormRequest
{
    use ValidatesExamWindowChanges;

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
            'randomize_question_order' => ['sometimes', 'boolean'],
            'result_visibility_mode' => ['required', 'in:final_score,estimated_score,objective_only_pending,hidden'],
            'require_webcam' => ['nullable', 'boolean'],
            'max_allowed_incidents' => ['required', 'integer', 'min:0', 'max:' . Exam::MAX_ALLOWED_INCIDENTS],
            'starts_at' => ['bail', 'required', 'date'],
            'ends_at' => ['bail', 'required', 'date', 'after:starts_at'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('acs_groups', 'id')->where('teacher_id', $teacherId),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $exam = $this->route('exam');
            $teacherId = $this->user()?->id;

            if (! ($exam instanceof Exam) || $teacherId === null) {
                return;
            }

            $this->validateExamWindowChanges($validator, $exam);

            if ($exam->is_published) {
                if ((string) $this->input('grading_scale') !== (string) $exam->grading_scale) {
                    $validator->errors()->add(
                        'grading_scale',
                        'No puedes cambiar la escala de calificación de un examen publicado'
                    );
                }

                if ($this->has('randomize_question_order')
                    && $this->boolean('randomize_question_order') !== (bool) $exam->randomize_question_order
                ) {
                    $validator->errors()->add(
                        'randomize_question_order',
                        'No puedes cambiar el orden estructural de un examen publicado'
                    );
                }
            }

            if ($exam->studentAttempts()->exists()
                && (string) $this->input('grading_scale') !== (string) $exam->grading_scale
            ) {
                $validator->errors()->add(
                    'grading_scale',
                    'No puedes cambiar la escala de calificación porque el examen ya tiene intentos registrados'
                );
            }

            $submittedGroups = $this->input('group_ids');
            if (is_array($submittedGroups) && $submittedGroups !== []) {
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
            'description.string' => 'La descripción debe ser una cadena de texto válida',
            'grading_scale.required' => 'El campo escala de calificación es obligatorio',
            'grading_scale.in' => 'La opción seleccionada en escala de calificación no es válida',
            'randomize_question_order.boolean' => 'El campo aleatorizar orden debe ser verdadero o falso',
            'result_visibility_mode.required' => 'El campo visibilidad del resultado es obligatorio',
            'result_visibility_mode.in' => 'La opción seleccionada en visibilidad del resultado no es válida',
            'require_webcam.boolean' => 'El campo cámara requerida debe ser verdadero o falso',
            'max_allowed_incidents.required' => 'El campo incidentes máximos permitidos es obligatorio',
            'max_allowed_incidents.integer' => 'El número de incidentes máximos permitidos debe ser un entero',
            'max_allowed_incidents.min' => 'El número de incidentes máximos permitidos debe ser mayor o igual que 0',
            'max_allowed_incidents.max' => 'El número de incidentes permitidos no debe exceder :max',
            'starts_at.required' => 'El campo fecha de inicio es obligatorio',
            'starts_at.date' => 'La fecha de inicio no tiene un formato válido',
            'ends_at.required' => 'El campo fecha de cierre es obligatorio',
            'ends_at.date' => 'La fecha de cierre no tiene un formato válido',
            'ends_at.after' => 'La fecha de cierre debe ser posterior a la fecha de inicio',
            'group_ids.array' => 'Los grupos seleccionados deben enviarse como un arreglo',
            'group_ids.*.integer' => 'El identificador de grupo debe ser un número entero',
            'group_ids.*.distinct' => 'No puedes asignar el mismo grupo más de una vez',
            'group_ids.*.exists' => 'Alguno de los grupos seleccionados no existe o no pertenece a tu catálogo docente',
        ];
    }
}
