<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ErrorPagesTest.php
 * Ubicación:   tests/Feature/ErrorPagesTest.php
 * Actividad:   ACT-WEB-055 (MPW-V3 §6.6.2, §7.3, §8.2)
 * Descripción: Las páginas de error 403, 404 y 500 usan la plantilla común,
 *              los componentes del UI Kit y muestran el folio de incidente.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_page_uses_institutional_layout(): void
    {
        $this->get('/esta-ruta-no-existe')
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertSee('Folio de incidente')
            ->assertSee('Volver al inicio')
            ->assertDontSee('text-[10px]', false);
    }

    public function test_403_page_is_shown_when_a_student_opens_a_teacher_section(): void
    {
        $student = User::create([
            'role' => 'student',
            'first_name' => 'Ana',
            'last_name' => 'López',
            'email' => 'alumna.403@example.test',
            'password' => 'Demo1234',
            'is_active' => true,
            'two_factor_verified_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('questions.index'))
            ->assertForbidden()
            ->assertSee('Acceso restringido')
            ->assertSee('Folio de incidente');
    }

    public function test_500_view_renders(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString('Error interno del servidor', $html);
        $this->assertStringContainsString('Plataforma Evaluadora de Exámenes. Todos los derechos reservados.', $html);
    }

    public function test_pages_use_only_official_branding(): void
    {
        $html = $this->get('/ruta-que-no-existe-para-prueba')->getContent();

        $this->assertStringContainsString('images/branding/logo-fondo-claro.png', $html);
        $this->assertStringContainsString('images/branding/isotipo.png', $html);
        $this->assertStringNotContainsString('M20 4L4 32', $html);

        $logo = $this->blade('<x-logo variant="compact" />');
        $logo->assertSee('images/branding/isotipo.png', false);
        $logo->assertDontSee('<svg', false);
    }
}
