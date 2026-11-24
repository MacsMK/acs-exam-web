<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        OperationalComplianceTest.php
 * Ubicación:      tests/Feature/Admin/OperationalComplianceTest.php
 * Requerimiento:  RF-ADM-002, 010 a 012 y 015
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Verifica el directorio, las consultas operativas y la publicación de términos.
 * ============================================================================
 */

namespace Tests\Feature\Admin;

use App\Exceptions\SubscriptionLimitExceededException;
use App\Models\AiCreditUsage;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use App\Services\ExamAssemblyService;
use App\Services\GroupEnrollmentService;
use App\Services\SubscriptionLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class OperationalComplianceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Comprueba el alcance del directorio estudiantil y su acceso por rol.
     *
     * @return void
     */
    public function test_student_directory_is_administrative_and_limited_to_admins(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => false]);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $route = route('admin.students.index');
        $this->actingAs($admin)->get($route)->assertOk()->assertSee($student->email)
            ->assertSee('Inactiva')->assertDontSee($teacher->email);
        $this->actingAs($teacher)->get($route)->assertForbidden();
    }

    /**
     * Verifica la persistencia del consumo al borrar recursos y la privacidad del informe.
     *
     * @return void
     */
    public function test_trial_consumption_survives_deletion_and_only_operational_fields_are_exposed(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $group = app(GroupEnrollmentService::class)->createGroup($teacher, ['name' => 'Grupo sintético']);
        $assembly = app(ExamAssemblyService::class);
        $exam = $assembly->createExam($teacher, [
            'title' => 'Contenido académico reservado', 'grading_scale' => '0-100',
            'result_visibility_mode' => 'final_score', 'max_allowed_incidents' => 3,
            'starts_at' => now(), 'ends_at' => now()->addHour(), 'group_ids' => [$group->id],
        ]);
        AiCreditUsage::create(['teacher_id' => $teacher->id, 'credits_consumed' => 2, 'is_reevaluation' => false,
            'model_used' => 'Modelo privado', 'status' => AiCreditUsage::STATUS_CONSUMED]);
        $this->seed(\Database\Seeders\SubscriptionPackageSeeder::class);
        $package = SubscriptionPackage::where('tier', 'basic')->firstOrFail();
        PaymentTransaction::create(['subscription_package_id' => $package->id, 'reference_code' => 'REN-TEST', 'teacher_id' => $teacher->id,
            'gateway_provider' => 'simulated', 'payment_method' => 'card', 'status' => 'paid',
            'amount' => 299, 'currency' => 'MXN', 'concept' => 'renewal']);
        $route = route('admin.operations.teacher', $teacher);
        $this->actingAs($admin)->get($route)->assertOk()->assertSee('Borrador')->assertSee('Abierto')
            ->assertSee('2 créditos')->assertSee('23')->assertSee('Pagado')
            ->assertDontSee('Contenido académico reservado')->assertDontSee('Modelo privado');
        $subscription = TeacherSubscription::create(['teacher_id' => $teacher->id, 'subscription_package_id' => $package->id,
            'payment_status' => 'paid', 'starts_at' => now(), 'expires_at' => now()->addMonth()]);
        $subscription->delete();
        $this->assertDatabaseMissing('acs_teacher_subscriptions', ['id' => $subscription->id]);
        $assembly->deleteDraftExam($exam);
        $group->delete();
        $this->get($route)->assertOk()->assertSee('Eliminado')->assertDontSee('Sin consumo');
        foreach (['ensureCanCreateGroup', 'ensureCanCreateExam'] as $method) {
            try {
                app(SubscriptionLimitService::class)->$method($teacher->fresh());
                $this->fail('La prueba ya fue consumida');
            } catch (SubscriptionLimitExceededException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->actingAs($teacher)->get($route)->assertForbidden();
    }

    /**
     * Valida la activación por fecha y hora y la conservación de versiones anteriores.
     *
     * @return void
     */
    public function test_terms_versions_take_effect_on_the_published_date_and_preserve_history(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->travelTo(today()->setTime(12, 0));
        $this->actingAs($admin);
        $route = route('admin.terms.store');
        foreach ([['v1', 'Primer texto aprobado', today()], ['v2', 'Segundo texto aprobado', today()->addDay()]] as [$version, $text, $date]) {
            $this->post($route, ['version' => $version, 'content' => $text, 'effective_at' => $date->toDateString()])
                ->assertRedirect(route('admin.terms.index'))->assertSessionHasNoErrors();
        }
        $this->post($route, ['version' => 'v2', 'content' => 'Reemplazo', 'effective_at' => today()->toDateString()])
            ->assertSessionHasErrors(['version', 'effective_at']);
        $this->post($route, ['version' => 'v1.1', 'content' => 'Texto desde la tarde',
            'effective_at' => today()->format('Y-m-d').'T16:30'])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('admin.terms.index'))->assertOk()->assertSee('Primer texto aprobado')->assertSee('Segundo texto aprobado');
        Auth::logout();
        $this->get(route('terms.public'))->assertOk()->assertSee('Primer texto aprobado')->assertDontSee('Segundo texto aprobado');
        foreach (['student', 'teacher'] as $role) {
            $this->get(route("auth.register.$role.show"))->assertOk()->assertSee('Primer texto aprobado')->assertDontSee('Segundo texto aprobado');
        }
        $this->travelTo(today()->setTime(16, 29));
        $this->get(route('terms.public'))->assertSee('Primer texto aprobado')->assertDontSee('Texto desde la tarde');
        $this->travel(1)->minutes();
        $this->get(route('terms.public'))->assertSee('Texto desde la tarde')->assertDontSee('Primer texto aprobado');
        $this->travel(1)->days();
        $this->get(route('auth.register.student.show'))->assertSee('Segundo texto aprobado')->assertDontSee('Primer texto aprobado');
        $this->assertDatabaseCount('acs_terms_versions', 3);
    }
}
