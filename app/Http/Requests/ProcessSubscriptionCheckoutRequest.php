<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ProcessSubscriptionCheckoutRequest.php
 * Ubicación:      app/Http/Requests/ProcessSubscriptionCheckoutRequest.php
 * Requerimiento:  RF-SUS-013, RF-SUS-015, ADR-058 (Pasarela de Pago Simulada)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Valida los datos del checkout de suscripción docente para
 *                 tarjeta bancaria (normalización, Luhn, vigencia MM/AA y CVV
 *                 diferenciado Amex/estándar) o transferencia SPEI instantánea,
 *                 con mensajes normalizados conforme a MPW-V2 Tabla 17.
 * ============================================================================
 */

namespace App\Http\Requests;

use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Form Request para el procesamiento del checkout de suscripción docente.
 *
 * @package App\Http\Requests
 */
class ProcessSubscriptionCheckoutRequest extends FormRequest
{
    /**
     * Autoriza exclusivamente a cuentas docentes ya verificadas.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || $user->role !== 'teacher' || ! $user->is_active || ! $user->isVerifiedTeacher()) {
            return false;
        }

        $package = $this->route('package');
        if ($package instanceof SubscriptionPackage && ! $package->is_active) {
            abort(404);
        }

        return true;
    }

    /**
     * Reglas de validación para los métodos de pago tarjeta y SPEI.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'in:card,spei'],
            'card_holder_name' => ['required_if:payment_method,card', 'nullable', 'string', 'min:3', 'max:120'],
            'card_number' => ['required_if:payment_method,card', 'nullable', 'string', 'regex:/^[0-9]{13,19}$/'],
            'card_expiry' => ['required_if:payment_method,card', 'nullable', 'string', 'regex:/^(0[1-9]|1[0-2])\/[0-9]{2}$/'],
            'card_cvv' => ['required_if:payment_method,card', 'nullable', 'string', 'regex:/^[0-9]{3,4}$/'],
            'spei_sender_bank' => ['required_if:payment_method,spei', 'nullable', 'string', 'in:BBVA México,Santander,Banorte,Citibanamex,HSBC,Nu México,Mercado Pago,Otro'],
            'spei_account_holder' => ['required_if:payment_method,spei', 'nullable', 'string', 'min:3', 'max:120'],
        ];
    }

    /**
     * Mensajes de validación normalizados conforme a MPW-V2 §8.11 / Tabla 17.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'El campo método de pago es obligatorio',
            'payment_method.string' => 'El método de pago no tiene un formato válido',
            'payment_method.in' => 'La opción seleccionada en método de pago no es válida',

            'card_holder_name.required_if' => 'El campo nombre del titular es obligatorio',
            'card_holder_name.string' => 'El nombre del titular no tiene un formato válido',
            'card_holder_name.min' => 'El nombre del titular debe tener al menos 3 caracteres',
            'card_holder_name.max' => 'El nombre del titular no debe exceder los 120 caracteres',

            'card_number.required_if' => 'El campo número de tarjeta es obligatorio',
            'card_number.string' => 'El número de tarjeta no tiene un formato válido',
            'card_number.regex' => 'El número de tarjeta debe contener entre 13 y 19 dígitos',

            'card_expiry.required_if' => 'El campo fecha de vencimiento es obligatorio',
            'card_expiry.string' => 'La fecha de vencimiento no tiene un formato válido',
            'card_expiry.regex' => 'La fecha de vencimiento debe tener el formato MM/AA',

            'card_cvv.required_if' => 'El campo código de seguridad (CVV) es obligatorio',
            'card_cvv.string' => 'El código de seguridad (CVV) no tiene un formato válido',
            'card_cvv.regex' => 'El código de seguridad (CVV) debe contener 3 o 4 dígitos',

            'spei_sender_bank.required_if' => 'El campo banco emisor es obligatorio',
            'spei_sender_bank.string' => 'El banco emisor no tiene un formato válido',
            'spei_sender_bank.max' => 'El banco emisor no debe exceder los 80 caracteres',

            'spei_account_holder.required_if' => 'El campo titular de la cuenta emisora es obligatorio',
            'spei_account_holder.string' => 'El titular de la cuenta emisora no tiene un formato válido',
            'spei_account_holder.min' => 'El titular de la cuenta emisora debe tener al menos 3 caracteres',
            'spei_account_holder.max' => 'El titular de la cuenta emisora no debe exceder los 120 caracteres',
        ];
    }

    /**
     * Valida Luhn, vigencia mensual y longitud de CVV según franquicia cuando
     * el método es tarjeta y los campos base superaron las reglas iniciales.
     *
     * @param Validator $validator Instancia del validador en curso.
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('payment_method') !== 'card') {
                return;
            }

            $cardNumber = $this->input('card_number');
            $cardExpiry = $this->input('card_expiry');
            $cardCvv = $this->input('card_cvv');

            if (
                ! $validator->errors()->has('card_number')
                && is_string($cardNumber)
                && $cardNumber !== ''
                && ! $this->passesLuhn($cardNumber)
            ) {
                $validator->errors()->add(
                    'card_number',
                    'El número de tarjeta no es válido según la verificación bancaria',
                );
            }

            if (
                ! $validator->errors()->has('card_expiry')
                && is_string($cardExpiry)
                && $cardExpiry !== ''
                && $this->isCardExpired($cardExpiry)
            ) {
                $validator->errors()->add(
                    'card_expiry',
                    'La tarjeta ingresada se encuentra vencida',
                );
            }

            if (
                ! $validator->errors()->has('card_number')
                && ! $validator->errors()->has('card_cvv')
                && is_string($cardNumber)
                && is_string($cardCvv)
                && $cardNumber !== ''
                && $cardCvv !== ''
            ) {
                $isAmex = str_starts_with($cardNumber, '34') || str_starts_with($cardNumber, '37');
                $expectedLength = $isAmex ? 4 : 3;

                if (strlen($cardCvv) !== $expectedLength) {
                    $validator->errors()->add(
                        'card_cvv',
                        $isAmex
                            ? 'El código de seguridad (CVV) debe contener 4 dígitos para tarjetas American Express'
                            : 'El código de seguridad (CVV) debe contener 3 dígitos',
                    );
                }
            }
        });
    }

    /**
     * Normaliza los campos de tarjeta y SPEI antes de ejecutar la validación.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('payment_method') && is_string($this->input('payment_method'))) {
            $normalized['payment_method'] = strtolower(trim($this->input('payment_method')));
        }

        if ($this->has('card_number') && is_string($this->input('card_number'))) {
            $cleanedNumber = preg_replace('/[\s-]+/', '', $this->input('card_number')) ?? '';
            $normalized['card_number'] = $cleanedNumber !== '' ? $cleanedNumber : null;
        }

        if ($this->has('card_expiry') && is_string($this->input('card_expiry'))) {
            $cleanedExpiry = preg_replace('/\s+/', '', trim($this->input('card_expiry'))) ?? '';
            $normalized['card_expiry'] = $cleanedExpiry !== '' ? $cleanedExpiry : null;
        }

        if ($this->has('card_cvv') && is_string($this->input('card_cvv'))) {
            $cleanedCvv = preg_replace('/\s+/', '', $this->input('card_cvv')) ?? '';
            $normalized['card_cvv'] = $cleanedCvv !== '' ? $cleanedCvv : null;
        }

        if ($this->has('card_holder_name') && is_string($this->input('card_holder_name'))) {
            $cleanedHolder = preg_replace('/\s+/u', ' ', trim($this->input('card_holder_name'))) ?? '';
            $normalized['card_holder_name'] = $cleanedHolder !== '' ? $cleanedHolder : null;
        }

        if ($this->has('spei_sender_bank') && is_string($this->input('spei_sender_bank'))) {
            $cleanedBank = trim($this->input('spei_sender_bank'));
            $normalized['spei_sender_bank'] = $cleanedBank !== '' ? $cleanedBank : null;
        }

        if ($this->has('spei_account_holder') && is_string($this->input('spei_account_holder'))) {
            $cleanedSpeiHolder = preg_replace('/\s+/u', ' ', trim($this->input('spei_account_holder'))) ?? '';
            $normalized['spei_account_holder'] = $cleanedSpeiHolder !== '' ? $cleanedSpeiHolder : null;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Evalúa si la fecha MM/AA es anterior al mes calendario vigente.
     *
     * @param string $expiry Fecha en formato MM/AA.
     * @return bool
     */
    private function isCardExpired(string $expiry): bool
    {
        [$monthPart, $yearPart] = explode('/', $expiry);
        $expiryEndOfMonth = Carbon::createFromDate(2000 + (int) $yearPart, (int) $monthPart, 1)->endOfMonth();

        return $expiryEndOfMonth->lt(now()->startOfMonth());
    }

    /**
     * Valida el dígito verificador del número de tarjeta mediante Luhn.
     *
     * @param string $digits Número de tarjeta sin separadores.
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
