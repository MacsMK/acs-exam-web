<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AccountProfileTest.php
 * Ubicación:   tests/Feature/Auth/AccountProfileTest.php
 * Requerimiento: RF-AUT-013 (ACT-WEB-050)
 * Descripción: El alumno y el superadministrador consultan sus datos, el aviso
 *              de privacidad y cierran sesión desde "Mi perfil"; el docente
 *              conserva su propio perfil.
 * ============================================================================
 */

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_account_data_privacy_and_logout(): void
    {
        $student = $this->createUser('student');

        $this->actingAs($student)
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee($student->email)
            ->assertSee('Estudiante')
            ->assertSee('Abrir aviso de privacidad')
            ->assertSee(route('auth.logout'), false);
    }

    public function test_super_admin_sees_account_profile(): void
    {
        $admin = $this->createUser('super_admin');

        $this->actingAs($admin)
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSee('Administrador')
            ->assertSee($admin->email);
    }

    public function test_user_menu_links_to_profile_for_student(): void
    {
        $student = $this->createUser('student');

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('account.profile'), false);
    }

    public function test_teacher_uses_teacher_profile_instead(): void
    {
        $teacher = $this->createUser('teacher');

        $this->actingAs($teacher)
            ->get(route('account.profile'))
            ->assertForbidden();
    }

    private function createUser(string $role): User
    {
        return User::create([
            'role' => $role,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => uniqid($role.'-', true).'@example.test',
            'phone' => '5512345678',
            'password' => 'Demo1234',
            'is_active' => true,
            'two_factor_verified_at' => now(),
        ]);
    }
}
