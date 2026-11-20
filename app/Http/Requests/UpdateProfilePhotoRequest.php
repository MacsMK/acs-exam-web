<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:        UpdateProfilePhotoRequest.php
 * Ubicación:      app/Http/Requests/UpdateProfilePhotoRequest.php
 * Requerimiento:  RF-AUT-014 (Actualización de la fotografía de perfil)
 * Actividad:      ACT-WEB-054
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Fecha Creación: 19/11/2026
 * Descripción:    Valida la nueva fotografía del estudiante: JPG o PNG de
 *                 hasta 5 MB, con mensajes conforme a MPW-V2 Tabla 17.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'student';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'El campo fotografía es obligatorio',
            'photo.file' => 'La fotografía no tiene un formato válido',
            'photo.mimes' => 'La fotografía debe ser una imagen JPG o PNG',
            'photo.max' => 'La fotografía no debe exceder los 5 MB',
        ];
    }
}
