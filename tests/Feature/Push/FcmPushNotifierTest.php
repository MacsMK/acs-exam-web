<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        FcmPushNotifierTest.php
 * Ubicación:      tests/Feature/Push/FcmPushNotifierTest.php
 * Requerimiento:  Notificaciones push (MPM-V4 §6.8, Tabla 14); código de
 *                 DE-01 pendiente de registro
 * Autor:          Calvo Sandoval Miguel Alexander (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Verifica el envío por Firebase Cloud Messaging HTTP v1 sin
 *                 salir a internet (Http::fake): el JWT de la cuenta de
 *                 servicio se firma con RS256 y lleva el alcance correcto, el
 *                 mensaje respeta el contrato de la Tabla 14, los tokens no
 *                 registrados se eliminan y ningún fallo lanza excepciones.
 * ============================================================================
 */

namespace Tests\Feature\Push;

use App\Contracts\PushNotifierContract;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Push\FcmPushNotifier;
use App\Services\Push\NullPushNotifier;
use App\Support\PushMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmPushNotifierTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private string $credentialsPath;

    private string $publicKey;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fcm-test-');
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'proyecto-de-prueba',
            'client_email' => 'push@proyecto-de-prueba.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => self::TOKEN_URL,
        ]));

        config([
            'services.fcm.enabled' => true,
            'services.fcm.credentials' => $this->credentialsPath,
            'services.fcm.project_id' => null,
        ]);

        $this->student = User::create([
            'role' => 'student',
            'first_name' => 'Luis',
            'last_name' => 'Gómez',
            'email' => uniqid('student-', true).'@example.test',
            'password' => 'Demo1234',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);

        parent::tearDown();
    }

    public function test_it_sends_the_table_14_payload_to_every_device_of_the_user(): void
    {
        $this->fakeGoogle();
        $this->registerDevice('token-a');
        $this->registerDevice('token-b');

        $accepted = $this->notifier()->sendToUsers([$this->student->id], $this->message());

        $this->assertSame(2, $accepted);

        $sentTokens = [];
        Http::assertSent(function (Request $request) use (&$sentTokens): bool {
            if (! str_contains($request->url(), 'fcm.googleapis.com')) {
                return false;
            }

            $this->assertSame('https://fcm.googleapis.com/v1/projects/proyecto-de-prueba/messages:send', $request->url());
            $this->assertSame('Bearer google-access-token', $request->header('Authorization')[0]);

            $message = $request->data()['message'];
            $sentTokens[] = $message['token'];
            $this->assertSame(['type' => 'exam.opened', 'exam_id' => '7', 'group_id' => '3'], $message['data']);
            $this->assertSame('Examen disponible', $message['notification']['title']);

            return true;
        });
        $this->assertEqualsCanonicalizing(['token-a', 'token-b'], $sentTokens);
    }

    public function test_the_oauth_assertion_is_a_valid_rs256_jwt_with_the_firebase_scope(): void
    {
        $this->fakeGoogle();
        $this->registerDevice('token-a');

        $this->notifier()->sendToUsers([$this->student->id], $this->message());

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== self::TOKEN_URL) {
                return false;
            }

            $data = $request->data();
            $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $data['grant_type']);

            [$header, $claims, $signature] = explode('.', $data['assertion']);
            $decode = static fn (string $part): string => base64_decode(strtr($part, '-_', '+/'));

            $this->assertSame('RS256', json_decode($decode($header), true)['alg']);
            $payload = json_decode($decode($claims), true);
            $this->assertSame('push@proyecto-de-prueba.iam.gserviceaccount.com', $payload['iss']);
            $this->assertSame('https://www.googleapis.com/auth/firebase.messaging', $payload['scope']);
            $this->assertSame(self::TOKEN_URL, $payload['aud']);
            $this->assertSame(3600, $payload['exp'] - $payload['iat']);
            $this->assertSame(
                1,
                openssl_verify($header.'.'.$claims, $decode($signature), $this->publicKey, OPENSSL_ALGO_SHA256),
            );

            return true;
        });
    }

    public function test_the_access_token_is_reused_between_sends(): void
    {
        $this->fakeGoogle();
        $this->registerDevice('token-a');

        $this->notifier()->sendToUsers([$this->student->id], $this->message());
        $this->notifier()->sendToUsers([$this->student->id], $this->message());

        $tokenRequests = Http::recorded(static fn (Request $request): bool => $request->url() === self::TOKEN_URL);
        $this->assertCount(1, $tokenRequests);
    }

    public function test_unregistered_tokens_are_deleted_and_valid_ones_still_receive_the_message(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'google-access-token']),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                ->push(['name' => 'projects/proyecto-de-prueba/messages/1'], 200),
        ]);
        $this->registerDevice('token-old');
        $this->registerDevice('token-new');

        $accepted = $this->notifier()->sendToUsers([$this->student->id], $this->message());

        $this->assertSame(1, $accepted);
        $this->assertSame(1, UserDevice::query()->count());
    }

    public function test_a_server_error_from_fcm_keeps_the_device_and_does_not_throw(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'google-access-token']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'INTERNAL']], 500),
        ]);
        $this->registerDevice('token-a');

        $accepted = $this->notifier()->sendToUsers([$this->student->id], $this->message());

        $this->assertSame(0, $accepted);
        $this->assertSame(1, UserDevice::query()->count());
    }

    public function test_missing_credentials_are_reported_without_contacting_google(): void
    {
        Http::fake();
        config(['services.fcm.credentials' => '/ruta/que/no/existe.json']);
        $this->registerDevice('token-a');

        $accepted = $this->notifier()->sendToUsers([$this->student->id], $this->message());

        $this->assertSame(0, $accepted);
        Http::assertNothingSent();
    }

    public function test_users_without_devices_do_not_trigger_any_request(): void
    {
        Http::fake();

        $this->assertSame(0, $this->notifier()->sendToUsers([$this->student->id], $this->message()));
        $this->assertSame(0, $this->notifier()->sendToUsers([], $this->message()));
        Http::assertNothingSent();
    }

    public function test_the_container_only_uses_firebase_when_fcm_is_enabled(): void
    {
        $this->assertInstanceOf(FcmPushNotifier::class, $this->app->make(PushNotifierContract::class));

        config(['services.fcm.enabled' => false]);

        $this->assertInstanceOf(NullPushNotifier::class, $this->app->make(PushNotifierContract::class));
    }

    public function test_the_disabled_notifier_never_contacts_firebase(): void
    {
        Http::fake();
        config(['services.fcm.enabled' => false]);
        $this->registerDevice('token-a');

        $accepted = $this->app->make(PushNotifierContract::class)->sendToUsers([$this->student->id], $this->message());

        $this->assertSame(0, $accepted);
        Http::assertNothingSent();
    }

    private function notifier(): FcmPushNotifier
    {
        return $this->app->make(FcmPushNotifier::class);
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'google-access-token', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/proyecto-de-prueba/messages/1']),
        ]);
    }

    private function registerDevice(string $token): void
    {
        UserDevice::create(['user_id' => $this->student->id, 'fcm_token' => $token, 'platform' => 'android']);
    }

    private function message(): PushMessage
    {
        return new PushMessage(PushMessage::EXAM_OPENED, 'Examen disponible', 'El examen ya está disponible.', 7, 3);
    }
}
