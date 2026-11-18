<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        PaymentGatewayContract.php
 * Ubicación:      app/Contracts/PaymentGatewayContract.php
 * Requerimiento:  RF-SUS-013, RF-SUS-015, ADR-058 (Pasarela de Pago Simulada)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Contrato desacoplado para el procesamiento de cobros de
 *                 paquetes SaaS docentes, aislando la lógica transaccional del
 *                 driver de pasarela concreto (simulada o futura real).
 * ============================================================================
 */

namespace App\Contracts;

use App\Models\SubscriptionPackage;
use App\Models\User;

/**
 * Contrato de procesamiento de pagos de suscripción SaaS docente.
 *
 * @package App\Contracts
 */
interface PaymentGatewayContract
{
    /**
     * Procesa un intento de cobro para la contratación o renovación de un paquete SaaS.
     *
     * @param SubscriptionPackage $package Paquete de suscripción seleccionado.
     * @param User $teacher Cuenta docente titular de la suscripción.
     * @param array<string, mixed> $paymentData Datos validados del instrumento de pago.
     * @return array{
     *     approved: bool,
     *     provider: string,
     *     method: string,
     *     card_brand: ?string,
     *     card_last4: ?string,
     *     card_holder_name: ?string,
     *     spei_tracking_key: ?string,
     *     failure_code: ?string,
     *     failure_message: ?string,
     *     metadata: array<string, mixed>
     * }
     */
    public function process(SubscriptionPackage $package, User $teacher, array $paymentData): array;
}
