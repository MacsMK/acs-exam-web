<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        User.php
 * Ubicación:      app/Models/User.php
 * Requerimiento:  RF-AUT (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-001)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        2.3.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_users (ficha BD-02 de acs_users). Sirve
 *                 indistintamente a super_admin, teacher y student
 *                 (MBD-V3 §3.3; ficha BD-02 de acs_users). Autenticable por Fortify (web) y portador
 *                 de tokens Sanctum (móvil, MPW-V3 §1.3.1 / MPM-V3 §6.6.1).
 *
 *                 CAMBIOS v2.0.0: refleja la migración 001 v2.0.0 (acuerdo
 *                 de equipo con Balam, 26/10/2026) — two_factor_enabled se
 *                 sustituye por two_factor_code/two_factor_expires_at/
 *                 two_factor_verified_at; se agrega is_active; se agregan
 *                 las relaciones con suscripción de docente, grupos y
 *                 aceptaciones de privacidad.
 *
 *                 CAMBIOS v2.2.0: agrega la relación teacherVerification y
 *                 helpers de estado de acreditación docente manteniendo
 *                 teacher_verification_status fuera de $fillable por seguridad.
 *
 *                 CAMBIOS v2.3.0: agrega relación aiCreditUsages y cast booleano
 *                 de skip_ai_reevaluation_warning (fuera de $fillable por
 *                 seguridad OWASP A01 / mass assignment).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuario de la plataforma (super admin, docente o alumno).
 *
 * @package App\Models
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V3 §3.3): Eloquent no
     * infiere "acs_users" a partir del nombre de la clase, debe declararse
     * explícitamente en todo modelo del proyecto.
     *
     * @var string
     */
    protected $table = 'acs_users';

    /**
     * Columnas asignables en masa (MPW-V3 §2.3.1: $guarded = [] prohibido).
     *
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    /**
     * Columnas que nunca deben serializarse (ej. respuestas JSON de la API).
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
    ];

    /**
     * Valores por defecto de atributos en memoria para mantener paridad con
     * los defaults de PostgreSQL (evita valores null antes de refresh()).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'teacher_verification_status' => TeacherVerification::STATUS_APPROVED,
    ];

    /**
     * Suscripción vigente del docente (acs_teacher_subscriptions). Solo
     * aplica cuando role = teacher; null para student/super_admin.
     *
     * @return HasOne<TeacherSubscription>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(TeacherSubscription::class, 'teacher_id')->latestOfMany();
    }

    /**
     * Grupos que este usuario imparte como docente (acs_groups.teacher_id).
     *
     * @return HasMany<Group>
     */
    public function groupsAsTeacher(): HasMany
    {
        return $this->hasMany(Group::class, 'teacher_id');
    }

    /**
     * Grupos en los que este usuario está inscrito como alumno
     * (acs_group_student).
     *
     * @return BelongsToMany<Group>
     */
    public function groupsAsStudent(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'acs_group_student', 'student_id', 'group_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * Aceptaciones de aviso de privacidad hechas por este usuario.
     *
     * @return HasMany<PrivacyAcceptance>
     */
    public function privacyAcceptances(): HasMany
    {
        return $this->hasMany(PrivacyAcceptance::class);
    }

    /**
     * Reactivos del banco de preguntas creados por este docente.
     *
     * @return HasMany<Question>
     */
    public function questionsAuthored(): HasMany
    {
        return $this->hasMany(Question::class, 'teacher_id');
    }

    /**
     * Exámenes armados por este docente.
     *
     * @return HasMany<Exam>
     */
    public function examsCreated(): HasMany
    {
        return $this->hasMany(Exam::class, 'teacher_id');
    }

    /**
     * Intentos de examen hechos por este usuario como alumno.
     *
     * @return HasMany<StudentAttempt>
     */
    public function studentAttempts(): HasMany
    {
        return $this->hasMany(StudentAttempt::class, 'student_id');
    }

    /**
     * Entradas de la bitácora de auditoría atribuidas a este usuario.
     *
     * @return HasMany<AuditLog>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Dispositivos móviles registrados (token FCM).
     *
     * @return HasMany<UserDevice>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * Suscripciones SaaS contratadas o asignadas a este docente.
     *
     * @return HasMany<TeacherSubscription>
     */
    public function teacherSubscriptions(): HasMany
    {
        return $this->hasMany(TeacherSubscription::class, 'teacher_id')->latest('id');
    }

    /**
     * Expediente de acreditación de identidad docente (acs_teacher_verifications).
     *
     * @return HasOne<TeacherVerification>
     */
    public function teacherVerification(): HasOne
    {
        return $this->hasOne(TeacherVerification::class, 'user_id');
    }

    /**
     * Historial de transacciones de pago del docente (acs_payment_transactions).
     *
     * @return HasMany<PaymentTransaction>
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'teacher_id')->latest('id');
    }

    /**
     * Última transacción de pago registrada para este docente.
     *
     * @return HasOne<PaymentTransaction>
     */
    public function latestPaymentTransaction(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class, 'teacher_id')->latestOfMany();
    }

    /**
     * Registros de consumo de créditos de evaluación con IA del docente
     * (acs_ai_credit_usages).
     *
     * @return HasMany<AiCreditUsage>
     */
    public function aiCreditUsages(): HasMany
    {
        return $this->hasMany(AiCreditUsage::class, 'teacher_id');
    }

    /**
     * Indica si el usuario cuenta con acreditación docente aprobada o no
     * requiere verificación por no tener rol docente.
     *
     * @return bool
     */
    public function isVerifiedTeacher(): bool
    {
        return $this->role !== 'teacher'
            || $this->teacher_verification_status === TeacherVerification::STATUS_APPROVED;
    }

    /**
     * Indica si el usuario es docente con solicitud de verificación pendiente.
     *
     * @return bool
     */
    public function isPendingTeacherVerification(): bool
    {
        return $this->role === 'teacher'
            && $this->teacher_verification_status === TeacherVerification::STATUS_PENDING;
    }

    /**
     * Indica si el usuario es docente cuya solicitud de verificación fue rechazada.
     *
     * @return bool
     */
    public function isRejectedTeacherVerification(): bool
    {
        return $this->role === 'teacher'
            && $this->teacher_verification_status === TeacherVerification::STATUS_REJECTED;
    }

    /**
     * Determina si el docente verificado aún debe completar el onboarding de
     * selección de plan y suscripción post-verificación.
     *
     * @return bool
     */
    public function needsPlanOnboarding(): bool
    {
        if ($this->role !== 'teacher' || ! $this->isVerifiedTeacher()) {
            return false;
        }

        if ($this->exists) {
            $this->loadMissing(['teacherVerification', 'subscription']);
        }

        $verification = $this->teacherVerification;

        if ($verification === null || $verification->plan_onboarding_completed_at !== null) {
            return false;
        }

        $sub = $this->subscription;

        if ($sub !== null && $sub->payment_status === 'paid' && ($sub->expires_at === null || $sub->expires_at->isFuture())) {
            return false;
        }

        return true;
    }

    /**
     * Conversión de atributos (MPW-V3 §2.3.2 y Tabla 6 Bloque 16: método
     * casts(), no la propiedad estática $casts, conforme al estándar Laravel 11).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'skip_ai_reevaluation_warning' => 'boolean',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'two_factor_verified_at' => 'datetime',
        ];
    }

    /**
     * Nombre completo (first_name + last_name), uso en vistas y recibos
     * (DE-07) donde MBD-V3/MPM-V3 piden un único nombre para mostrar.
     * Ubicado en el bloque 16 (Accesores / Mutadores) conforme a MPW-V3 §3.8.1.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
