<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupReopenLimitTest.php
 * Ubicación:   tests/Feature/GroupReopenLimitTest.php
 * Requerimiento: RF-GRU-014, RF-GRU-020 (ACT-WEB-051)
 * Descripción: Reabrir un grupo cerrado vuelve a ocupar un lugar del límite
 *              de grupos activos; si el límite está completo, se rechaza.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\Group;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupReopenLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPackageSeeder::class);
    }

    public function test_reopen_is_rejected_when_basic_plan_already_has_its_active_groups(): void
    {
        $teacher = $this->teacherWithPlan('basic');
        $closed = $this->group($teacher, 'Cerrado', true);
        for ($i = 1; $i <= 3; $i++) {
            $this->group($teacher, "Activo {$i}", false);
        }

        $this->actingAs($teacher)
            ->from(route('groups.show', $closed))
            ->post(route('groups.reopen', $closed))
            ->assertSessionHasErrors('subscription');

        $this->assertTrue((bool) $closed->fresh()->is_closed);
    }

    public function test_reopen_is_allowed_when_there_is_room_in_the_plan(): void
    {
        $teacher = $this->teacherWithPlan('basic');
        $closed = $this->group($teacher, 'Cerrado', true);
        $this->group($teacher, 'Activo 1', false);

        $this->actingAs($teacher)
            ->post(route('groups.reopen', $closed))
            ->assertRedirect(route('groups.show', $closed))
            ->assertSessionHas('status');

        $this->assertFalse((bool) $closed->fresh()->is_closed);
    }

    public function test_free_trial_reopens_its_single_group(): void
    {
        $teacher = $this->teacher();
        $closed = $this->group($teacher, 'Prueba', true);

        $this->actingAs($teacher)
            ->post(route('groups.reopen', $closed))
            ->assertSessionHas('status');

        $this->assertFalse((bool) $closed->fresh()->is_closed);
    }

    private function teacherWithPlan(string $tier): User
    {
        $teacher = $this->teacher();

        TeacherSubscription::create([
            'teacher_id' => $teacher->id,
            'subscription_package_id' => SubscriptionPackage::where('tier', $tier)->firstOrFail()->id,
            'payment_status' => 'paid',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        return $teacher->fresh();
    }

    private function teacher(): User
    {
        return User::create([
            'role' => 'teacher',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => uniqid('teacher-', true).'@example.test',
            'phone' => '5512345678',
            'password' => 'Demo1234',
            'is_active' => true,
            'two_factor_verified_at' => now(),
        ]);
    }

    private function group(User $teacher, string $name, bool $closed): Group
    {
        return Group::create([
            'teacher_id' => $teacher->id,
            'name' => $name,
            'access_folio' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'is_closed' => $closed,
        ]);
    }
}
