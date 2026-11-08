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

use RuntimeException;

use App\Models\PrivacyAcceptance;
use App\Models\PrivacyNotice;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AuthService
{
    /**
     * Vigencia del código de verificación, en minutos (RF-AUT-005).
     */
    public const TWO_FACTOR_TTL_MINUTES = 10;

    /**
     * Espera mínima, en segundos, entre dos envíos del código (RF-AUT-006).
     */
    public const TWO_FACTOR_RESEND_SECONDS = 60;

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
        $code = $this->generateUniqueCode($user);

        $user->forceFill([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(self::TWO_FACTOR_TTL_MINUTES),
        ])->save();

        Mail::raw(
            "Tu código de verificación es: {$code}\n\n"
            .'Es válido durante '.self::TWO_FACTOR_TTL_MINUTES." minutos.\n"
            ."Si no solicitaste este código, ignora este mensaje.",
            function ($message) use ($user): void {
                $message->to($user->email)
                    ->subject('Código de verificación - A-Code Solutions');
            }
        );
    }

    /**
     * Reenvía el código del segundo paso (RF-AUT-006). Solo procede si hay una
     * verificación pendiente para el correo (el código se borra al verificarse)
     * y si pasaron al menos TWO_FACTOR_RESEND_SECONDS desde el último envío,
     * que se deduce de la vigencia del código vigente. Emitir uno nuevo
     * sobrescribe el anterior, por lo que este queda invalidado.
     *
     * @return User|null Cuenta a la que se reenvió; null si no hay verificación
     *                   pendiente (la API responde igual para no revelar qué
     *                   cuentas existen).
     *
     * @throws TooManyRequestsHttpException Si aún no transcurren los 60 segundos.
     */
    public function resendTwoFactorCode(string $email): ?User
    {
        $user = User::where('email', $email)
            ->whereIn('role', ['teacher', 'student'])
            ->where('is_active', true)
            ->first();

        if ($user === null || $user->two_factor_code === null || $user->two_factor_expires_at === null) {
            return null;
        }

        $availableAt = $user->two_factor_expires_at->copy()
            ->subMinutes(self::TWO_FACTOR_TTL_MINUTES)
            ->addSeconds(self::TWO_FACTOR_RESEND_SECONDS);

        if (now()->lt($availableAt)) {
            $wait = max(1, $availableAt->getTimestamp() - now()->getTimestamp());

            throw new TooManyRequestsHttpException(
                $wait,
                "Espera {$wait} segundos para solicitar un nuevo código.",
            );
        }

        $this->issueTwoFactorCode($user);

        return $user->fresh();
    }

    /**
     * Genera un código de 6 dígitos que no coincida con el de ningún otro
     * usuario con código vigente. La API móvil localiza al usuario solo por
     * el código (Api\V1\AuthController::twoFactor), así que dos códigos
     * iguales y vigentes a la vez podrían entregar el token de la cuenta
     * equivocada.
     */
    private function generateUniqueCode(User $user): string
    {
        $attempts = 0;
        $maxAttempts = 50;

        do {
            $attempts++;
            if ($attempts > $maxAttempts) {
                throw new RuntimeException('No fue posible generar un código 2FA único tras múltiples intentos.');
            }

            $code = (string) random_int(100000, 999999);

            $inUse = User::where('two_factor_code', $code)
                ->where('two_factor_expires_at', '>', now())
                ->where('id', '!=', $user->id)
                ->exists();
        } while ($inUse);

        return $code;
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
