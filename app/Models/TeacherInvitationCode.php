<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherInvitationCode.php
 * Ubicación:      app/Models/TeacherInvitationCode.php
 * Requerimiento:  RF-AUT / Verificación Híbrida de Identidad Docente
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 17/11/2026
 * Descripción:    Modelo Eloquent de acs_teacher_invitation_codes: códigos de
 *                 invitación institucional emitidos por el Super Admin para la
 *                 acreditación expedita de cuentas docentes con control de
 *                 vigencia, estado activo y límite de usos.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Código de invitación institucional para acreditación expedita de docentes.
 *
 * @package App\Models
 */
class TeacherInvitationCode extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V3 §3.3).
     *
     * @var string
     */
    protected $table = 'acs_teacher_invitation_codes';

    /**
     * Columnas asignables en masa (MPW-V3 §2.3.1: $guarded = [] prohibido).
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'institution_name',
        'description',
        'max_uses',
        'uses_count',
        'expires_at',
        'is_active',
        'created_by',
    ];

    /**
     * Administrador que emitió el código de invitación institucional.
     *
     * @return BelongsTo<User, TeacherInvitationCode>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Solicitudes de verificación docente que canjearon este código.
     *
     * @return HasMany<TeacherVerification>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(TeacherVerification::class, 'invitation_code_id');
    }

    /**
     * Indica si el código superó su fecha límite de vigencia.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Calcula la cantidad de usos disponibles restantes para este código.
     *
     * @return int
     */
    public function remainingUses(): int
    {
        return max(0, (int) $this->max_uses - (int) $this->uses_count);
    }

    /**
     * Indica si el código aún cuenta con al menos un uso disponible.
     *
     * @return bool
     */
    public function hasRemainingUses(): bool
    {
        return $this->remainingUses() > 0;
    }

    /**
     * Determina si el código puede canjearse actualmente (activo, vigente y con cupo).
     *
     * @return bool
     */
    public function isUsable(): bool
    {
        return (bool) $this->is_active && ! $this->isExpired() && $this->hasRemainingUses();
    }

    /**
     * Normaliza un código de invitación recortando extremos, eliminando espacios
     * internos y convirtiendo a mayúsculas canónicas.
     *
     * @param string $code Código ingresado por el usuario o administrador.
     * @return string Código normalizado sin espacios en mayúsculas.
     */
    public static function normalizeCode(string $code): string
    {
        $trimmed = trim($code);
        $withoutSpaces = preg_replace('/\s+/u', '', $trimmed);

        return strtoupper($withoutSpaces ?? $trimmed);
    }

    /**
     * Conversión de atributos (MPW-V3 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
