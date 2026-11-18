<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        RegisterTeacherRequest.php
 * Ubicación:      app/Http/Requests/RegisterTeacherRequest.php
 * Requerimiento:  RF-AUT-001, RW-03, ADR-057 (Registro y Acreditación Híbrida Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Valida el alta de una cuenta docente con acreditación híbrida
 *                 obligatoria: canje de código institucional vigente o carga de
 *                 comprobante documental privado (PDF/JPG/PNG <= 5 MB) junto con
 *                 institución educativa y cédula profesional SEP opcional.
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Models\TeacherInvitationCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Form Request de registro de docente con acreditación híbrida (MPW-V2 Tabla 7 y 17).
 *
 * @package App\Http\Requests
 */
class RegisterTeacherRequest extends FormRequest
{
    /**
     * Autoriza la petición pública de registro docente.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para identidad personal y acreditación docente híbrida.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', 'regex:/\A(?=.*\p{L})[\p{L}\p{M} ]+\z/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/\A(?=.*\p{L})[\p{L}\p{M} ]+\z/u'],
            'email' => ['required', 'email', 'max:255', 'unique:acs_users,email'],
            'phone' => ['required', 'regex:/^[0-9]{10}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accepts_privacy_notice' => ['accepted'],
            'invitation_code' => ['nullable', 'string', 'max:32'],
            'institution_name' => ['required_without:invitation_code', 'nullable', 'string', 'max:150'],
            'educational_level' => ['nullable', 'string', 'in:secundaria,media_superior,superior,capacitacion,otro'],
            'professional_license' => ['nullable', 'string', 'regex:/^[0-9]{7,8}$/'],
            'credential_type' => ['nullable', 'string', 'in:institutional_id,employment_letter,official_schedule'],
            'verification_document' => [
                'required_without:invitation_code',
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:5120',
            ],
        ];
    }

    /**
     * Mensajes de validación normalizados conforme a MPW-V2 §8.11 / Tabla 17.
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

            'email.required' => 'El campo correo electrónico es obligatorio',
            'email.email' => 'El correo electrónico no tiene un formato válido',
            'email.max' => 'El correo electrónico no debe exceder los 255 caracteres',
            'email.unique' => 'Este correo electrónico ya está registrado',

            'phone.required' => 'El campo teléfono es obligatorio',
            'phone.regex' => 'El teléfono debe contener 10 dígitos',

            'password.required' => 'El campo contraseña es obligatorio',
            'password.string' => 'El campo contraseña no tiene un formato válido',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Los campos contraseña y confirmar contraseña deben coincidir',

            'accepts_privacy_notice.accepted' => 'Debes aceptar el aviso de privacidad para continuar',

            'invitation_code.string' => 'El código de invitación no tiene un formato válido',
            'invitation_code.max' => 'El código de invitación no debe exceder los 32 caracteres',

            'institution_name.required_without' => 'El campo institución educativa es obligatorio',
            'institution_name.string' => 'El campo institución educativa no tiene un formato válido',
            'institution_name.max' => 'El campo institución educativa no debe exceder los 150 caracteres',

            'educational_level.string' => 'El nivel educativo no tiene un formato válido',
            'educational_level.in' => 'La opción seleccionada en nivel educativo no es válida',

            'professional_license.string' => 'La cédula profesional no tiene un formato válido',
            'professional_license.regex' => 'La cédula profesional no tiene un formato válido',

            'credential_type.string' => 'El tipo de comprobante no tiene un formato válido',
            'credential_type.in' => 'La opción seleccionada en tipo de comprobante no es válida',

            'verification_document.required_without' => 'El campo comprobante de acreditación docente es obligatorio',
            'verification_document.uploaded' => 'No se pudo cargar el comprobante de acreditación docente, verifica que no supere 5 MB',
            'verification_document.file' => 'El comprobante de acreditación docente no tiene un formato válido',
            'verification_document.mimes' => 'El comprobante de acreditación docente no tiene un formato válido',
            'verification_document.mimetypes' => 'El comprobante de acreditación docente no tiene un formato válido',
            'verification_document.max' => 'El tamaño del comprobante de acreditación docente debe ser menor o igual que 5 MB',
        ];
    }

    /**
     * Valida la vigencia y cupo del código institucional cuando el docente lo
     * proporciona y el resto del formulario pasó sin errores (previene oráculo).
     *
     * @param Validator $validator Instancia del validador en curso.
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rawCode = $this->input('invitation_code');
            if (! is_string($rawCode) || trim($rawCode) === '') {
                return;
            }

            $normalized = TeacherInvitationCode::normalizeCode($rawCode);
            $code = TeacherInvitationCode::where('code', $normalized)->first();

            if ($code === null || ! $code->isUsable()) {
                $validator->errors()->add(
                    'invitation_code',
                    'El código de invitación no es válido, expiró o alcanzó su límite de usos',
                );
            }
        });
    }

    /**
     * Normaliza campos de acreditación docente antes de ejecutar las reglas.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('invitation_code') && is_string($this->input('invitation_code'))) {
            $normalizedCode = TeacherInvitationCode::normalizeCode($this->input('invitation_code'));
            $payload['invitation_code'] = $normalizedCode !== '' ? $normalizedCode : null;
        }

        if ($this->has('professional_license') && is_string($this->input('professional_license'))) {
            $license = trim($this->input('professional_license'));
            $payload['professional_license'] = $license !== '' ? $license : null;
        }

        if ($this->has('institution_name') && is_string($this->input('institution_name'))) {
            $institution = trim($this->input('institution_name'));
            $payload['institution_name'] = $institution !== '' ? $institution : null;
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
