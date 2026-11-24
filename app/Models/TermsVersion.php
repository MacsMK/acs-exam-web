<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TermsVersion.php
 * Ubicación:      app/Models/TermsVersion.php
 * Requerimiento:  RF-ADM-015
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Conserva las versiones publicadas y resuelve el texto vigente.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermsVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'acs_terms_versions';
    protected $fillable = ['version', 'content', 'effective_at', 'published_by'];
    protected $casts = ['effective_at' => 'datetime'];

    /**
     * Selecciona la versión con la entrada en vigor más reciente ya alcanzada.
     *
     * @return ?self Versión vigente, o nulo para el texto original.
     */
    public static function current(): ?self
    {
        return self::where('effective_at', '<=', now())->orderByDesc('effective_at')->first();
    }
}
