<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     WebLogoutConfirmationTest.php
 * Ubicación:   tests/Feature/Auth/WebLogoutConfirmationTest.php
 * Requerimiento: RF-AUT-012 (ACT-WEB-048)
 * Descripción: El menú de usuario de la web solicita confirmación antes de
 *              cerrar la sesión y, al confirmarse, el servidor invalida la
 *              sesión y regresa al inicio de sesión.
 * ============================================================================
 */

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebLogoutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_menu_asks_for_confirmation_before_logout(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-logout-form', false)
            ->assertSee('¿Cerrar sesión?');
    }

    public function test_confirmed_logout_ends_the_session(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student)
            ->post(route('auth.logout'))
            ->assertRedirect(route('auth.login.show'));

        $this->assertGuest();
    }

    private function createStudent(): User
    {
        return User::create([
            'role' => 'student',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => uniqid('student-', true).'@example.test',
            'password' => 'Demo1234',
            'is_active' => true,
            'two_factor_verified_at' => now(),
        ]);
    }
}
