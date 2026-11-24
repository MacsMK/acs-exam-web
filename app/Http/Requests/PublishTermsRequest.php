<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        PublishTermsRequest.php
 * Ubicación:      app/Http/Requests/PublishTermsRequest.php
 * Requerimiento:  RF-ADM-015
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Valida la versión, el texto y la entrada en vigor de los términos.
 * ============================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishTermsRequest extends FormRequest
{
    /**
     * Comprueba el rol y el ámbito de la solicitud.
     *
     * @return bool Resultado de la comprobación.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'super_admin';
    }

    /**
     * Define las reglas de formato y contenido de la solicitud.
     *
     * @return array Datos estructurados de la operación.
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:60', 'unique:acs_terms_versions,version'],
            'content' => ['required', 'string'],
            'effective_at' => ['required', 'date_format:Y-m-d,Y-m-d\TH:i', 'unique:acs_terms_versions,effective_at'],
        ];
    }
}
