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
use App\Models\AiCreditUsage;
use App\Models\Group;
use App\Models\MultipleChoiceItem;
use App\Models\Question;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use App\Services\GroupEnrollmentService;
use App\Services\QuestionBankService;
use App\Services\SubscriptionLimitService;
use App\Services\TeacherSubscriptionService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_paid_ai_credits_reset_each_month_and_keep_the_original_activation_day(): void
    {
        $basicPackage = SubscriptionPackage::where('tier', 'basic')->firstOrFail();
        TeacherSubscription::create([
            'teacher_id' => $this->teacher->id,
            'subscription_package_id' => $basicPackage->id,
            'payment_status' => 'paid',
            'starts_at' => '2026-01-31 10:00:00',
            'expires_at' => '2027-01-31 10:00:00',
        ]);

        $this->travelTo(Carbon::parse('2026-02-28 09:59:59'));
        AiCreditUsage::create([
            'teacher_id' => $this->teacher->id,
            'credits_consumed' => 200,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $summary = $this->limitService->getAiCreditQuotaSummary($this->teacher);
        $this->assertSame(0, $summary['remaining']);
        $this->assertSame('2026-02-28 10:00:00', $summary['next_reset_at']?->toDateTimeString());
        $this->assertThrows(
            fn () => $this->limitService->ensureCanConsumeAiCredits($this->teacher),
            SubscriptionLimitExceededException::class,
            'El próximo ciclo está previsto para el 28/02/2026.',
        );

        $this->travelTo(Carbon::parse('2026-02-28 10:00:00'));
        $this->limitService->ensureCanConsumeAiCredits($this->teacher);
        AiCreditUsage::create([
            'teacher_id' => $this->teacher->id,
            'credits_consumed' => 1,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $this->travelTo(Carbon::parse('2026-03-28 10:00:00'));
        $summary = $this->limitService->getAiCreditQuotaSummary($this->teacher);
        $this->assertSame(199, $summary['remaining']);
        $this->assertSame('2026-02-28 10:00:00', $summary['cycle_starts_at']?->toDateTimeString());
        $this->assertSame('2026-03-31 10:00:00', $summary['next_reset_at']?->toDateTimeString());

        $this->travelTo(Carbon::parse('2026-03-31 10:00:00'));
        $summary = $this->limitService->getAiCreditQuotaSummary($this->teacher);
        $this->assertSame(200, $summary['remaining']);
        $this->assertSame('2026-04-30 10:00:00', $summary['next_reset_at']?->toDateTimeString());
    }

    public function test_upgrading_keeps_ai_consumption_and_cancelling_removes_the_unfunded_next_cycle(): void
    {
        $this->travelTo(Carbon::parse('2026-03-10 10:00:00'));
        $basicPackage = SubscriptionPackage::where('tier', 'basic')->firstOrFail();
        $advancedPackage = SubscriptionPackage::where('tier', 'advanced')->firstOrFail();
        TeacherSubscription::create([
            'teacher_id' => $this->teacher->id,
            'subscription_package_id' => $basicPackage->id,
            'payment_status' => 'paid',
            'starts_at' => '2026-02-25 10:00:00',
            'expires_at' => '2026-03-25 10:00:00',
            'auto_renew' => true,
            'card_brand' => 'Visa',
            'card_last4' => '4242',
        ]);
        AiCreditUsage::create([
            'teacher_id' => $this->teacher->id,
            'credits_consumed' => 35,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $subscriptions = app(TeacherSubscriptionService::class);
        $subscriptions->upgradePackage($this->teacher, $advancedPackage, null, null);
        $summary = $this->limitService->getAiCreditQuotaSummary($this->teacher->fresh());
        $this->assertSame(565, $summary['remaining']);
        $this->assertSame('2026-02-25 10:00:00', $summary['cycle_starts_at']?->toDateTimeString());
        $this->assertSame('2026-03-25 10:00:00', $summary['next_reset_at']?->toDateTimeString());

        $subscriptions->cancelRenewal($this->teacher->fresh(), null, null);
        $summary = $this->limitService->getAiCreditQuotaSummary($this->teacher->fresh());
        $this->assertSame(565, $summary['remaining']);
        $this->assertSame('2026-03-25 10:00:00', $summary['cycle_ends_at']?->toDateTimeString());
        $this->assertNull($summary['next_reset_at']);
        $this->assertThrows(
            fn () => $this->limitService->ensureCanConsumeAiCredits($this->teacher->fresh(), 566),
            SubscriptionLimitExceededException::class,
            'Consulta las opciones de tu plan en Suscripción.',
        );
    }

    public function test_trial_overview_and_validation_keep_used_allowances_after_records_are_removed(): void
    {
        $this->teacher->forceFill([
            'trial_group_used_at' => now()->subMonth(),
            'legacy_exam_record' => true,
        ])->save();

        $overview = app(TeacherSubscriptionService::class)->getSubscriptionOverview($this->teacher);
        $this->assertSame(1, $overview['quotaMetrics']['groups']['used']);
        $this->assertSame(1, $overview['quotaMetrics']['exams']['used']);
        $this->assertThrows(fn () => $this->limitService->ensureCanCreateGroup($this->teacher), SubscriptionLimitExceededException::class);
        $this->assertThrows(fn () => $this->limitService->ensureCanCreateExam($this->teacher), SubscriptionLimitExceededException::class);
    }

    public function test_unlimited_ai_reports_consumption_since_activation_without_a_monthly_reset(): void
    {
        $this->travelTo(Carbon::parse('2026-02-26 10:00:00'));
        $proPackage = SubscriptionPackage::where('tier', 'pro')->firstOrFail();
        TeacherSubscription::create([
            'teacher_id' => $this->teacher->id,
            'subscription_package_id' => $proPackage->id,
            'payment_status' => 'paid',
            'starts_at' => '2026-02-25 10:00:00',
            'expires_at' => '2027-02-25 10:00:00',
        ]);
        AiCreditUsage::create([
            'teacher_id' => $this->teacher->id,
            'credits_consumed' => 35,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $this->travelTo(Carbon::parse('2026-04-26 10:00:00'));
        $summary = $this->limitService->getAiCreditQuotaSummary($this->teacher);
        $this->assertSame(35, $summary['used']);
        $this->assertTrue($summary['is_unlimited']);
        $this->assertNull($summary['cycle_starts_at']);
        $this->assertNull($summary['next_reset_at']);
    }
}
