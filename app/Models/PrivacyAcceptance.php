<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        PrivacyAcceptance.php
 * Ubicación:      app/Models/PrivacyAcceptance.php
 * Requerimiento:  RF-AUT (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-003)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_privacy_acceptances (MBD-V4
 *                 Tabla 29). Registra que un usuario aceptó una versión
 *                 específica del aviso de privacidad
 *                 (api.v1.privacy.accept, MPM-V2 §6.6.3).
 *                 CAMBIO v1.1.0: se agrega ip_address (ver migración
 *                 BD01-2026-003 v1.1.0).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aceptación de un usuario a una versión del aviso de privacidad.
 *
 * @package App\Models
 */
class PrivacyAcceptance extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_privacy_acceptances';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'privacy_notice_id',
        'accepted_at',
        'ip_address',
    ];

    /**
     * Conversión de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Usuario que aceptó el aviso.
     *
     * @return BelongsTo<User, PrivacyAcceptance>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Versión del aviso aceptada.
     *
     * @return BelongsTo<PrivacyNotice, PrivacyAcceptance>
     */
    public function privacyNotice(): BelongsTo
    {
        return $this->belongsTo(PrivacyNotice::class);
    }
}
