<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     TwoFactorAttemptLimitTest.php
 * Ubicación:   tests/Feature/Auth/TwoFactorAttemptLimitTest.php
 * Requerimiento: RF-AUT-007 (ACT-WEB-037)
 * Descripción: Máximo cinco intentos de verificación por código en la web y
 *              en la API; al quinto fallo el código queda invalidado y solo
 *              se acepta uno nuevo.
 * ============================================================================
 */

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TwoFactorAttemptLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_web_accepts_the_valid_code_after_four_failed_attempts(): void
    {
        $user = $this->createUserWithPendingCode('teacher');

        for ($i = 0; $i < 4; $i++) {
            $this->webVerify($user, $this->wrongCode($user))->assertSessionHasErrors('code');
        }

        $this->webVerify($user, $user->two_factor_code)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_invalidates_the_code_after_the_fifth_failed_attempt(): void
    {
        $user = $this->createUserWithPendingCode('super_admin');

        for ($i = 0; $i < 5; $i++) {
            $response = $this->webVerify($user, $this->wrongCode($user));
        }

        $response->assertSessionHasErrors(['code' => AuthService::TWO_FACTOR_LOCKED_MESSAGE]);
        $this->assertSame(5, $user->fresh()->two_factor_failed_attempts);

        $this->webVerify($user, $user->two_factor_code)
            ->assertSessionHasErrors(['code' => AuthService::TWO_FACTOR_LOCKED_MESSAGE]);
        $this->assertGuest();
    }

    public function test_a_new_code_restarts_the_attempt_counter(): void
    {
        Carbon::setTestNow('2026-11-19 10:00:00');
        $user = $this->createUserWithPendingCode('student');

        for ($i = 0; $i < 5; $i++) {
            $this->webVerify($user, $this->wrongCode($user));
        }

        Carbon::setTestNow('2026-11-19 10:01:00');
        $this->withSession(['pending_2fa_user_id' => $user->id])
            ->post(route('auth.verify-2fa.resend'))
            ->assertSessionHas('status');

        $fresh = $user->fresh();
        $this->assertSame(0, $fresh->two_factor_failed_attempts);
        $this->webVerify($fresh, $fresh->two_factor_code)->assertRedirect(route('dashboard'));
    }

    public function test_api_counts_failed_attempts_when_the_email_is_sent(): void
    {
        $user = $this->createUserWithPendingCode('student');

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/two-factor', ['email' => $user->email, 'code' => $this->wrongCode($user)])
                ->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/two-factor', ['email' => $user->email, 'code' => $this->wrongCode($user)])
            ->assertStatus(422)
            ->assertJsonFragment([AuthService::TWO_FACTOR_LOCKED_MESSAGE]);

        $this->postJson('/api/v1/auth/two-factor', ['email' => $user->email, 'code' => $user->two_factor_code])
            ->assertStatus(422);
    }

    private function webVerify(User $user, string $code)
    {
        return $this->withSession(['pending_2fa_user_id' => $user->id])
            ->post(route('auth.verify-2fa.store'), ['user_id' => $user->id, 'code' => $code]);
    }

    private function wrongCode(User $user): string
    {
        return $user->two_factor_code === '000000' ? '111111' : '000000';
    }

    private function createUserWithPendingCode(string $role): User
    {
        $user = User::create([
            'role' => $role,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => uniqid($role.'-', true).'@example.test',
            'phone' => '5512345678',
            'password' => 'Demo1234',
            'is_active' => true,
        ]);

        app(AuthService::class)->issueTwoFactorCode($user);

        return $user->fresh();
    }
}
