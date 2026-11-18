<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        SimulatedPaymentGateway.php
 * Ubicación:      app/Services/Payment/SimulatedPaymentGateway.php
 * Requerimiento:  RF-SUS-013, RF-SUS-015, ADR-058 (Pasarela de Pago Simulada)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Implementación simulada ('simulated') de PaymentGatewayContract
 *                 con soporte dual para tarjeta bancaria (validación Luhn y
 *                 reglas deterministas sin persistir PAN completo ni CVV) y
 *                 transferencia SPEI instantánea con clave de rastreo.
 * ============================================================================
 */

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayContract;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPackage;
use App\Models\User;

/**
 * Pasarela de pago simulada dual (Tarjeta Luhn y SPEI instantáneo).
 *
 * @package App\Services\Payment
 */
class SimulatedPaymentGateway implements PaymentGatewayContract
{
    /**
     * CLABE interbancaria institucional de destino para pruebas SPEI.
     */
    public const SIMULATED_CLABE = '646180110400000018';

    /**
     * Identificador del proveedor simulado para cobros con tarjeta.
     */
    private const PROVIDER_CARD = 'simulated_card';

    /**
     * Identificador del proveedor simulado para transferencias SPEI.
     */
    private const PROVIDER_SPEI = 'simulated_spei';

