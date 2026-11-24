<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     GroupEditTest.php
 * Ubicación:   tests/Feature/GroupEditTest.php
 * Requerimiento: RF-GRU-001, RF-GRU-003 (ACT-WEB-020, BD01-2026-032)
 * Descripción: Descripción opcional al crear un grupo y modificación de
 *              nombre, materia y descripción solo en grupos activos y por
 *              el docente que los administra.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPackageSeeder::class);
    }

    public function test_teacher_creates_a_group_with_optional_description(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)
            ->post(route('groups.store'), [
                'name' => 'Auditoría 9A',
                'subject' => 'Auditoría de Sistemas',
                'description' => 'Turno matutino',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('acs_groups', [
            'teacher_id' => $teacher->id,
            'name' => 'Auditoría 9A',
            'description' => 'Turno matutino',
        ]);
    }

    public function test_teacher_edits_name_subject_and_description_of_an_active_group(): void
    {
        $teacher = $this->teacher();
        $group = $this->group($teacher, false);

        $this->actingAs($teacher)
            ->get(route('groups.edit', $group))
            ->assertOk()
            ->assertSee('Editar grupo');

        $this->actingAs($teacher)
            ->put(route('groups.update', $group), [
                'name' => 'Auditoría 9B',
                'subject' => '',
                'description' => 'Grupo de recuperación',
            ])
            ->assertRedirect(route('groups.show', $group))
            ->assertSessionHas('status');

        $group->refresh();
        $this->assertSame('Auditoría 9B', $group->name);
        $this->assertNull($group->subject);
        $this->assertSame('Grupo de recuperación', $group->description);
        $this->assertSame('FOLIO123', $group->access_folio);
    }

    public function test_closed_group_cannot_be_edited(): void
    {
        $teacher = $this->teacher();
        $group = $this->group($teacher, true);

        $this->actingAs($teacher)
            ->get(route('groups.edit', $group))
            ->assertRedirect(route('groups.show', $group))
            ->assertSessionHasErrors('group');

        $this->actingAs($teacher)
            ->put(route('groups.update', $group), ['name' => 'Otro nombre'])
            ->assertSessionHasErrors('group');

        $this->assertSame('Auditoría 9A', $group->fresh()->name);
    }

    public function test_another_teacher_cannot_edit_the_group(): void
    {
        $group = $this->group($this->teacher(), false);

        $status = $this->actingAs($this->teacher())
            ->put(route('groups.update', $group), ['name' => 'Intruso'])
            ->getStatusCode();

        // 403 por la verificación de propiedad o 404 si el ámbito global oculta el grupo
        $this->assertContains($status, [403, 404]);
        $this->assertSame('Auditoría 9A', $group->fresh()->name);
    }

    public function test_description_longer_than_500_characters_is_rejected(): void
    {
        $teacher = $this->teacher();
        $group = $this->group($teacher, false);

        $this->actingAs($teacher)
            ->put(route('groups.update', $group), [
                'name' => 'Auditoría 9A',
                'description' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('description');
    }

    private function group(User $teacher, bool $closed): Group
    {
        return Group::create([
            'teacher_id' => $teacher->id,
            'name' => 'Auditoría 9A',
            'access_folio' => 'FOLIO123',
            'is_closed' => $closed,
            'closed_at' => $closed ? now() : null,
        ]);
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
}
