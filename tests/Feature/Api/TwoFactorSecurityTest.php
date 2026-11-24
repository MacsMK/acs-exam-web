<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     TwoFactorSecurityTest.php
 * Ubicación:   tests/Feature/Api/TwoFactorSecurityTest.php
 * Descripción: Protecciones del segundo factor en la API móvil: `demo_code`
 *              solo aparece cuando el correo no sale de verdad, los códigos
 *              vigentes de distintos usuarios nunca coinciden y el endpoint
 *              de verificación tiene límite de intentos.
 * ============================================================================
 */

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TwoFactorSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_demo_seed_account_can_verify_without_logging_the_code(): void
    {
        $this->app->instance('env', 'local');
        config(['mail.default' => 'log', 'auth.two_factor_demo' => true]);
        Mail::shouldReceive('raw')->never();
        $user = $this->createStudent();
        $user->update(['email' => 'alumno0.demo@acode-solutions.test']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Demo1234',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.demo_code', $user->fresh()->two_factor_code);
        $this->postJson('/api/v1/auth/two-factor', ['code' => $response->json('data.demo_code')])
            ->assertOk()->assertJsonPath('data.role', 'student');
    }

    public function test_login_never_returns_the_code_when_mail_is_real(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake(); // no se abre ninguna conexión SMTP real en la prueba
        $user = $this->createStudent();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Demo1234',
        ]);

        $response->assertOk();
        $this->assertNotNull($user->fresh()->two_factor_code);
        $this->assertStringNotContainsString($user->fresh()->two_factor_code, $response->getContent());
        $response->assertJsonMissingPath('data.demo_code');
    }

    /** Comprueba que un transporte que escribe al log no se considere una entrega. */
    public function test_login_requires_delivery_even_with_a_log_fallback_configured(): void
    {
        config(['mail.default' => 'failover', 'auth.two_factor_demo' => true]);
        Mail::shouldReceive('raw')->never();
        $user = $this->createStudent();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Demo1234'])
            ->assertUnprocessable()->assertJsonMissingPath('data.demo_code');
        $this->assertNull($user->fresh()->two_factor_code);
    }

    /** Comprueba el transporte efectivo cuando la URL sustituye el tipo configurado. */
    public function test_a_mail_url_cannot_substitute_logging_for_delivery(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.url' => 'log://localhost']);
        Mail::shouldReceive('raw')->never();
        $user = $this->createStudent();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Demo1234'])
            ->assertUnprocessable()->assertJsonMissingPath('data.demo_code');
        $this->assertNull($user->fresh()->two_factor_code);
    }

    /** Comprueba que el indicador de demo no tenga efecto fuera de local y testing. */
    public function test_production_cannot_issue_a_demo_code(): void
    {
        $this->app->instance('env', 'production');
        config(['mail.default' => 'log', 'auth.two_factor_demo' => true]);
        Mail::shouldReceive('raw')->never();
        $user = $this->createStudent();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Demo1234'])
            ->assertUnprocessable()->assertJsonMissingPath('data.demo_code');
        $this->assertNull($user->fresh()->two_factor_code);
    }

    /** Comprueba que la demostración solo aplica a cuentas con dominio sintético. */
    public function test_demo_configuration_never_grants_an_ordinary_account_a_code(): void
    {
        config(['mail.default' => 'log', 'auth.two_factor_demo' => true]);
        Mail::shouldReceive('raw')->never();
        $user = $this->createStudent();
        $user->update(['email' => 'cuenta-local@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Demo1234'])
            ->assertUnprocessable()->assertJsonMissingPath('data.demo_code');
        $this->assertNull($user->fresh()->two_factor_code);
    }

    /** Comprueba que una segunda lectura del mismo desafío ya consumido no pueda verificarse. */
    public function test_consumption_uses_current_challenge_instead_of_a_stale_copy(): void
    {
        $service = app(AuthService::class);
        $user = $this->createStudent();
        $service->issueTwoFactorCode($user);
        $stale = $user->fresh();
        $code = $user->two_factor_code;

        $service->verifyTwoFactorCode($user, $code);
        $this->assertNull($user->fresh()->two_factor_code);
        $this->assertNotNull($user->fresh()->two_factor_verified_at);

        $this->expectException(ValidationException::class);
        $service->verifyTwoFactorCode($stale, $code);
    }

    public function test_register_never_returns_the_code_when_mail_is_real(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake(); // no se abre ninguna conexión SMTP real en la prueba

        $response = $this->postJson('/api/v1/auth/register/student', [
            'first_name' => 'Luis',
            'last_name' => 'Gómez',
            'email' => 'nuevo-alumno@example.test',
            'password' => 'Demo1234',
            'password_confirmation' => 'Demo1234',
            'accepts_privacy_notice' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonMissingPath('data.demo_code');
    }

    public function test_live_codes_of_different_users_never_collide(): void
    {
        $service = app(AuthService::class);
        $users = collect(range(1, 40))->map(fn () => $this->createStudent());

        $users->each(fn (User $user) => $service->issueTwoFactorCode($user));

        $codes = $users->map(fn (User $user) => $user->fresh()->two_factor_code);

        $this->assertCount(40, $codes->unique());
    }

    public function test_two_factor_endpoint_limits_attempts(): void
    {
        $last = null;

        for ($i = 0; $i < 21; $i++) {
            $last = $this->postJson('/api/v1/auth/two-factor', ['code' => '000000']);
        }

        $last->assertStatus(429);
        $last->assertJsonPath('status', 'error');
    }

    private function createStudent(): User
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
}
