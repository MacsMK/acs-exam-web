<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     TwoFactorResendTest.php
 * Ubicación:   tests/Feature/Api/TwoFactorResendTest.php
 * Descripción: Reenvío del código del segundo paso en la API móvil
 *              (RF-AUT-006, ADR-035): espera de 60 segundos desde el último
 *              envío, invalidación del código anterior, respuesta uniforme
 *              para cuentas sin verificación pendiente y límite de intentos.
 * ============================================================================
 */

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorResendTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_resend_after_sixty_seconds_issues_a_new_code_and_invalidates_the_previous_one(): void
    {
        config(['mail.default' => 'log']);
        Carbon::setTestNow('2026-11-09 10:00:00');
        $user = $this->createUserWithPendingCode();
        $previousCode = $user->two_factor_code;

        Carbon::setTestNow('2026-11-09 10:01:00');
        $response = $this->postJson('/api/v1/auth/two-factor/resend', ['email' => $user->email]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $newCode = $user->fresh()->two_factor_code;
        $this->assertNotSame($previousCode, $newCode);
        $response->assertJsonPath('data.demo_code', $newCode);

        // El código anterior ya no autentica; el nuevo sí (vigencia reiniciada).
        $this->postJson('/api/v1/auth/two-factor', ['code' => $previousCode])->assertStatus(422);
        $this->postJson('/api/v1/auth/two-factor', ['code' => $newCode])
            ->assertOk()
            ->assertJsonPath('data.role', 'student');
    }

    public function test_resend_before_sixty_seconds_is_rejected_with_429_and_keeps_the_current_code(): void
    {
        Carbon::setTestNow('2026-11-09 10:00:00');
        $user = $this->createUserWithPendingCode();
        $currentCode = $user->two_factor_code;

        Carbon::setTestNow('2026-11-09 10:00:30');
        $response = $this->postJson('/api/v1/auth/two-factor/resend', ['email' => $user->email]);

        $response->assertStatus(429);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('message', 'Espera 30 segundos para solicitar un nuevo código.');
        $this->assertSame($currentCode, $user->fresh()->two_factor_code);
    }

    public function test_resend_is_allowed_exactly_at_the_sixty_second_mark(): void
    {
        config(['mail.default' => 'log']);
        Carbon::setTestNow('2026-11-09 10:00:00');
        $user = $this->createUserWithPendingCode();
        $previousCode = $user->two_factor_code;

        Carbon::setTestNow('2026-11-09 10:00:59');
        $this->postJson('/api/v1/auth/two-factor/resend', ['email' => $user->email])->assertStatus(429);

        Carbon::setTestNow('2026-11-09 10:01:00');
        $this->postJson('/api/v1/auth/two-factor/resend', ['email' => $user->email])->assertOk();
        $this->assertNotSame($previousCode, $user->fresh()->two_factor_code);
    }

    public function test_the_new_code_restarts_the_wait_interval(): void
    {
        config(['mail.default' => 'log']);
        Carbon::setTestNow('2026-11-09 10:00:00');
        $user = $this->createUserWithPendingCode();

        Carbon::setTestNow('2026-11-09 10:01:00');
        $this->postJson('/api/v1/auth/two-factor/resend', ['email' => $user->email])->assertOk();

        Carbon::setTestNow('2026-11-09 10:01:20');
        $this->postJson('/api/v1/auth/two-factor/resend', ['email' => $user->email])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Espera 40 segundos para solicitar un nuevo código.');
    }

    public function test_resend_sends_the_new_code_by_mail_when_mail_is_real(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        Carbon::setTestNow('2026-11-09 10:00:00');
        $user = $this->createUserWithPendingCode();

        Carbon::setTestNow('2026-11-09 10:01:00');
        $response = $this->postJson('/api/v1/auth/two-factor/resend', ['email' => $user->email]);

        $response->assertOk();
        $response->assertJsonMissingPath('data.demo_code');
        $this->assertStringNotContainsString($user->fresh()->two_factor_code, $response->getContent());
    }

    public function test_resend_for_an_unknown_email_answers_the_same_and_sends_nothing(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/two-factor/resend', ['email' => 'nadie@example.test']);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonMissingPath('data.demo_code');
    }

    public function test_resend_does_nothing_when_the_account_has_no_pending_verification(): void
    {
        Carbon::setTestNow('2026-11-09 10:00:00');
        $user = $this->createUser();
        $this->assertNull($user->two_factor_code);

        $response = $this->postJson('/api/v1/auth/two-factor/resend', ['email' => $user->email]);

        $response->assertOk();
        $this->assertNull($user->fresh()->two_factor_code);
    }

    public function test_resend_ignores_suspended_accounts(): void
    {
        Carbon::setTestNow('2026-11-09 10:00:00');
        $user = $this->createUserWithPendingCode();
        $previousCode = $user->two_factor_code;
        $user->forceFill(['is_active' => false])->save();

        Carbon::setTestNow('2026-11-09 10:05:00');
        $this->postJson('/api/v1/auth/two-factor/resend', ['email' => $user->email])->assertOk();

        $this->assertSame($previousCode, $user->fresh()->two_factor_code);
    }

    public function test_resend_requires_a_valid_email(): void
    {
        $this->postJson('/api/v1/auth/two-factor/resend', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El campo correo electrónico es obligatorio');

        $this->postJson('/api/v1/auth/two-factor/resend', ['email' => 'no-es-correo'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El correo electrónico no tiene un formato válido');
    }

    public function test_resend_endpoint_limits_attempts(): void
    {
        $last = null;

        for ($i = 0; $i < 11; $i++) {
            $last = $this->postJson('/api/v1/auth/two-factor/resend', ['email' => 'nadie@example.test']);
        }

        $last->assertStatus(429);
        $last->assertJsonPath('status', 'error');
    }

    private function createUser(): User
    {
        return User::create([
            'role' => 'student',
            'first_name' => 'Luis',
            'last_name' => 'Gómez',
            'email' => uniqid('student-', true).'@example.test',
            'password' => 'Demo1234',
            'is_active' => true,
        ]);
    }

    private function createUserWithPendingCode(): User
    {
        $user = $this->createUser();
        app(AuthService::class)->issueTwoFactorCode($user);

        return $user->fresh();
    }
}
