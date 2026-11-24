<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        PaymentTransaction.php
 * Ubicación:      app/Models/PaymentTransaction.php
 * Requerimiento:  ADR-058 / Pasarela de Pago Simulada y Onboarding Docente
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Modelo Eloquent de acs_payment_transactions: registro de
 *                 intentos y confirmaciones de pago de suscripción docente
 *                 mediante pasarela simulada (tarjeta y SPEI), sin almacenar
 *                 PAN completo ni CVV.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transacción de pago de suscripción docente procesada por la pasarela.
 *
 * @package App\Models
 */
class PaymentTransaction extends Model
{
    /**
     * Estados del resultado de la transacción de pago.
     */
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    // Actualización de nivel sin cobro inmediato (RF-SUS-012)
    public const STATUS_NO_CHARGE = 'no_charge';

    /**
     * Concepto de la operación (RF-SUS-013, BD01-2026-035).
     */
    public const CONCEPT_SUBSCRIPTION = 'subscription';
    public const CONCEPT_RENEWAL = 'renewal';
    public const CONCEPT_UPGRADE = 'upgrade';

    /**
     * Operación sin medio de pago (actualización de nivel sin cobro).
     */
    public const METHOD_NONE = 'none';

    /**
     * Métodos de pago admitidos por la pasarela simulada.
     */
    public const METHOD_CARD = 'card';
    public const METHOD_SPEI = 'spei';

    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V3 §3.3).
     *
     * @var string
     */
    protected $table = 'acs_payment_transactions';

    /**
     * Columnas asignables en masa (MPW-V3 §2.3.1: sin PAN completo ni CVV).
     *
     * @var list<string>
     */
    protected $fillable = [
        'reference_code',
        'teacher_id',
        'subscription_package_id',
        'teacher_subscription_id',
        'gateway_provider',
        'payment_method',
        'status',
        'concept',
        'amount',
        'currency',
        'card_brand',
        'card_last4',
        'card_holder_name',
        'spei_tracking_key',
        'failure_code',
        'failure_message',
        'metadata',
        'paid_at',
    ];

    /**
     * Docente que originó la transacción de pago.
     *
     * @return BelongsTo<User, PaymentTransaction>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Paquete de suscripción asociado a esta transacción.
     *
     * @return BelongsTo<SubscriptionPackage, PaymentTransaction>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }

    /**
     * Suscripción docente activada o renovada por esta transacción.
     *
     * @return BelongsTo<TeacherSubscription, PaymentTransaction>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TeacherSubscription::class, 'teacher_subscription_id');
    }

    /**
     * Devuelve la etiqueta legible del método de pago utilizado.
     *
     * @return string
     */
    public function methodLabel(): string
    {
        return match ($this->payment_method) {
            self::METHOD_CARD => 'Tarjeta de crédito / débito',
            self::METHOD_SPEI => 'Transferencia SPEI',
            default => (string) ($this->payment_method ?? 'No especificado'),
        };
    }

    /**
     * Etiqueta del concepto de la operación (RF-SUS-013).
     */
    public function conceptLabel(): string
    {
        return match ($this->concept) {
            self::CONCEPT_RENEWAL => 'Renovación',
            self::CONCEPT_UPGRADE => 'Actualización de nivel',
            default => 'Contratación',
        };
    }

    /**
     * Devuelve la etiqueta legible del estado de la transacción.
     *
     * @return string
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'Pagado',
            self::STATUS_FAILED => 'Fallido',
            self::STATUS_NO_CHARGE => 'Sin cobro',
            default => (string) ($this->status ?? 'Desconocido'),
        };
    }

    /**
     * Genera un resumen enmascarado del instrumento de pago utilizado.
     *
     * @return string
     */
    public function maskedPaymentSummary(): string
    {
        if ($this->payment_method === self::METHOD_CARD) {
            $brand = $this->card_brand !== null && trim($this->card_brand) !== ''
                ? trim($this->card_brand)
                : 'Tarjeta';
            $last4 = $this->card_last4 !== null && trim($this->card_last4) !== ''
                ? trim($this->card_last4)
                : '••••';

            return "{$brand} •••• {$last4}";
        }

        if ($this->payment_method === self::METHOD_SPEI) {
            if ($this->spei_tracking_key !== null && trim($this->spei_tracking_key) !== '') {
                return "SPEI ({$this->spei_tracking_key})";
            }

            return 'SPEI';
        }

        if ($this->payment_method === self::METHOD_NONE) {
            return 'Sin cobro';
        }

        return $this->methodLabel();
    }

    /**
     * Conversión de atributos (MPW-V3 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }
}
