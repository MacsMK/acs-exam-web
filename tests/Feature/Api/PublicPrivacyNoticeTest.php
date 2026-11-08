<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     PublicPrivacyNoticeTest.php
 * Ubicación:   tests/Feature/Api/PublicPrivacyNoticeTest.php
 * Descripción: api.v1.privacy.current (ADR-034): la app móvil lee el aviso de
 *              privacidad vigente sin sesión, antes de registrarse
 *              (RF-AUT-011), y el registro de alumno guarda la aceptación de
 *              esa misma versión.
 * ============================================================================
 */

namespace Tests\Feature\Api;

use App\Models\PrivacyNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPrivacyNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_reads_current_notice_without_accepted_flag(): void
    {
        PrivacyNotice::create([
            'version' => 'v1.0',
            'content' => 'Texto anterior.',
            'published_at' => now()->subDays(2),
        ]);
        PrivacyNotice::create([
            'version' => 'v2.0',
            'content' => 'Texto vigente del aviso.',
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/api/v1/privacy-notice/current');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.version', 'v2.0');
        $response->assertJsonPath('data.title', 'Aviso de Privacidad v2.0');
        $response->assertJsonPath('data.content', 'Texto vigente del aviso.');
        $this->assertArrayNotHasKey('accepted', $response->json('data'));
    }

    public function test_current_notice_is_not_found_when_none_is_published(): void
    {
        $this->getJson('/api/v1/privacy-notice/current')->assertNotFound();
    }

    public function test_authenticated_notice_route_still_requires_a_token(): void
    {
        PrivacyNotice::create([
            'version' => 'v1.0',
            'content' => 'Contenido de prueba.',
            'published_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/v1/privacy-notice')->assertUnauthorized();
    }

    public function test_student_registration_after_reading_the_notice_records_that_version(): void
    {
        $notice = PrivacyNotice::create([
            'version' => 'v3.0',
            'content' => 'Aviso mostrado antes del registro.',
            'published_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/v1/privacy-notice/current')
            ->assertOk()
            ->assertJsonPath('data.version', 'v3.0');

        $response = $this->postJson('/api/v1/auth/register/student', [
            'first_name' => 'Luis',
            'last_name' => 'Pérez',
            'email' => 'luis.perez@example.test',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'accepts_privacy_notice' => true,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('acs_privacy_acceptances', [
            'user_id' => $response->json('data.user_id'),
            'privacy_notice_id' => $notice->id,
        ]);
    }

    public function test_student_registration_requires_accepting_the_notice(): void
    {
        PrivacyNotice::create([
            'version' => 'v3.0',
            'content' => 'Aviso mostrado antes del registro.',
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/auth/register/student', [
            'first_name' => 'Luis',
            'last_name' => 'Pérez',
            'email' => 'luis.perez@example.test',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'accepts_privacy_notice' => false,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.accepts_privacy_notice.0', 'Debes aceptar el aviso de privacidad para continuar');
    }
}
