<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AuthService.php
 * Ubicación:   app/Services/AuthService.php
 * Descripción: Lógica transaccional de registro/login/2FA/aceptación de aviso
 *              de privacidad (RF-PRI-001, MPM-V2 §6.6.1) compartida entre el
 *              panel web y la API móvil v1.
 * ============================================================================
 */

namespace App\Services;

use App\Models\PrivacyAcceptance;
use App\Models\PrivacyNotice;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Crea un usuario con el rol indicado dentro de una transacción, registra
     * la aceptación del aviso de privacidad vigente si aplica (RF-PRI-001) y
     * emite el primer código 2FA.
     *
     * @param array<string, mixed> $data
     */
    public function register(array $data, string $role): User
    {
        return DB::transaction(function () use ($data, $role): User {
            $user = User::create([
                'role' => $role,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $privacyAccepted = (bool) ($data['accepts_privacy_notice'] ?? $data['privacy_accepted'] ?? false);
            if ($privacyAccepted) {
                $currentNotice = PrivacyNotice::latest('published_at')->first();
                if ($currentNotice !== null) {
                    PrivacyAcceptance::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'privacy_notice_id' => $currentNotice->id,
                        ],
                        [
                            'accepted_at' => now(),
                            'ip_address' => request()?->ip(),
                        ],
                    );
                }
            }

            $this->issueTwoFactorCode($user);

            return $user;
        });
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

    /**
     * Cierra la sesión autenticada en el guard web.
     */
    public function logout(?User $user = null): void
    {
        Auth::guard('web')->logout();
    }
}
