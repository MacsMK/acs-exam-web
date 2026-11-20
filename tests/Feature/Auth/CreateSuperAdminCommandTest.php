<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     CreateSuperAdminCommandTest.php
 * Ubicación:   tests/Feature/Auth/CreateSuperAdminCommandTest.php
 * Requerimiento: RF-AUT-010 (ACT-WEB-049)
 * Descripción: El superadministrador solo se crea por consola, con datos
 *              validados, confirmación y registro de auditoría; no existe
 *              registro público para este rol.
 * ============================================================================
 */

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_active_super_admin_and_audits_it(): void
    {
        $this->runCommand('admin.nuevo@example.test', 'ClaveSegura1', 'ClaveSegura1', true)
            ->assertSuccessful();

        $admin = User::where('email', 'admin.nuevo@example.test')->firstOrFail();
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue((bool) $admin->is_active);
        $this->assertTrue(Hash::check('ClaveSegura1', $admin->password));
        $this->assertTrue(AuditLog::where('action', 'super_admin.created_by_console')->where('auditable_id', $admin->id)->exists());
    }

    public function test_command_rejects_invalid_data_without_creating_the_account(): void
    {
        $this->runCommand('correo-invalido', 'corta', 'distinta', true)
            ->expectsOutput('El correo electrónico no tiene un formato válido')
            ->assertFailed();

        $this->assertSame(0, User::where('role', 'super_admin')->count());
    }

    public function test_command_does_nothing_when_the_operator_cancels(): void
    {
        $this->runCommand('admin.cancelado@example.test', 'ClaveSegura1', 'ClaveSegura1', false)
            ->assertFailed();

        $this->assertFalse(User::where('email', 'admin.cancelado@example.test')->exists());
    }

    public function test_there_is_no_public_registration_route_for_super_admins(): void
    {
        $publicRegisterRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'register'))
            ->map(fn ($route) => $route->getName())
            ->filter(fn ($name) => $name !== null && str_contains($name, 'admin'));

        $this->assertCount(0, $publicRegisterRoutes);
    }

    private function runCommand(string $email, string $password, string $confirmation, bool $confirm)
    {
        $command = $this->artisan('acs:crear-superadmin')
            ->expectsQuestion('Nombre(s)', 'Ana')
            ->expectsQuestion('Apellido(s)', 'Pérez López')
            ->expectsQuestion('Correo electrónico', $email)
            ->expectsQuestion('Teléfono (10 dígitos)', '5512345678')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', $password)
            ->expectsQuestion('Confirmar contraseña', $confirmation);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) && $password === $confirmation && strlen($password) >= 8) {
            $command->expectsConfirmation("¿Crear la cuenta de superadministrador para {$email}?", $confirm ? 'yes' : 'no');
        }

        return $command;
    }
}
