<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        LandingPageTest.php
 * Ubicación:      tests/Feature/LandingPageTest.php
 * Requerimiento:  RF-SUS-001, RF-SUS-008, RW-01, MC-V2
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Pruebas de renderizado y visualización de planes en la Landing Page.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\PrivacyNotice;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_displays_saas_packages_and_branding(): void
    {
        SubscriptionPackage::create([
            'tier' => 'basic',
            'name' => 'Básico',
            'max_groups' => 3,
            'max_questions' => 5000,
            'max_concurrent_students' => 40,
            'max_active_exams' => 5,
            'monthly_price' => 299.00,
            'is_active' => true,
        ]);

        SubscriptionPackage::create([
            'tier' => 'advanced',
            'name' => 'Avanzado',
            'max_groups' => 10,
            'max_questions' => 10000,
            'max_concurrent_students' => 120,
            'max_active_exams' => 25,
            'monthly_price' => 599.00,
            'is_active' => true,
        ]);

        PrivacyNotice::create([
            'version' => 'v1.0.0',
            'content' => 'Aviso oficial de A-Code Solutions.',
            'published_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('A-Code Solutions');
        $response->assertSee('Analizamos, diseñamos, transformamos');
        $response->assertSee('Básico');
        $response->assertSee('$299.00');
        $response->assertSee('Avanzado');
        $response->assertSee('$599.00');
        $response->assertSee('Prueba Gratuita Freemium');
        $response->assertSee('RF-SUS-008');
        $response->assertSee(route('auth.register.teacher.show'));
    }
}
