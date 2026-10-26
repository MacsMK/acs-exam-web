<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AuthService.php
 * Ubicación:   app/Services/AuthService.php
 * Descripción: Lógica de registro/login/2FA compartida entre el panel web
 *              y la API móvil v1, para no duplicarla entre controladores.
 *              2FA: código de un solo uso de 6 dígitos (MPM-V2 §6.6.1).
 *              En este entorno de DEMO el código se escribe también al log
 *              (storage/logs/laravel.log) además de "enviarse" por correo,
 *              para que se pueda probar sin bandeja de entrada real.
 * ============================================================================
 */

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Crea un usuario con el rol indicado y le genera ya el primer código
     * 2FA, para que el flujo de registro → verificación sea uno solo.
     *
     * @param array<string, mixed> $data
     */
    public function register(array $data, string $role): User
    {
        $user = User::create([
            'role' => $role,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        $this->issueTwoFactorCode($user);

        return $user;
    }

    /**
     * Paso 1 del login: valida correo+contraseña y, si son correctos,
     * emite un nuevo código 2FA. No autentica todavía.
     *
     * @throws ValidationException
     */
    public function attemptCredentials(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no coinciden con ningún registro.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Esta cuenta está suspendida. Contacta al administrador.'],
            ]);
        }

        $this->issueTwoFactorCode($user);

        return $user;
    }

    /**
     * Genera y "envía" (log, en demo) un código de 6 dígitos válido 10
     * minutos.
     */
    public function issueTwoFactorCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        Log::info("[2FA DEMO] Código para {$user->email}: {$code} (válido 10 min)");
    }

    /**
     * Paso 2 del login: valida el código de un solo uso.
     *
     * @throws ValidationException
     */
    public function verifyTwoFactorCode(User $user, string $code): void
    {
        $isValid = $user->two_factor_code === $code
            && $user->two_factor_expires_at !== null
            && $user->two_factor_expires_at->isFuture();

        if (! $isValid) {
            throw ValidationException::withMessages([
                'code' => ['El código es inválido o expiró. Solicita uno nuevo.'],
            ]);
        }

        $user->forceFill([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
            'two_factor_verified_at' => now(),
        ])->save();
    }
}
