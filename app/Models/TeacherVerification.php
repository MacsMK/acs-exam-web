<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherVerification.php
 * Ubicación:      app/Models/TeacherVerification.php
 * Requerimiento:  RF-AUT / Verificación Híbrida de Identidad Docente (LFPDPPP)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 17/11/2026
 * Descripción:    Modelo Eloquent de acs_teacher_verifications: expediente de
 *                 acreditación de identidad docente con estados de dictamen,
 *                 métodos de validación híbrida, catálogos normativos y control
 *                 de purga automática de comprobantes conforme a LFPDPPP.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expediente de acreditación de identidad de un usuario con rol docente.
 *
 * @package App\Models
 */
class TeacherVerification extends Model
{
    /**
     * Estados del ciclo de vida del dictamen de verificación docente.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Métodos admitidos para acreditar la identidad profesional docente.
     */
    public const METHOD_DOCUMENT_REVIEW = 'document_review';
    public const METHOD_INVITATION_CODE = 'invitation_code';
    public const METHOD_SEP_API = 'sep_api';

    /**
     * Catálogo de tipos de comprobante documental aceptados.
     *
     * @var array<string, string>
     */
    public const CREDENTIAL_TYPES = [
        'institutional_id' => 'Credencial institucional de docente',
        'employment_letter' => 'Constancia laboral o nombramiento',
        'official_schedule' => 'Horario oficial sellado',
    ];

    /**
     * Catálogo de niveles educativos de adscripción docente.
     *
     * @var array<string, string>
     */
    public const EDUCATIONAL_LEVELS = [
        'secundaria' => 'Secundaria',
        'media_superior' => 'Media Superior / Bachillerato',
        'superior' => 'Superior / Universidad',
        'capacitacion' => 'Capacitación / Docente independiente',
        'otro' => 'Otro nivel educativo',
    ];

    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_teacher_verifications';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1: $guarded = [] prohibido).
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'verification_method',
        'institution_name',
        'educational_level',
        'professional_license',
        'credential_type',
        'document_path',
        'document_original_name',
        'document_mime_type',
        'document_purged_at',
        'is_institutional_email',
        'invitation_code_id',
        'license_check_payload',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'resubmission_count',
    ];

    /**
     * Usuario docente titular de esta solicitud de verificación.
     *
     * @return BelongsTo<User, TeacherVerification>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Administrador que emitió el dictamen de aprobación o rechazo.
     *
     * @return BelongsTo<User, TeacherVerification>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Código de invitación institucional utilizado en caso de acreditación expedita.
     *
     * @return BelongsTo<TeacherInvitationCode, TeacherVerification>
     */
    public function invitationCode(): BelongsTo
    {
        return $this->belongsTo(TeacherInvitationCode::class, 'invitation_code_id');
    }

    /**
     * Indica si el expediente conserva actualmente un comprobante sin purgar.
     *
     * @return bool
     */
    public function hasDocument(): bool
    {
        return $this->document_path !== null
            && trim($this->document_path) !== ''
            && $this->document_purged_at === null;
    }

    /**
     * Devuelve la etiqueta legible del tipo de comprobante documental.
     *
     * @return string
     */
    public function credentialTypeLabel(): string
    {
        if ($this->credential_type === null || $this->credential_type === '') {
            return 'Comprobante de adscripción docente';
        }

        return self::CREDENTIAL_TYPES[$this->credential_type] ?? $this->credential_type;
    }

    /**
     * Devuelve la etiqueta legible del nivel educativo declarado.
     *
     * @return string
     */
    public function educationalLevelLabel(): string
    {
        if ($this->educational_level === null || $this->educational_level === '') {
            return 'No especificado';
        }

        return self::EDUCATIONAL_LEVELS[$this->educational_level] ?? $this->educational_level;
    }

    /**
     * Devuelve la etiqueta legible del método de verificación empleado.
     *
     * @return string
     */
    public function verificationMethodLabel(): string
    {
        return match ($this->verification_method) {
            self::METHOD_DOCUMENT_REVIEW => 'Revisión documental',
            self::METHOD_INVITATION_CODE => 'Código de invitación institucional',
            self::METHOD_SEP_API => 'Validación vía Cédula SEP',
            default => (string) ($this->verification_method ?? 'No especificado'),
        };
    }

    /**
     * Devuelve la etiqueta legible del estado actual de la solicitud.
     *
     * @return string
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente de revisión',
            self::STATUS_APPROVED => 'Aprobada',
            self::STATUS_REJECTED => 'Rechazada',
            default => (string) ($this->status ?? 'Desconocido'),
        };
    }

    /**
     * Indica si el expediente docente aprobado aún requiere completar el
     * onboarding inicial de selección de plan o suscripción.
     *
     * @return bool
     */
    public function needsPlanOnboarding(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->plan_onboarding_completed_at === null;
    }

    /**
     * Conversión de atributos (MPW-V2 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_purged_at' => 'datetime',
            'is_institutional_email' => 'boolean',
            'license_check_payload' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'plan_onboarding_completed_at' => 'datetime',
            'resubmission_count' => 'integer',
        ];
    }
}
