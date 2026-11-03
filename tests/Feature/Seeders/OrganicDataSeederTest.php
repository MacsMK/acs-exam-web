<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        OrganicDataSeederTest.php
 * Ubicación:      tests/Feature/Seeders/OrganicDataSeederTest.php
 * Requerimiento:  N/A (verificación de herramienta de datos de demostración)
 * Autor:          Calvo Sandoval Miguel Alexander (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Comprueba que OrganicDataSeeder siembra cantidades
 *                 esperadas, correos únicos, teléfonos de 10 dígitos
 *                 (los mismos que exigen los FormRequest), nombres con
 *                 acentos, es idempotente y que las cuentas sembradas
 *                 pueden iniciar sesión por la API móvil.
 * ============================================================================
 */

namespace Tests\Feature\Seeders;

use App\Models\Group;
use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\OrganicDataSeeder;
use Database\Seeders\PrivacySeeder;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganicDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PrivacySeeder::class);
        $this->seed(SubscriptionPackageSeeder::class);
        $this->seed(OrganicDataSeeder::class);
    }

    public function test_it_seeds_the_expected_amount_of_records(): void
    {
        $this->assertSame(3, User::where('role', 'teacher')->count());
        $this->assertSame(30, User::where('role', 'student')->count());
        $this->assertSame(6, Group::count());
    }

    public function test_every_email_is_unique_and_uses_the_test_domain(): void
    {
        $emails = User::pluck('email');

        $this->assertSame($emails->count(), $emails->unique()->count());
        $this->assertTrue($emails->every(fn (string $email): bool => str_ends_with($email, '.acode-solutions.test')));
    }

    public function test_phones_are_ten_digits_when_present_and_mandatory_for_teachers(): void
    {
        User::whereNotNull('phone')->pluck('phone')->each(
            fn (string $phone) => $this->assertMatchesRegularExpression('/^[0-9]{10}$/', $phone),
        );

        $this->assertSame(0, User::where('role', 'teacher')->whereNull('phone')->count());
    }

    public function test_names_are_real_spanish_names_with_accents_and_two_surnames(): void
    {
        $names = User::where('role', 'student')->get();

        $this->assertTrue($names->contains(
            fn (User $user): bool => preg_match('/[áéíóúñÁÉÍÓÚ]/u', $user->first_name.$user->last_name) === 1,
        ));
        $this->assertTrue($names->every(fn (User $user): bool => str_contains($user->last_name, ' ')));
        $this->assertTrue($names->every(fn (User $user): bool => $user->first_name !== '' && $user->last_name !== ''));
    }

    public function test_every_student_belongs_to_at_least_one_group_and_folios_are_unique(): void
    {
        $this->assertSame(0, User::where('role', 'student')->doesntHave('groupsAsStudent')->count());

        $folios = Group::pluck('access_folio');
        $this->assertSame($folios->count(), $folios->unique()->count());
        $folios->each(fn (string $folio) => $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $folio));
    }

    public function test_every_user_accepted_the_privacy_notice_so_mobile_does_not_block_them(): void
    {
        $this->assertSame(0, User::doesntHave('privacyAcceptances')->count());
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $users = User::count();
        $groups = Group::count();
        $memberships = DB::table('acs_group_student')->count();

        $this->seed(OrganicDataSeeder::class);

        $this->assertSame($users, User::count());
        $this->assertSame($groups, Group::count());
        $this->assertSame($memberships, DB::table('acs_group_student')->count());
    }

    public function test_seeded_accounts_can_start_a_session_through_the_mobile_api(): void
    {
        $sample = User::where('role', 'student')->orderBy('id')->limit(7)->get()
            ->concat(User::where('role', 'teacher')->get());

        foreach ($sample as $user) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Demo1234'])
                ->assertOk()
                ->assertJsonPath('status', 'success');
        }
    }

    public function test_a_wrong_password_is_rejected_for_a_seeded_account(): void
    {
        $user = User::where('role', 'student')->firstOrFail();

        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Incorrecta1']);

        $response->assertJsonPath('status', 'error');
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_the_user_factory_builds_valid_accounts_for_each_role(): void
    {
        $student = UserFactory::new()->student()->create();
        $teacher = UserFactory::new()->teacher()->create();

        $this->assertSame('student', $student->role);
        $this->assertSame('teacher', $teacher->role);
        $this->assertMatchesRegularExpression('/^[0-9]{10}$/', (string) $teacher->phone);
        $this->assertNotSame($student->email, $teacher->email);
        $this->assertFalse(UserFactory::new()->inactive()->make()->is_active);
        $this->assertNull(UserFactory::new()->unverified()->make()->email_verified_at);
    }
}
