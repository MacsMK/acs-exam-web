<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Carbon;

trait NormalizesExamWindow
{
    protected function passedValidation(): void
    {
        $timezone = (string) config('app.timezone');

        // Las columnas de la ventana guardan la hora de la aplicación sin offset.
        foreach (['starts_at', 'ends_at'] as $attribute) {
            $submitted = $this->validator->getValue($attribute);
            if ($submitted !== null) {
                $date = Carbon::parse((string) $submitted, $timezone)->setTimezone($timezone);
                $this->validator->setValue($attribute, $date->toDateTimeString());
            }
        }
    }
}
