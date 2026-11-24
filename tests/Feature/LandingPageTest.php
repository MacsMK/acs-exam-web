<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        LandingPageTest.php
 * Ubicación:      tests/Feature/LandingPageTest.php
 * Requerimiento:  RF-SUS-001, RF-SUS-008, RW-01, MC-V3
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Pruebas de renderizado y visualización de planes en la Landing Page.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\SubscriptionPackage;
use App\Services\SubscriptionLimitService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_displays_saas_packages_and_branding(): void
    {
        $this->seed(SubscriptionPackageSeeder::class);

        $response = $this->get(route('landing'))
            ->assertOk()
            ->assertSeeText('A-Code Solutions')
            ->assertSeeText('Analizamos, diseñamos, transformamos')
            ->assertSeeText('Créditos de IA ilimitados')
            ->assertViewHas('freemiumLimits', SubscriptionLimitService::FREEMIUM_LIMITS)
            ->assertSeeText(SubscriptionLimitService::FREEMIUM_MAX_CUMULATIVE_GROUPS . ' grupo y ' . SubscriptionLimitService::FREEMIUM_MAX_CUMULATIVE_EXAMS . ' examen en total')
            ->assertSeeText(SubscriptionLimitService::FREEMIUM_MAX_STUDENTS_PER_GROUP . ' alumnos por grupo')
            ->assertSeeText(SubscriptionLimitService::FREEMIUM_MAX_QUESTIONS . ' reactivos y ' . SubscriptionLimitService::FREEMIUM_MAX_AI_EVALUATIONS . ' créditos de IA')
            ->assertSee(route('auth.register.teacher.show'));

        foreach (SubscriptionPackage::orderBy('monthly_price')->get() as $package) {
            $response->assertSeeText($package->name)
                ->assertSeeText('$' . number_format((float) $package->monthly_price, 2));
        }
    }

    public function test_landing_reflects_changes_to_active_prices_and_all_five_quotas(): void
    {
        $this->seed(SubscriptionPackageSeeder::class);
        SubscriptionPackage::where('tier', 'basic')->update([
            'name' => 'Docente personalizado',
            'monthly_price' => 348.75,
            'max_groups' => 19,
            'max_questions' => 2134,
            'max_concurrent_students' => 87,
            'max_active_exams' => 31,
            'ai_requests_limit' => 729,
        ]);
        SubscriptionPackage::where('tier', 'advanced')->update([
            'name' => 'Paquete retirado',
            'is_active' => false,
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSeeText('Docente personalizado')
            ->assertSeeText('$348.75')
            ->assertSeeText('19 grupos escolares activos')
            ->assertSeeText('2,134 reactivos en banco')
            ->assertSeeText('87 alumnos inscritos por grupo')
            ->assertSeeText('31 exámenes activos simultáneos')
            ->assertSeeText('729 créditos de IA por ciclo mensual')
            ->assertDontSeeText('Paquete retirado')
            ->assertDontSeeText('$599.00')
            ->assertDontSeeText('$299');
    }
}
