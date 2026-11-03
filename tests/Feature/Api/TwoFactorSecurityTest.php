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
use Tests\TestCase;

class TwoFactorSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_includes_demo_code_when_mail_is_not_delivered(): void
    {
        config(['mail.default' => 'log']);
        $user = $this->createStudent();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Demo1234',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.demo_code', $user->fresh()->two_factor_code);
    }

    public function test_login_never_returns_the_code_when_mail_is_real(): void
    {
        config(['mail.default' => 'smtp']);
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

    public function test_register_never_returns_the_code_when_mail_is_real(): void
    {
        config(['mail.default' => 'smtp']);

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
