<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        PrivacyNotice.php
 * Ubicación:      app/Models/PrivacyNotice.php
 * Requerimiento:  RF-AUT (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-002)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_privacy_notices (MBD-V4 Tabla 29).
 *                 Expuesto por api.v1.privacy.show (MPM-V2 §6.6.3).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versión del aviso de privacidad institucional.
 *
 * @package App\Models
 */
class PrivacyNotice extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_privacy_notices';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'version',
        'content',
        'published_at',
    ];

    /**
     * Conversión de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * Aceptaciones registradas para esta versión del aviso.
     *
     * @return HasMany<PrivacyAcceptance>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(PrivacyAcceptance::class);
    }
}
