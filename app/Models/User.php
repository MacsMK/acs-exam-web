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
 * Versión:        2.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_users (MBD-V4 Tabla 20). Sirve
 *                 indistintamente a super_admin, teacher y student
 *                 (MBD-V4 §6.2). Autenticable por Fortify (web) y portador
 *                 de tokens Sanctum (móvil, MPW-V2 §1.3.1 / MPM-V2 §6.6.1).
 *
 *                 CAMBIOS v2.0.0: refleja la migración 001 v2.0.0 (acuerdo
 *                 de equipo con Balam, 26/10/2026) — two_factor_enabled se
 *                 sustituye por two_factor_code/two_factor_expires_at/
 *                 two_factor_verified_at; se agrega is_active; se agregan
 *                 las relaciones con suscripción de docente, grupos y
 *                 aceptaciones de privacidad.
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
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4): Eloquent no
     * infiere "acs_users" a partir del nombre de la clase, debe declararse
     * explícitamente en todo modelo del proyecto.
     *
     * @var string
     */
    protected $table = 'acs_users';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1: $guarded = [] prohibido).
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
     * Conversión de atributos (MPW-V2 §2.3.2: método casts(), no la
     * propiedad estática $casts, conforme al estándar de Laravel 11).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'two_factor_verified_at' => 'datetime',
        ];
    }

    /**
     * Nombre completo (first_name + last_name), uso en vistas y recibos
     * (DE-07) donde MBD-V4/MPM-V2 piden un único nombre para mostrar.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Suscripción vigente del docente (acs_teacher_subscriptions). Solo
     * aplica cuando role = teacher; null para student/super_admin.
     *
     * @return HasOne<TeacherSubscription>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(TeacherSubscription::class, 'teacher_id');
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
}
