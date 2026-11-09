<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\PrivacyNotice;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private SubscriptionPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'super_admin',
            'first_name' => 'Admin',
            'last_name' => 'A-Code',
            'email' => 'admin.test@acode-solutions.test',
        ]);

        $this->teacher = User::factory()->create([
            'role' => 'teacher',
            'first_name' => 'Profesor',
            'last_name' => 'Prueba',
            'email' => 'profe.test@acode-solutions.test',
            'is_active' => true,
        ]);

        $this->package = SubscriptionPackage::create([
            'name' => 'Pro Departamental',
            'tier' => 'pro',
            'monthly_price' => 799.00,
            'max_groups' => 10,
            'max_questions' => 500,
            'max_active_exams' => 10,
            'max_concurrent_students' => 150,
            'ai_requests_limit' => 500,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_sidebar_shows_only_admin_options(): void
    {
        TeacherSubscription::create([
            'teacher_id' => $this->teacher->id,
            'subscription_package_id' => $this->package->id,
            'payment_status' => 'paid',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.teachers.index'));

        $response->assertOk();
        $response->assertSee('Panel de Administración');
        $response->assertSee('Gestión de Docentes');
        $response->assertSee('Pagado');
        $response->assertDontSee('Banco de preguntas');
        $response->assertDontSee('Suspendido (RW-05)');
        $response->assertDontSee('(RW-05)');
    }

    public function test_teacher_role_cannot_access_super_admin_panel(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.teachers.index'));

        $response->assertForbidden();
    }

    public function test_super_admin_can_toggle_teacher_status_with_clean_flash_message(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.teachers.toggle', $this->teacher));

        $response->assertRedirect();
        $response->assertSessionHas('status', "La cuenta del docente {$this->teacher->full_name} ha sido suspendida exitosamente.");

        $this->assertFalse($this->teacher->fresh()->is_active);
    }

    public function test_super_admin_can_assign_package_and_see_status_updated_in_realtime(): void
    {
        // 1. Asignar Suspendido
        $response = $this->actingAs($this->admin)->post(route('admin.teachers.assign', $this->teacher), [
            'subscription_package_id' => $this->package->id,
            'payment_status' => 'suspended',
            'notes' => 'Suspensión por falta de pago',
        ]);

        $response->assertRedirect();
        $this->assertSame('suspended', $this->teacher->fresh()->subscription?->payment_status);

        $indexResponse = $this->actingAs($this->admin)->get(route('admin.teachers.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Suspendido');

        // 2. Asignar Pagado
        $response2 = $this->actingAs($this->admin)->post(route('admin.teachers.assign', $this->teacher), [
            'subscription_package_id' => $this->package->id,
            'payment_status' => 'paid',
            'notes' => 'Pago validado',
        ]);

        $response2->assertRedirect();
        $this->assertSame('paid', $this->teacher->fresh()->subscription?->payment_status);

        $indexResponse2 = $this->actingAs($this->admin)->get(route('admin.teachers.index'));
        $indexResponse2->assertOk();
        $indexResponse2->assertSee('Pagado');
    }

    public function test_super_admin_cannot_assign_invalid_payment_status(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.teachers.assign', $this->teacher), [
            'subscription_package_id' => $this->package->id,
            'payment_status' => 'failed',
        ]);

        $response->assertSessionHasErrors('payment_status');
    }
}
