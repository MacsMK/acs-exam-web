<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherSubscription.php
 * Ubicación:      app/Models/TeacherSubscription.php
 * Requerimiento:  RF-SUS (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-005)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_teacher_subscriptions: la
 *                 suscripción vigente (o histórica) de un docente a un
 *                 paquete, con su estado de pago (RW-05, suspensión por
 *                 falta de pago).
 * ============================================================================
 */

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suscripción de un docente a un paquete.
 *
 * @package App\Models
 */
class TeacherSubscription extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V3 §3.3).
     *
     * @var string
     */
    protected $table = 'acs_teacher_subscriptions';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'teacher_id',
        'subscription_package_id',
        'payment_status',
        'starts_at',
        'expires_at',
        'suspended_at',
        'notes',
        'auto_renew',
        'cancelled_at',
        'renewal_attempts',
        'card_brand',
        'card_last4',
        'card_holder_name',
    ];

    /**
     * Días de reintento del cobro de renovación rechazado, durante los cuales
     * el plan sigue vigente (RF-SUS-005).
     */
    public const RENEWAL_GRACE_DAYS = 3;

    /**
     * Conversión de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'auto_renew' => 'boolean',
            'renewal_attempts' => 'integer',
        ];
    }

    /**
     * Docente dueño de la suscripción.
     *
     * @return BelongsTo<User, TeacherSubscription>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Paquete contratado.
     *
     * @return BelongsTo<SubscriptionPackage, TeacherSubscription>
     */
    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class);
    }

    /**
     * Alias de conveniencia para la relación con SubscriptionPackage.
     *
     * @return BelongsTo<SubscriptionPackage, TeacherSubscription>
     */
    public function package(): BelongsTo
    {
        return $this->subscriptionPackage();
    }

    /**
     * La suscripción se renovará con cobro domiciliado (RF-SUS-005).
     */
    public function renewsAutomatically(): bool
    {
        return $this->auto_renew && $this->cancelled_at === null && $this->card_last4 !== null;
    }

    /**
     * Fecha del próximo cobro domiciliado; null si no se renovará (RF-SUS-006).
     */
    public function nextChargeAt(): ?CarbonInterface
    {
        return $this->renewsAutomatically() ? $this->expires_at : null;
    }

    /**
     * Vencida, con cobro de renovación rechazado y dentro de los días de reintento (RF-SUS-005).
     */
    public function isInRenewalGracePeriod(): bool
    {
        return $this->payment_status === 'overdue'
            && $this->renewsAutomatically()
            && $this->expires_at !== null
            && $this->expires_at->copy()->addDays(self::RENEWAL_GRACE_DAYS)->isFuture();
    }

    /**
     * Resumen enmascarado del medio de pago domiciliado (RF-SUS-003).
     */
    public function maskedCard(): ?string
    {
        if ($this->card_last4 === null) {
            return null;
        }

        return trim(($this->card_brand ?: 'Tarjeta').' •••• '.$this->card_last4);
    }
}
