<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Exam;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

trait ValidatesExamWindowChanges
{
    use NormalizesExamWindow;

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        $data = parent::validationData();
        $exam = $this->route('exam');
        if (! ($exam instanceof Exam)) {
            return $data;
        }

        // datetime-local omite los segundos. Conserva el valor guardado si no se editó el minuto.
        foreach (['starts_at', 'ends_at'] as $attribute) {
            $saved = $exam->{$attribute};
            if ($saved !== null && ($data[$attribute] ?? null) === $saved->format('Y-m-d\TH:i')) {
                $data[$attribute] = $saved->toDateTimeString();
            }
        }

        return $data;
    }

    protected function validateExamWindowChanges(Validator $validator, Exam $exam): void
    {
        $now = now(config('app.timezone'));
        $changedStart = $this->changedWindowDate($validator, $exam, 'starts_at');
        if ($changedStart !== null && $exam->is_published && $exam->hasStartedWindow()) {
            $validator->errors()->add(
                'starts_at',
                'No puedes modificar la fecha de inicio de un examen cuya ventana ya comenzó'
            );
        } elseif ($changedStart?->lt($now->copy()->startOfMinute())) {
            $validator->errors()->add(
                'starts_at',
                'La fecha de inicio debe ser igual o posterior al minuto actual'
            );
        }

        if ($this->changedWindowDate($validator, $exam, 'ends_at')?->lte($now)) {
            $validator->errors()->add('ends_at', 'La fecha de cierre debe ser posterior a la hora actual');
        }
    }

    protected function submittedWindowDate(Validator $validator, string $attribute): ?Carbon
    {
        $value = $validator->getValue($attribute);

        return $value === null || $validator->errors()->has($attribute)
            ? null
            : Carbon::parse((string) $value, config('app.timezone'));
    }

    private function changedWindowDate(Validator $validator, Exam $exam, string $attribute): ?Carbon
    {
        $submitted = $this->submittedWindowDate($validator, $attribute);
        $saved = $exam->{$attribute};

        return $saved === null || $submitted?->ne($saved) ? $submitted : null;
    }
}
