<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     SubscriptionLimitServiceTest.php
 * Ubicación:   tests/Feature/SubscriptionLimitServiceTest.php
 * Descripción: Pruebas automatizadas del servicio transaccional de límites
 *              y cuotas SaaS (RF-SUS-001..015, RF-SUS-010, RW-01..04).
 * ============================================================================
 */

namespace Tests\Feature;

use App\Exceptions\SubscriptionLimitExceededException;
use App\Models\Group;
use App\Models\MultipleChoiceItem;
use App\Models\Question;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use App\Services\GroupEnrollmentService;
use App\Services\QuestionBankService;
use App\Services\SubscriptionLimitService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private SubscriptionLimitService $limitService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPackageSeeder::class);

        $this->teacher = User::factory()->create([
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->limitService = app(SubscriptionLimitService::class);
    }

    public function test_freemium_teacher_can_create_first_group(): void
    {
        $this->expectNotToPerformAssertions();

        $this->limitService->ensureCanCreateGroup($this->teacher);
    }

    public function test_freemium_teacher_cannot_create_second_group_even_if_first_is_closed(): void
    {
        // Primer grupo histórico
        Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Grupo 101',
            'access_folio' => 'GRP-101',
            'is_closed' => true, // Cerrado
        ]);

        $this->expectException(SubscriptionLimitExceededException::class);
        $this->expectExceptionMessage('El beneficio de prueba gratuita permite crear un máximo histórico de 1 grupo');

        $this->limitService->ensureCanCreateGroup($this->teacher);
    }

    public function test_freemium_teacher_cannot_exceed_50_questions(): void
    {
        $variant = MultipleChoiceItem::create([
            'options_payload' => ['A', 'B'],
            'correct_option_index' => 0,
        ]);

        // 50 reactivos previos
        for ($i = 1; $i <= 50; $i++) {
            Question::create([
                'teacher_id' => $this->teacher->id,
                'title' => "Reactivo {$i}",
                'stem' => "Enunciado {$i}",
                'question_type' => 'multiple_choice',
                'default_points' => 1.0,
                'default_time_seconds' => 60,
                'questionable_type' => MultipleChoiceItem::class,
                'questionable_id' => $variant->id,
            ]);
        }

        $this->expectException(SubscriptionLimitExceededException::class);
        $this->expectExceptionMessage('No es posible registrar 1 reactivo(s). Tu cuenta gratuita tiene registrados 50/50 reactivos permitidos.');

        $this->limitService->ensureCanCreateQuestions($this->teacher, 1);
    }

    public function test_basic_plan_teacher_can_create_up_to_3_active_groups(): void
    {
        $basicPackage = SubscriptionPackage::where('tier', 'basic')->firstOrFail();

        TeacherSubscription::create([
            'teacher_id' => $this->teacher->id,
            'subscription_package_id' => $basicPackage->id,
            'payment_status' => 'paid',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // Crea 3 grupos activos
        for ($i = 1; $i <= 3; $i++) {
            Group::create([
                'teacher_id' => $this->teacher->id,
                'name' => "Grupo {$i}",
                'access_folio' => "FOLIO-{$i}",
                'is_closed' => false,
            ]);
        }

        // El 4to debe ser rechazado
        $this->expectException(SubscriptionLimitExceededException::class);
        $this->expectExceptionMessage('Has alcanzado el límite de 3 grupos activos de tu paquete Básico');

        $this->limitService->ensureCanCreateGroup($this->teacher);
    }

    public function test_basic_plan_allows_new_group_when_previous_is_closed(): void
    {
        $basicPackage = SubscriptionPackage::where('tier', 'basic')->firstOrFail();

        TeacherSubscription::create([
            'teacher_id' => $this->teacher->id,
            'subscription_package_id' => $basicPackage->id,
            'payment_status' => 'paid',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // 2 activos y 1 cerrado
        Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Grupo 1',
            'access_folio' => 'FOL-01',
            'is_closed' => false,
        ]);
        Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Grupo 2',
            'access_folio' => 'FOL-02',
            'is_closed' => false,
        ]);
        Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Grupo 3 Cerrado',
            'access_folio' => 'FOL-03',
            'is_closed' => true,
        ]);

        // Total activos: 2 < 3 -> Permitido
        $this->expectNotToPerformAssertions();
        $this->limitService->ensureCanCreateGroup($this->teacher);
    }

    public function test_student_cannot_join_if_group_reaches_capacity(): void
    {
        $group = Group::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Grupo Masivo',
            'access_folio' => 'GRP-MAX',
            'is_closed' => false,
        ]);

        // Agregamos 40 alumnos (límite freemium)
        $students = User::factory()->count(40)->create(['role' => 'student']);
        foreach ($students as $student) {
            $group->students()->attach($student->id, ['joined_at' => now()]);
        }

        $newStudent = User::factory()->create(['role' => 'student']);
        $enrollmentService = app(GroupEnrollmentService::class);

        $this->expectException(SubscriptionLimitExceededException::class);
        $this->expectExceptionMessage('Este grupo escolar ha alcanzado su capacidad máxima permitida de 40 alumnos.');

        $enrollmentService->joinByFolio($newStudent, 'GRP-MAX');
    }

    public function test_suspended_subscription_blocks_operations(): void
    {
        $basicPackage = SubscriptionPackage::where('tier', 'basic')->firstOrFail();

        TeacherSubscription::create([
            'teacher_id' => $this->teacher->id,
            'subscription_package_id' => $basicPackage->id,
            'payment_status' => 'suspended',
            'starts_at' => now()->subMonth(),
            'suspended_at' => now(),
        ]);

        $this->expectException(SubscriptionLimitExceededException::class);
        $this->expectExceptionMessage('Tu suscripción docente se encuentra temporalmente suspendida por falta de pago.');

        $this->limitService->ensureCanCreateGroup($this->teacher);
    }
}