    /**
     * Procesa de forma determinista un cobro simulado con tarjeta o SPEI sin
     * exponer ni conservar nunca el PAN completo ni el CVV en metadatos.
     *
     * @param SubscriptionPackage $package Paquete de suscripción a cobrar.
     * @param User $teacher Cuenta docente titular del pago.
     * @param array<string, mixed> $paymentData Datos validados del formulario de pago.
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
    public function process(SubscriptionPackage $package, User $teacher, array $paymentData): array
    {
        $rawMethod = $paymentData['payment_method'] ?? $paymentData['method'] ?? '';
        $method = is_string($rawMethod) ? strtolower(trim($rawMethod)) : '';

        return match ($method) {
            PaymentTransaction::METHOD_CARD => $this->processCard($paymentData),
            PaymentTransaction::METHOD_SPEI => $this->processSpei($paymentData),
            default => [
                'approved' => false,
                'provider' => 'simulated',
                'method' => $method !== '' ? $method : 'unknown',
                'card_brand' => null,
                'card_last4' => null,
                'card_holder_name' => null,
                'spei_tracking_key' => null,
                'failure_code' => 'unsupported_method',
                'failure_message' => 'El método de pago seleccionado no es compatible con la pasarela',
                'metadata' => ['simulated' => true],
            ],
        };
    }

    /**
     * Evalúa el cobro simulado con tarjeta aplicando normalización de dígitos,
     * detección de marca, algoritmo Luhn y reglas deterministas de rechazo.
     *
     * @param array<string, mixed> $paymentData Datos del instrumento tarjeta.
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
    private function processCard(array $paymentData): array
    {
        $rawNumber = isset($paymentData['card_number']) && is_string($paymentData['card_number'])
            ? $paymentData['card_number']
            : '';
        $digits = preg_replace('/\D+/', '', $rawNumber) ?? '';
        $last4 = strlen($digits) >= 4 ? substr($digits, -4) : ($digits !== '' ? $digits : null);
        $brand = $this->detectCardBrand($digits);

        $rawHolder = isset($paymentData['card_holder_name']) && is_string($paymentData['card_holder_name'])
            ? trim($paymentData['card_holder_name'])
            : '';
        $holderName = $rawHolder !== '' ? mb_substr($rawHolder, 0, 120) : null;

        $cvv = isset($paymentData['card_cvv']) && is_string($paymentData['card_cvv'])
            ? trim($paymentData['card_cvv'])
            : '';

        if (strlen($digits) < 13 || strlen($digits) > 19 || ! $this->passesLuhn($digits)) {
            return [
                'approved' => false,
                'provider' => self::PROVIDER_CARD,
                'method' => PaymentTransaction::METHOD_CARD,
                'card_brand' => $brand,
                'card_last4' => $last4,
                'card_holder_name' => $holderName,
                'spei_tracking_key' => null,
                'failure_code' => 'invalid_card',
                'failure_message' => 'El número de tarjeta no superó la validación bancaria',
                'metadata' => ['simulated' => true],
            ];
        }

        if ($last4 === '0002' || $cvv === '000') {
            return [
                'approved' => false,
                'provider' => self::PROVIDER_CARD,
                'method' => PaymentTransaction::METHOD_CARD,
                'card_brand' => $brand,
                'card_last4' => $last4,
                'card_holder_name' => $holderName,
                'spei_tracking_key' => null,
                'failure_code' => 'insufficient_funds',
                'failure_message' => 'Pago rechazado por el banco emisor: fondos insuficientes',
                'metadata' => ['simulated' => true],
            ];
        }

        if ($last4 === '0009') {
            return [
                'approved' => false,
                'provider' => self::PROVIDER_CARD,
                'method' => PaymentTransaction::METHOD_CARD,
                'card_brand' => $brand,
                'card_last4' => $last4,
                'card_holder_name' => $holderName,
                'spei_tracking_key' => null,
                'failure_code' => 'card_declined',
                'failure_message' => 'La tarjeta de prueba fue rechazada por validación de seguridad',
                'metadata' => ['simulated' => true],
            ];
        }

        return [
            'approved' => true,
            'provider' => self::PROVIDER_CARD,
            'method' => PaymentTransaction::METHOD_CARD,
            'card_brand' => $brand,
            'card_last4' => $last4,
            'card_holder_name' => $holderName,
            'spei_tracking_key' => null,
            'failure_code' => null,
            'failure_message' => null,
            'metadata' => [
                'simulated' => true,
                'authorization_code' => 'SIM-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
            ],
        ];
    }

    /**
     * Evalúa la transferencia SPEI instantánea simulada validando banco y titular.
     *
     * @param array<string, mixed> $paymentData Datos de la transferencia SPEI.
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
    private function processSpei(array $paymentData): array
    {
        $senderBank = isset($paymentData['spei_sender_bank']) && is_string($paymentData['spei_sender_bank'])
            ? mb_substr(trim($paymentData['spei_sender_bank']), 0, 80)
            : '';

        $holder = isset($paymentData['spei_account_holder']) && is_string($paymentData['spei_account_holder'])
            ? mb_substr(trim($paymentData['spei_account_holder']), 0, 120)
            : '';

        if ($senderBank === '' || $holder === '') {
            return [
                'approved' => false,
                'provider' => self::PROVIDER_SPEI,
                'method' => PaymentTransaction::METHOD_SPEI,
                'card_brand' => null,
                'card_last4' => null,
                'card_holder_name' => null,
                'spei_tracking_key' => null,
                'failure_code' => 'spei_invalid_data',
                'failure_message' => 'Los datos de la transferencia SPEI están incompletos',
                'metadata' => ['simulated' => true],
            ];
        }

        if (str_contains(mb_strtoupper($holder), 'RECHAZO')) {
            return [
                'approved' => false,
                'provider' => self::PROVIDER_SPEI,
                'method' => PaymentTransaction::METHOD_SPEI,
                'card_brand' => null,
                'card_last4' => null,
                'card_holder_name' => null,
                'spei_tracking_key' => null,
                'failure_code' => 'spei_rejected',
                'failure_message' => 'Transferencia SPEI rechazada por clave de rastreo de prueba',
                'metadata' => [
                    'simulated' => true,
                    'sender_bank' => $senderBank,
                    'clabe_destination' => self::SIMULATED_CLABE,
                ],
            ];
        }

        return [
            'approved' => true,
            'provider' => self::PROVIDER_SPEI,
            'method' => PaymentTransaction::METHOD_SPEI,
            'card_brand' => null,
            'card_last4' => null,
            'card_holder_name' => null,
            'spei_tracking_key' => 'SPEI-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4))),
            'failure_code' => null,
            'failure_message' => null,
            'metadata' => [
                'simulated' => true,
                'sender_bank' => $senderBank,
                'clabe_destination' => self::SIMULATED_CLABE,
            ],
        ];
    }

    /**
     * Detecta la marca comercial de la tarjeta a partir de sus dígitos iniciales.
     *
     * @param string $digits Número de tarjeta normalizado solo a dígitos.
     * @return string
     */
    private function detectCardBrand(string $digits): string
    {
        if (str_starts_with($digits, '34') || str_starts_with($digits, '37')) {
            return 'amex';
        }

        if (str_starts_with($digits, '4')) {
            return 'visa';
        }

        if (preg_match('/^(5[1-5]|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)/', $digits) === 1) {
            return 'mastercard';
        }

        return 'card';
    }

    /**
     * Verifica el dígito verificador mediante el algoritmo estándar de Luhn.
     *
     * @param string $digits Secuencia numérica de la tarjeta sin espacios ni guiones.
     * @return bool
     */
    private function passesLuhn(string $digits): bool
    {
        if ($digits === '' || preg_match('/^[0-9]+$/', $digits) !== 1) {
            return false;
        }

        $sum = 0;
        $shouldDouble = false;

        for ($index = strlen($digits) - 1; $index >= 0; $index--) {
            $digit = (int) $digits[$index];

            if ($shouldDouble) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $shouldDouble = ! $shouldDouble;
        }

        return ($sum % 10) === 0;
    }
}
