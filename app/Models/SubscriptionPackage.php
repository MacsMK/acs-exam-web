<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        SubscriptionPackage.php
 * Ubicación:      app/Models/SubscriptionPackage.php
 * Requerimiento:  RF-SUS (confirmar código específico en DE-01 con Eli;
 *                 ver Formato BD-01 folio BD01-2026-004)
 * Autor:          Equipo de Base de Datos (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_subscription_packages: catálogo de
 *                 paquetes (basic/advanced/pro, MPW-V2 Tabla 8) que un
 *                 docente puede contratar.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paquete de suscripción (nivel basic/advanced/pro) para docentes.
 *
 * @package App\Models
 */
class SubscriptionPackage extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_subscription_packages';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tier',
        'name',
        'max_groups',
        'max_questions',
        'max_concurrent_students',
        'max_active_exams',
        'ai_requests_limit',
        'monthly_price',
        'is_active',
    ];

    /**
     * Conversión de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Suscripciones de docentes contratadas sobre este paquete.
     *
     * @return HasMany<TeacherSubscription>
     */
    public function teacherSubscriptions(): HasMany
    {
        return $this->hasMany(TeacherSubscription::class);
    }
}
