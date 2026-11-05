<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        FcmPushNotifier.php
 * Ubicación:      app/Services/Push/FcmPushNotifier.php
 * Requerimiento:  RF-EXA-054 (Notificación de programación o publicación
 *                 de exámenes); los avisos exam.opened, exam.started y
 *                 exam.finished aún no tienen código en DE-01
 *                 (MPM-V4 §6.8, Tabla 14)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Envío real por Firebase Cloud Messaging HTTP v1. Obtiene el
 *                 token OAuth2 firmando un JWT (RS256) con la cuenta de
 *                 servicio, sin agregar dependencias a composer. El archivo de
 *                 la cuenta de servicio NUNCA se versiona: su ruta llega por
 *                 FCM_CREDENTIALS_PATH. Elimina los tokens que FCM reporta
 *                 como no registrados y registra, sin lanzar, cualquier
 *                 fallo.
 * ============================================================================
 */

namespace App\Services\Push;

use App\Contracts\PushNotifierContract;
use App\Models\UserDevice;
use App\Support\PushMessage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FcmPushNotifier implements PushNotifierContract
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_CACHE_KEY = 'fcm.oauth_access_token';

    /** El token de Google dura 3600 s; se renueva antes de que expire. */
    private const TOKEN_CACHE_SECONDS = 3300;

    public function sendToUsers(array $userIds, PushMessage $message): int
    {
        if ($userIds === []) {
            return 0;
        }

        $devices = UserDevice::query()->whereIn('user_id', $userIds)->get();

        if ($devices->isEmpty()) {
            return 0;
        }

        try {
            $credentials = $this->credentials();
            $accessToken = $this->accessToken($credentials);
        } catch (Throwable $e) {
            Log::error('Push no enviado: no se pudo autenticar con FCM.', ['reason' => $e->getMessage()]);

            return 0;
        }

        $url = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            config('services.fcm.project_id') ?: $credentials['project_id'],
        );

        $accepted = 0;

        foreach ($devices as $device) {
            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->timeout((int) config('services.fcm.timeout', 10))
                    ->post($url, ['message' => $this->payload($device->fcm_token, $message)]);
            } catch (Throwable $e) {
                Log::warning('Push no enviado a un dispositivo.', ['device_id' => $device->id, 'reason' => $e->getMessage()]);

                continue;
            }

            if ($response->successful()) {
                $accepted++;

                continue;
            }

            if ($this->isUnregistered($response)) {
                // El token ya no existe en FCM (app desinstalada o token rotado).
                $device->delete();

                continue;
            }

            Log::warning('FCM rechazó un mensaje.', ['device_id' => $device->id, 'status' => $response->status()]);
        }

        return $accepted;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $token, PushMessage $message): array
    {
        return [
            'token' => $token,
            'notification' => ['title' => $message->title, 'body' => $message->body],
            'data' => $message->data(),
            'android' => ['priority' => 'HIGH'],
        ];
    }

    private function isUnregistered(Response $response): bool
    {
        if ($response->status() === 404) {
            return true;
        }

        foreach ((array) $response->json('error.details', []) as $detail) {
            if (($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{client_email: string, private_key: string, project_id: string, token_uri: string}
     */
    private function credentials(): array
    {
        $path = (string) config('services.fcm.credentials');

        // Una ruta relativa se resuelve desde la raíz del proyecto, no desde el
        // directorio de trabajo del proceso (public/ bajo php-fpm).
        if ($path !== '' && ! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('FCM_CREDENTIALS_PATH no apunta a un archivo existente.');
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('El archivo de la cuenta de servicio no es válido.');
        }

        return [
            'client_email' => (string) $json['client_email'],
            'private_key' => (string) $json['private_key'],
            'project_id' => (string) ($json['project_id'] ?? ''),
            'token_uri' => (string) ($json['token_uri'] ?? 'https://oauth2.googleapis.com/token'),
        ];
    }

    /**
     * @param  array{client_email: string, private_key: string, project_id: string, token_uri: string}  $credentials
     */
    private function accessToken(array $credentials): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_CACHE_SECONDS, function () use ($credentials): string {
            $response = Http::asForm()
                ->timeout((int) config('services.fcm.timeout', 10))
                ->post($credentials['token_uri'], [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->signedJwt($credentials),
                ]);

            $token = $response->json('access_token');

            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new RuntimeException('Google no entregó el token de acceso (HTTP '.$response->status().').');
            }

            return $token;
        });
    }

    /**
     * @param  array{client_email: string, private_key: string, project_id: string, token_uri: string}  $credentials
     */
    private function signedJwt(array $credentials): string
    {
        $issuedAt = time();

        $unsigned = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'
            .$this->base64Url((string) json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'],
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ]));

        $signature = '';

        if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el JWT con la llave privada de la cuenta de servicio.');
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
