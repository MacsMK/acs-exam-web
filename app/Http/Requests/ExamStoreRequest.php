<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExamStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'teacher';
    }

    protected function prepareForValidation(): void
    {
        $zonaHoraria = 'America/Mexico_City';

        $fechas = [];

        foreach (['starts_at', 'ends_at'] as $campo) {
            $valor = $this->input($campo);

            if (!is_string($valor) || $valor === '') {
                continue;
            }

            try {
                $fecha = \Carbon\Carbon::createFromFormat(
                    '!Y-m-d\TH:i',
                    $valor,
                    $zonaHoraria
                );

                if ($fecha->format('Y-m-d\TH:i') !== $valor) {
                    continue;
                }

                $fechas[$campo] = $fecha
                    ->utc()
                    ->format('Y-m-d\TH:i');
            } catch (\Exception $e) {
                // La validación rechazará las fechas inválidas.
            }
        }

        $this->merge($fechas);
    }
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],

            'starts_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
                'after:now',
            ],

            'ends_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
                'after:starts_at',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Escribe el título del examen.',
            'title.max' => 'El título no puede superar los 150 caracteres.',

            'starts_at.required' => 'Selecciona la fecha y hora de inicio del examen.',
            'starts_at.date_format' => 'La fecha u hora de inicio no tiene un formato válido.',
            'starts_at.after' => 'La fecha y hora de inicio deben ser posteriores al momento actual.',

            'ends_at.required' => 'Selecciona la fecha y hora de término del examen.',
            'ends_at.date_format' => 'La fecha u hora de término no tiene un formato válido.',
            'ends_at.after' => 'La fecha y hora de término deben ser posteriores al inicio del examen.',
        ];
    }
}