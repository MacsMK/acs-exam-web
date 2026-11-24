<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        UpdateTeacherProfileRequest.php
 * Ubicación:      app/Http/Requests/UpdateTeacherProfileRequest.php
 * Requerimiento:  RW-03, RW-04 (Gestión de Perfil y Credenciales Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Valida la actualización de datos personales, teléfono y
 *                 contraseña del usuario docente autenticado con mensajes
 *                 normativos en español (MPW-V3 §6.3, §8.11, Tabla 18).
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Form Request para la actualización del perfil y credenciales del docente.
 *
 * @package App\Http\Requests
 */
class UpdateTeacherProfileRequest extends FormRequest
{
    /**
     * Autoriza exclusivamente a usuarios autenticados.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Reglas de validación para datos personales y cambio opcional de contraseña.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', 'regex:/\A(?=.*\p{L})[\p{L}\p{M} ]+\z/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/\A(?=.*\p{L})[\p{L}\p{M} ]+\z/u'],
            'phone' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * Mensajes de validación normalizados conforme a MPW-V3 §8.11 / Tabla 18.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'El campo nombre es obligatorio',
            'first_name.string' => 'El nombre no tiene un formato válido',
            'first_name.max' => 'El nombre no debe exceder los 100 caracteres',
            'first_name.regex' => 'El nombre no tiene un formato válido',

            'last_name.required' => 'El campo apellidos es obligatorio',
            'last_name.string' => 'El campo apellidos no tiene un formato válido',
            'last_name.max' => 'El campo apellidos no debe exceder los 100 caracteres',
            'last_name.regex' => 'El campo apellidos no tiene un formato válido',

            'phone.required' => 'El campo teléfono es obligatorio',
            'phone.string' => 'El teléfono no tiene un formato válido',
            'phone.regex' => 'El teléfono debe contener 10 dígitos',

            'current_password.required_with' => 'El campo contraseña actual es obligatorio',
            'current_password.current_password' => 'La contraseña actual no es correcta',

            'new_password.confirmed' => 'Los campos nueva contraseña y confirmar contraseña deben coincidir',
            'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres',
        ];
    }

    /**
     * Recorta espacios extremos en nombre, apellidos y teléfono antes de validar.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['first_name', 'last_name', 'phone'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $payload[$field] = trim($this->input($field));
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
