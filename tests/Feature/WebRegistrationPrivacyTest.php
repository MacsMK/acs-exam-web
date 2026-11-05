<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     WebRegistrationPrivacyTest.php
 * Ubicación:   tests/Feature/WebRegistrationPrivacyTest.php
 * Descripción: Registro por navegador (alumno y docente) con aceptación del
 *              aviso de privacidad versionado: las vistas envían el id del
 *              aviso vigente, el servidor registra la aceptación con la IP y
 *              rechaza un aviso desactualizado o ausente (RF-AUT-001,
 *              RF-AUT-002, RF-AUT-011 — MPW-V2 §7.3, §8.6.1).
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\PrivacyAcceptance;
use App\Models\PrivacyNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebRegistrationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_views_send_current_notice_id_and_show_its_version_and_text(): void
    {
        $notice = PrivacyNotice::create([
            'version' => '3.1',
            'content' => 'Texto del aviso vigente para la prueba.',
            'published_at' => now()->subMinute(),
        ]);

        foreach (['auth.register.student.show', 'auth.register.teacher.show'] as $route) {
            $response = $this->get(route($route));

            $response->assertOk();
            $response->assertSee('name="privacy_notice_id" value="'.$notice->id.'"', false);
            $response->assertSee('Versión 3.1');
            $response->assertSee('Texto del aviso vigente para la prueba.');
            $response->assertSee('id="register-form"', false);
        }
    }

    public function test_notice_not_yet_published_is_not_offered(): void
    {
        PrivacyNotice::create([
            'version' => '9.9',
            'content' => 'Aviso futuro.',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('auth.register.student.show'));

        $response->assertOk();
        $response->assertDontSee('name="privacy_notice_id"', false);
        $response->assertDontSee('Aviso futuro.');
    }

    public function test_student_web_registration_stores_acceptance_with_ip_and_opens_2fa_session(): void
    {
        $notice = PrivacyNotice::create([
            'version' => '1.0',
            'content' => 'Aviso vigente.',
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->post(route('auth.register.student.store'), [
            'privacy_notice_id' => (string) $notice->id,
            'first_name' => 'Ana María',
            'last_name' => 'López',
            'email' => 'ana.web@example.test',
            'phone' => '5512345678',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'accepts_privacy_notice' => '1',
        ]);

        $user = User::where('email', 'ana.web@example.test')->firstOrFail();

        $response->assertRedirect(route('auth.verify-2fa.show', ['user' => $user->id]));
        $response->assertSessionHas('pending_2fa_user_id', $user->id);

        $this->assertDatabaseHas('acs_privacy_acceptances', [
            'user_id' => $user->id,
            'privacy_notice_id' => $notice->id,
        ]);
        $this->assertNotNull(
            PrivacyAcceptance::where('user_id', $user->id)->value('ip_address')
        );
    }

    public function test_teacher_web_registration_requires_phone_and_current_notice(): void
    {
        $notice = PrivacyNotice::create([
            'version' => '1.0',
            'content' => 'Aviso vigente.',
            'published_at' => now()->subMinute(),
        ]);

        $payload = [
            'privacy_notice_id' => (string) $notice->id,
            'first_name' => 'Fernando',
            'last_name' => 'Castillo',
            'email' => 'fer.web@example.test',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'accepts_privacy_notice' => '1',
        ];

        // Docente: el teléfono es obligatorio (RF-AUT-002).
        $this->post(route('auth.register.teacher.store'), $payload)
            ->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('acs_users', ['email' => 'fer.web@example.test']);

        $this->post(route('auth.register.teacher.store'), $payload + ['phone' => '5598765432'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('acs_users', ['email' => 'fer.web@example.test', 'role' => 'teacher']);
    }

    public function test_registration_is_rejected_when_notice_changed_or_is_missing(): void
    {
        $old = PrivacyNotice::create([
            'version' => '1.0',
            'content' => 'Primer aviso.',
            'published_at' => now()->subHours(2),
        ]);
        PrivacyNotice::create([
            'version' => '2.0',
            'content' => 'Aviso actualizado.',
            'published_at' => now()->subHour(),
        ]);

        $base = [
            'first_name' => 'Luis',
            'last_name' => 'Pérez',
            'email' => 'luis.web@example.test',
            'phone' => '5512345678',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'accepts_privacy_notice' => '1',
        ];

        // Se mostró la versión 1.0 y mientras tanto se publicó la 2.0.
        $this->post(route('auth.register.student.store'), $base + ['privacy_notice_id' => (string) $old->id])
            ->assertSessionHasErrors('accepts_privacy_notice');

        // Sin id de aviso (formulario manipulado).
        $this->post(route('auth.register.student.store'), $base)
            ->assertSessionHasErrors('accepts_privacy_notice');

        $this->assertDatabaseMissing('acs_users', ['email' => 'luis.web@example.test']);
        $this->assertDatabaseCount('acs_privacy_acceptances', 0);
    }
}
