<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AuthService.php
 * Ubicación:      app/Services/AuthService.php
 * Requerimiento:  RF-AUT-001..011, RF-PRI-001, ADR-031, ADR-035, ADR-057
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Servicio transaccional compartido entre el panel web y la
 *                 API móvil v1 para registro de docentes y alumnos, acreditación
 *                 docente híbrida (ADR-057), aceptación de aviso de privacidad
 *                 versionado, inicio de sesión y verificación 2FA por correo.
 * ============================================================================
 */

namespace App\Services;

use App\Models\PrivacyAcceptance;
use App\Models\PrivacyNotice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Servicio transaccional de autenticación, registro híbrido y 2FA.
 *
 * @package App\Services
 */
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

    /** Intentos de verificación permitidos por código (RF-AUT-007). */
    public const TWO_FACTOR_MAX_ATTEMPTS = 5;

    /** Mensaje cuando el código agotó sus intentos (MPW-V3 Tabla 18, plantilla 7). */
    public const TWO_FACTOR_LOCKED_MESSAGE = 'Superaste los 5 intentos permitidos para este código. Solicita un nuevo código';

    /**
     * Inicializa el servicio de autenticación con el servicio de acreditación docente.
     *
     * @param TeacherVerificationService $teacherVerificationService Servicio transaccional de verificación docente.
     * @param TwoFactorDelivery $twoFactorDelivery Entrega del código de verificación.
     */
    public function __construct(
        private readonly TeacherVerificationService $teacherVerificationService,
        private readonly TwoFactorDelivery $twoFactorDelivery,
    ) {
    }

    /**
     * Obtiene el aviso de privacidad publicado más reciente.
     *
     * @return PrivacyNotice|null
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
     * Registra una cuenta web, su expediente de acreditación si es docente y la
     * aceptación del aviso de privacidad mostrado.
     *
     * @param array<string, mixed> $data Datos validados del registro.
     * @param string $role Rol asignado ('teacher' o 'student').
     * @param mixed $noticeId Identificador del aviso de privacidad mostrado.
     * @param string|null $ipAddress Dirección IP del cliente.
     * @param UploadedFile|null $verificationDocument Comprobante docente opcional (cuando aplica revisión documental).
     * @param string|null $userAgent User-Agent del navegador solicitante.
     * @return User Cuenta creada con código 2FA emitido.
     *
     * @throws ValidationException Si el aviso cambió o la verificación docente es inválida.
     * @throws Throwable Si ocurre un fallo transaccional.
     */
    public function registerWeb(
        array $data,
        string $role,
        mixed $noticeId,
        ?string $ipAddress,
        ?UploadedFile $verificationDocument = null,
        ?string $userAgent = null,
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

        $storedDocumentPath = null;

        try {
            return DB::transaction(function () use (
                $data,
                $role,
                $notice,
                $ipAddress,
                $verificationDocument,
                $userAgent,
                &$storedDocumentPath,
            ): User {
                $user = $this->register(
                    Arr::except($data, ['accepts_privacy_notice', 'privacy_accepted']),
                    $role,
                    $verificationDocument,
                    $ipAddress,
                    $userAgent,
                );

                if ($role === 'teacher') {
                    $storedDocumentPath = $user->teacherVerification()->value('document_path');
                }

                PrivacyAcceptance::create([
                    'user_id' => $user->id,
                    'privacy_notice_id' => $notice->id,
                    'accepted_at' => now(),
                    'ip_address' => $ipAddress,
                ]);

                return $user;
            });
        } catch (Throwable $e) {
            $this->teacherVerificationService->purgeDocumentPath($storedDocumentPath);
            throw $e;
        }
    }

    /**
     * Crea un usuario con el rol indicado dentro de una transacción, registra
     * la acreditación híbrida cuando el rol es docente, guarda la aceptación del
     * aviso de privacidad vigente si el llamador la indica (RF-AUT-011) y emite
     * el primer código 2FA.
     *
     * @param array<string, mixed> $data Datos validados del registro.
     * @param string $role Rol asignado ('teacher' o 'student').
     * @param UploadedFile|null $verificationDocument Comprobante documental para registro docente sin código.
     * @param string|null $ipAddress Dirección IP del solicitante.
     * @param string|null $userAgent User-Agent del solicitante.
     * @return User Cuenta creada.
     *
     * @throws ValidationException Si la acreditación docente falla en validación de negocio.
     * @throws Throwable Si ocurre un error en persistencia o envío 2FA.
     */
    public function register(
        array $data,
        string $role,
        ?UploadedFile $verificationDocument = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): User {
        $storedDocumentPath = null;

        try {
            return DB::transaction(function () use (
                $data,
                $role,
                $verificationDocument,
                $ipAddress,
                $userAgent,
                &$storedDocumentPath,
            ): User {
                $user = User::create([
                    'role' => $role,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                    'is_active' => true,
                ]);

                if ($role === 'teacher') {
                    $verification = $this->teacherVerificationService->registerTeacherVerification(
                        $user,
                        $data,
                        $verificationDocument,
                        $ipAddress ?? request()?->ip(),
                        $userAgent ?? request()?->userAgent(),
                    );
                    $storedDocumentPath = $verification->document_path;
                }

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
                                'ip_address' => $ipAddress ?? request()?->ip(),
                            ],
                        );
                    }
                }

                $this->issueTwoFactorCode($user);

                return $user;
            });
        } catch (Throwable $e) {
            $this->teacherVerificationService->purgeDocumentPath($storedDocumentPath);
            throw $e;
        }
    }

    /**
     * Paso 1 del login: valida correo+contraseña y, si son correctos,
     * emite un nuevo código 2FA. No autentica todavía.
     *
     * @param string $email Correo electrónico del usuario.
     * @param string $password Contraseña en texto plano para validar hash.
     * @return User Cuenta autenticada parcialmente pendiente de 2FA.
     *
     * @throws ValidationException Si las credenciales no coinciden o la cuenta está suspendida.
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
     * Emite un desafío nuevo para el paso de verificación.
     *
     * @param User $user Cuenta que aprobó el primer paso.
     * @return void
     */
    public function issueTwoFactorCode(User $user): void
    {
        $this->issueTwoFactorChallenge($user, false);
    }

    /**
     * Reserva la emisión y acredita la entrega solo para ese mismo desafío.
     *
     * @param User $user Destinatario del código.
     * @param bool $resending Indica que debe existir un desafío pendiente.
     * @return bool Indica si se emitió un desafío.
     * @throws ValidationException Si el canal o la cuenta no son válidos.
     * @throws TooManyRequestsHttpException Si todavía no procede un reenvío.
     */
    private function issueTwoFactorChallenge(User $user, bool $resending): bool
    {
        $challenge = DB::transaction(function () use ($user, $resending): ?array {
            // La API móvil admite código sin correo. Solo un emisor puede reservar cada código vigente.
            DB::selectOne('SELECT pg_advisory_xact_lock(462007)');
            $current = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($resending && ($current->two_factor_code === null || ! $current->is_active)) {
                return null;
            }

            if (! $current->is_active) {
                throw ValidationException::withMessages(['email' => ['Esta cuenta está suspendida. Contacta al administrador.']]);
            }

            if ($resending) {
                $wait = $this->secondsUntilResendAvailable($current);
                if ($wait > 0) {
                    throw new TooManyRequestsHttpException($wait, "Espera {$wait} segundos para solicitar un nuevo código.");
                }
            }

            $channel = $this->twoFactorDelivery->channelFor($current);
            $code = $this->generateUniqueCode($current);
            $challengeId = (string) Str::uuid();
            $current->forceFill([
                'two_factor_challenge_id' => $challengeId,
                'two_factor_code' => $code,
                'two_factor_expires_at' => now()->addMinutes(self::TWO_FACTOR_TTL_MINUTES),
                'two_factor_failed_attempts' => 0,
                'two_factor_delivery' => null,
            ])->save();

            return [$current, $challengeId, $code, $channel];
        });

        if ($challenge === null) {
            return false;
        }

        [$current, $challengeId, $code, $channel] = $challenge;
        $this->twoFactorDelivery->send($current, $code, $channel);

        User::query()->whereKey($current->getKey())
            ->where('two_factor_challenge_id', $challengeId)
            ->where('email', $current->email)
            ->where('is_active', true)
            ->update(['two_factor_delivery' => $channel]);

        $user->refresh();

        return true;
    }

    /**
     * Devuelve el código solamente para un desafío de demostración autorizado.
     *
     * @param User $user Cuenta de la sesión pendiente.
     * @return string|null Código de demostración vigente.
     */
    public function demoTwoFactorCode(User $user): ?string
    {
        if ($user->two_factor_delivery !== 'demo' || ! $this->twoFactorDelivery->permits($user)
            || ! $user->two_factor_expires_at?->isFuture()
            || $user->two_factor_failed_attempts >= self::TWO_FACTOR_MAX_ATTEMPTS) {
            return null;
        }

        return $user->two_factor_code;
    }

    /**
     * Reenvía el código del segundo paso (RF-AUT-006). Solo procede si hay una
     * verificación pendiente para el correo (el código se borra al verificarse)
     * y si pasaron al menos TWO_FACTOR_RESEND_SECONDS desde el último envío.
     *
     * @param string $email Correo electrónico de la cuenta.
     * @return User|null Cuenta a la que se reenvió; null si no hay verificación pendiente.
     *
     * @throws TooManyRequestsHttpException Si aún no transcurren los 60 segundos.
     */
    public function resendTwoFactorCode(string $email): ?User
    {
        $user = User::where('email', $email)
            ->whereIn('role', ['teacher', 'student'])
            ->where('is_active', true)
            ->first();

        return $user === null ? null : $this->resendTwoFactorCodeForUser($user);
    }

    /**
     * Reenvía el código del segundo paso a una cuenta con verificación pendiente.
     *
     * @param User $user Cuenta con verificación pendiente.
     * @return User|null Cuenta con el nuevo código o nulo si ya no hay un desafío pendiente.
     * @throws TooManyRequestsHttpException Si aún no transcurren los 60 segundos.
     */
    public function resendTwoFactorCodeForUser(User $user): ?User
    {
        return $this->issueTwoFactorChallenge($user, true) ? $user : null;
    }

    /**
     * Segundos que faltan para poder solicitar un nuevo código (0 si ya se puede).
     *
     * @param User $user Cuenta con verificación pendiente.
     * @return int
     */
    public function secondsUntilResendAvailable(User $user): int
    {
        if ($user->two_factor_expires_at === null) {
            return 0;
        }

        $availableAt = $user->two_factor_expires_at->copy()
            ->subMinutes(self::TWO_FACTOR_TTL_MINUTES)
            ->addSeconds(self::TWO_FACTOR_RESEND_SECONDS);

        return max(0, $availableAt->getTimestamp() - now()->getTimestamp());
    }

    /**
     * Paso 2 del login: valida el código de un solo uso y limpia el estado 2FA.
     *
     * @param User $user Cuenta que completa el segundo factor.
     * @param string $code Código numérico de 6 dígitos ingresado.
     * @return void
     *
     * @throws ValidationException Si el código no coincide o ya expiró.
     */
    public function verifyTwoFactorCode(User $user, string $code): void
    {
        $error = DB::transaction(function () use ($user, $code): ?string {
            $current = User::query()->lockForUpdate()->find($user->getKey());
            if ($current === null || ! $current->is_active || ! $this->twoFactorDelivery->permits($current)
                || $current->two_factor_code === null || ! $current->two_factor_expires_at?->isFuture()) {
                return 'El código es inválido o expiró. Solicita uno nuevo.';
            }

            if ($current->two_factor_failed_attempts >= self::TWO_FACTOR_MAX_ATTEMPTS) {
                return self::TWO_FACTOR_LOCKED_MESSAGE;
            }

            if (! hash_equals($current->two_factor_code, $code)) {
                $current->increment('two_factor_failed_attempts');

                return $current->two_factor_failed_attempts >= self::TWO_FACTOR_MAX_ATTEMPTS
                    ? self::TWO_FACTOR_LOCKED_MESSAGE
                    : 'El código es inválido o expiró. Solicita uno nuevo.';
            }

            $current->forceFill([
                'two_factor_challenge_id' => null,
                'two_factor_code' => null,
                'two_factor_expires_at' => null,
                'two_factor_failed_attempts' => 0,
                'two_factor_delivery' => null,
                'two_factor_verified_at' => now(),
            ])->save();

            return null;
        });

        // La validación se comunica después de confirmar la transacción del contador.
        if ($error !== null) {
            throw ValidationException::withMessages(['code' => [$error]]);
        }

        $user->refresh();
    }

    /**
     * Cierra la sesión autenticada en el guard web.
     *
     * @param User|null $user Usuario opcional que cierra sesión.
     * @return void
     */
    public function logout(?User $user = null): void
    {
        Auth::guard('web')->logout();
    }

    /**
     * Genera un código de 6 dígitos que no coincida con el de ningún otro
     * usuario con código vigente para evitar colisiones en la API móvil.
     *
     * @param User $user Cuenta para la que se emite el código.
     * @return string Código numérico de 6 dígitos único vigente.
     *
     * @throws RuntimeException Si excede la cota máxima de intentos.
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

            $inUse = $code === $user->two_factor_code || User::where('two_factor_code', $code)
                ->where('two_factor_expires_at', '>', now())
                ->exists();
        } while ($inUse);

        return $code;
    }
}
