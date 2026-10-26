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
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_teacher_subscriptions: la
 *                 suscripción vigente (o histórica) de un docente a un
 *                 paquete, con su estado de pago (RW-05, suspensión por
 *                 falta de pago).
 * ============================================================================
 */

namespace App\Models;

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
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
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
    ];

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
}
