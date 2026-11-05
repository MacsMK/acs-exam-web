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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Obtiene el aviso de privacidad publicado más reciente.
     */
    public function currentPrivacyNotice(): ?PrivacyNotice
    {
        return PrivacyNotice::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Registra una cuenta web y la aceptación del aviso mostrado.
     *
     * @param array<string, mixed> $data
     */
    public function registerWeb(
        array $data,
        string $role,
        mixed $noticeId,
        ?string $ipAddress
    ): User {
        $notice = $this->currentPrivacyNotice();

        if (
            ! $notice ||
            ! is_string($noticeId) ||
            $noticeId !== (string) $notice->id
        ) {
            throw ValidationException::withMessages([
                'accepts_privacy_notice' => [
                    'El aviso de privacidad cambió o no está disponible. Recarga la página y revísalo nuevamente.',
                ],
            ]);
        }

        return DB::transaction(function () use ($data, $role, $notice, $ipAddress): User {
            $user = $this->register(
                Arr::except($data, ['accepts_privacy_notice', 'privacy_accepted']),
                $role,
            );

            PrivacyAcceptance::create([
                'user_id' => $user->id,
                'privacy_notice_id' => $notice->id,
                'accepted_at' => now(),
                'ip_address' => $ipAddress,
            ]);

            return $user;
        });
    }

    /**
     * Crea un usuario con el rol indicado dentro de una transacción, registra
     * la aceptación del aviso de privacidad vigente si el llamador la indica
     * (RF-AUT-011, flujo de la API) y emite el primer código 2FA, para que el
     * registro → verificación sea uno solo.
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
                'email' => ['Las credenciales ingresadas no coinciden con nuestros registros'],
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
     * Genera un código de seis dígitos y lo envía por correo.
     */
    public function issueTwoFactorCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        Mail::raw(
            "Tu código de verificación es: {$code}\n\n"
            ."Es válido durante 10 minutos.\n"
            ."Si no solicitaste este código, ignora este mensaje.",
            function ($message) use ($user): void {
                $message->to($user->email)
                    ->subject('Código de verificación - A-Code Solutions');
            }
        );
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
