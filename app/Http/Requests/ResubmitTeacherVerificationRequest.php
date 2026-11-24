<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ResubmitTeacherVerificationRequest.php
 * Ubicación:      app/Http/Requests/ResubmitTeacherVerificationRequest.php
 * Requerimiento:  RF-AUT-001, ADR-057 (Reenvío de Acreditación Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Valida el reenvío de comprobante documental de un docente
 *                 cuya solicitud de verificación fue previamente rechazada.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para el reenvío de comprobante docente tras un rechazo.
 *
 * @package App\Http\Requests
 */
class ResubmitTeacherVerificationRequest extends FormRequest
{
    /**
     * Autoriza exclusivamente a docentes con solicitud de acreditación rechazada.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role === 'teacher'
            && $user->isRejectedTeacherVerification();
    }

    /**
     * Reglas de validación del nuevo comprobante y metadatos institucionales.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['required', 'string', 'max:150'],
            'educational_level' => ['nullable', 'string', 'in:secundaria,media_superior,superior,capacitacion,otro'],
            'professional_license' => ['nullable', 'string', 'regex:/^[0-9]{7,8}$/'],
            'credential_type' => ['nullable', 'string', 'in:institutional_id,employment_letter,official_schedule'],
            'verification_document' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:5120',
            ],
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
            'institution_name.required' => 'El campo institución educativa es obligatorio',
            'institution_name.string' => 'El campo institución educativa no tiene un formato válido',
            'institution_name.max' => 'El campo institución educativa no debe exceder los 150 caracteres',

            'educational_level.string' => 'El nivel educativo no tiene un formato válido',
            'educational_level.in' => 'La opción seleccionada en nivel educativo no es válida',

            'professional_license.string' => 'La cédula profesional no tiene un formato válido',
            'professional_license.regex' => 'La cédula profesional no tiene un formato válido',

            'credential_type.string' => 'El tipo de comprobante no tiene un formato válido',
            'credential_type.in' => 'La opción seleccionada en tipo de comprobante no es válida',

            'verification_document.required' => 'El campo comprobante de acreditación docente es obligatorio',
            'verification_document.uploaded' => 'No se pudo cargar el comprobante de acreditación docente, verifica que no supere 5 MB',
            'verification_document.file' => 'El comprobante de acreditación docente no tiene un formato válido',
            'verification_document.mimes' => 'El comprobante de acreditación docente no tiene un formato válido',
            'verification_document.mimetypes' => 'El comprobante de acreditación docente no tiene un formato válido',
            'verification_document.max' => 'El tamaño del comprobante de acreditación docente debe ser menor o igual que 5 MB',
        ];
    }

    /**
     * Normaliza campos de texto recortando espacios y convirtiendo vacíos a null.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('institution_name') && is_string($this->input('institution_name'))) {
            $payload['institution_name'] = trim($this->input('institution_name'));
        }

        if ($this->has('professional_license') && is_string($this->input('professional_license'))) {
            $license = trim($this->input('professional_license'));
            $payload['professional_license'] = $license !== '' ? $license : null;
        }

        if ($this->has('educational_level') && is_string($this->input('educational_level'))) {
            $level = trim($this->input('educational_level'));
            $payload['educational_level'] = $level !== '' ? $level : null;
        }

        if ($this->has('credential_type') && is_string($this->input('credential_type'))) {
            $type = trim($this->input('credential_type'));
            $payload['credential_type'] = $type !== '' ? $type : null;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
