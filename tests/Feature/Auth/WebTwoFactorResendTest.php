<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     WebTwoFactorResendTest.php
 * Ubicación:   tests/Feature/Auth/WebTwoFactorResendTest.php
 * Requerimiento: RF-AUT-006 (ACT-WEB-047)
 * Descripción: Reenvío del código del segundo paso en la web: espera de 60
 *              segundos desde el último envío, invalidación del código
 *              anterior, disponible para todos los roles y solo con una
 *              verificación pendiente en la sesión.
 * ============================================================================
 */

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WebTwoFactorResendTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_verification_screen_shows_resend_button(): void
    {
        $user = $this->createUserWithPendingCode('teacher');

        $this->withSession(['pending_2fa_user_id' => $user->id])
            ->get(route('auth.verify-2fa.show', ['user' => $user->id]))
            ->assertOk()
            ->assertSee('Reenviar código');
    }

    public function test_resend_after_sixty_seconds_issues_new_code_and_invalidates_previous(): void
    {
        Carbon::setTestNow('2026-11-19 10:00:00');
        $user = $this->createUserWithPendingCode('super_admin');
        $previousCode = $user->two_factor_code;

        Carbon::setTestNow('2026-11-19 10:01:00');
        $this->withSession(['pending_2fa_user_id' => $user->id])
            ->post(route('auth.verify-2fa.resend'))
            ->assertRedirect(route('auth.verify-2fa.show', ['user' => $user->id]))
            ->assertSessionHas('status');

        $newCode = $user->fresh()->two_factor_code;
        $this->assertNotSame($previousCode, $newCode);

        $this->withSession(['pending_2fa_user_id' => $user->id])
            ->post(route('auth.verify-2fa.store'), ['user_id' => $user->id, 'code' => $previousCode])
            ->assertSessionHasErrors('code');
    }

    public function test_resend_before_sixty_seconds_is_rejected_and_keeps_current_code(): void
    {
        Carbon::setTestNow('2026-11-19 10:00:00');
        $user = $this->createUserWithPendingCode('student');
        $currentCode = $user->two_factor_code;

        Carbon::setTestNow('2026-11-19 10:00:30');
        $this->withSession(['pending_2fa_user_id' => $user->id])
            ->post(route('auth.verify-2fa.resend'))
            ->assertSessionHasErrors(['code' => 'Espera 30 segundos para solicitar un nuevo código.']);

        $this->assertSame($currentCode, $user->fresh()->two_factor_code);
    }

    public function test_resend_without_pending_verification_is_forbidden(): void
    {
        $this->post(route('auth.verify-2fa.resend'))->assertForbidden();
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
