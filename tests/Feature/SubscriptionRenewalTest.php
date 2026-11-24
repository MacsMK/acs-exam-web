<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     SubscriptionRenewalTest.php
 * Ubicación:   tests/Feature/SubscriptionRenewalTest.php
 * Requerimiento: RF-SUS-002, 003, 005, 006, 011, 012, 013, 014
 *              (ACT-WEB-023, 024, 025; BD01-2026-034 y 035)
 * Descripción: Suscripción domiciliada con tarjeta, renovación mensual con
 *              reintentos de 3 días, cancelación de la renovación,
 *              actualización a nivel superior sin cobro inmediato y concepto
 *              de cada operación en el historial.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\AiCreditUsage;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\TeacherVerification;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        $this->seed(SubscriptionPackageSeeder::class);
    }

    public function test_card_checkout_with_authorization_stores_masked_card_and_next_charge_date(): void
    {
        $teacher = $this->verifiedTeacher();

        $this->actingAs($teacher)->post(route('teacher.subscription.pay', $this->package('basic')), $this->cardPayload('4242424242424242'));

        $subscription = TeacherSubscription::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertTrue($subscription->renewsAutomatically());
        $this->assertSame('4242', $subscription->card_last4);
        $this->assertSame(PaymentTransaction::CONCEPT_SUBSCRIPTION, PaymentTransaction::where('teacher_id', $teacher->id)->value('concept'));

        $this->actingAs($teacher->fresh())
            ->get(route('teacher.subscription.index'))
            ->assertOk()
            ->assertSee('•••• 4242', false)
            ->assertSee($subscription->expires_at->format('d/m/Y'))
            ->assertSee('Cancelar renovación')
            ->assertSee('Contratación');
    }

    public function test_spei_checkout_does_not_renew_automatically(): void
    {
        $teacher = $this->verifiedTeacher();

        $this->actingAs($teacher)->post(route('teacher.subscription.pay', $this->package('basic')), [
            'payment_method' => 'spei',
            'spei_sender_bank' => 'BBVA México',
            'spei_account_holder' => 'Elena Morales Vega',
        ]);

        $subscription = TeacherSubscription::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertFalse($subscription->renewsAutomatically());
        $this->assertNull($subscription->nextChargeAt());
    }

    public function test_ai_quota_shows_used_and_available_credits_with_the_replenishment_time(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(11, 0));

        $subscription = $this->subscription('4242', now()->addDays(32));
        $subscription->update(['starts_at' => now()->subDay()->setTime(9, 15)]);
        $teacher = $subscription->teacher;
        $limit = (int) $subscription->subscriptionPackage->ai_requests_limit;

        AiCreditUsage::create([
            'teacher_id' => $teacher->id,
            'credits_consumed' => $limit - 1,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $this->actingAs($teacher)->get(route('teacher.subscription.index'))
            ->assertOk()
            ->assertSeeText(($limit - 1)." de {$limit} créditos usados en este ciclo")
            ->assertSeeText('1 crédito disponible')
            ->assertSeeText('Cerca del límite.')
            ->assertSeeText('Próxima reposición si tu plan sigue activo: 23/12/2026 a las 09:15.');

        AiCreditUsage::create([
            'teacher_id' => $teacher->id,
            'credits_consumed' => 1,
            'status' => AiCreditUsage::STATUS_CONSUMED,
        ]);

        $this->get(route('teacher.subscription.index'))
            ->assertOk()
            ->assertSeeText('0 créditos disponibles')
            ->assertSeeText('Has alcanzado el límite.')
            ->assertDontSeeText('Cerca del límite.');
    }

    public function test_due_subscription_is_renewed_with_the_stored_card(): void
    {
        $subscription = $this->subscription('4242', now()->subHour());

        $this->artisan('subscriptions:renew')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('paid', $subscription->payment_status);
        $this->assertTrue($subscription->expires_at->isFuture());
        $this->assertDatabaseHas('acs_payment_transactions', [
            'teacher_subscription_id' => $subscription->id,
            'concept' => PaymentTransaction::CONCEPT_RENEWAL,
            'status' => PaymentTransaction::STATUS_PAID,
        ]);
    }

    public function test_rejected_renewal_keeps_the_plan_for_three_days_and_ends_on_the_third_failure(): void
    {
        $subscription = $this->subscription('0002', now()->subHour());
        $teacher = $subscription->teacher;
        $limits = app(SubscriptionLimitService::class);

        $this->artisan('subscriptions:renew');
        $subscription->refresh();
        $this->assertSame('overdue', $subscription->payment_status);
        $this->assertNotNull($limits->resolveActivePackage($teacher->fresh()));

        // Mismo día: no se vuelve a cobrar
        $this->artisan('subscriptions:renew');
        $this->assertSame(1, PaymentTransaction::where('teacher_subscription_id', $subscription->id)->count());

        $this->travel(1)->days();
        $this->artisan('subscriptions:renew');
        $this->travel(1)->days();
        $this->artisan('subscriptions:renew');

        $subscription->refresh();
        $this->assertSame(3, $subscription->renewal_attempts);
        $this->assertFalse($subscription->auto_renew);
        $this->assertNull($limits->resolveActivePackage($teacher->fresh()));
        $this->assertSame(3, PaymentTransaction::where('teacher_subscription_id', $subscription->id)->where('status', 'failed')->count());
    }

    public function test_cancelled_renewal_stops_charges_but_keeps_the_plan_until_it_expires(): void
    {
        $subscription = $this->subscription('4242', now()->addDays(10));
        $teacher = $subscription->teacher;

        $this->actingAs($teacher)
            ->post(route('teacher.subscription.cancel-renewal'))
            ->assertRedirect(route('teacher.subscription.index'))
            ->assertSessionHas('status');

        $subscription->refresh();
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertNull($subscription->nextChargeAt());
        $this->assertNotNull(app(SubscriptionLimitService::class)->resolveActivePackage($teacher->fresh()));

        $this->travel(11)->days();
        $this->artisan('subscriptions:renew');
        $this->assertSame(0, PaymentTransaction::where('teacher_subscription_id', $subscription->id)->count());
        $this->assertNull(app(SubscriptionLimitService::class)->resolveActivePackage($teacher->fresh()));
    }

    public function test_pending_and_rejected_renewal_show_accurate_actions_until_cancellation(): void
    {
        $subscription = $this->subscription('0002', now()->subHour());
        $teacher = $subscription->teacher;
        $checkout = route('teacher.subscription.checkout', $this->package('basic'));

        $this->actingAs($teacher->fresh())->get(route('teacher.subscription.index'))
            ->assertOk()
            ->assertViewHas('isAwaitingRenewal', true)
            ->assertViewHas('canCheckout', false)
            ->assertViewHas('canCancelRenewal', true);
        $this->actingAs($teacher->fresh())->get($checkout)
            ->assertRedirect(route('teacher.subscription.index'))
            ->assertSessionHasErrors('subscription');

        $this->artisan('subscriptions:renew')->assertSuccessful();
        $this->actingAs($teacher->fresh())->get(route('teacher.subscription.index'))
            ->assertOk()
            ->assertViewHas('isInGracePeriod', true)
            ->assertViewHas('nextChargeAt', static fn ($value): bool => $value === null)
            ->assertViewHas('canUpgrade', true);

        $this->actingAs($teacher->fresh())->post(route('teacher.subscription.cancel-renewal'))
            ->assertRedirect(route('teacher.subscription.index'))
            ->assertSessionHas('status', 'Cancelaste la renovación. Terminó el acceso a tu plan de pago y se detuvieron los reintentos de cobro.');
        $this->actingAs($teacher->fresh())->get(route('teacher.subscription.index'))
            ->assertOk()
            ->assertViewHas('isExpiredSubscription', true)
            ->assertViewHas('canCheckout', true)
            ->assertViewHas('canCancelRenewal', false);
    }

    public function test_suspended_subscription_can_retry_checkout_and_is_restored_only_by_an_approved_payment(): void
    {
        $subscription = $this->subscription('4242', now()->addDay());
        $subscription->update(['payment_status' => 'suspended', 'suspended_at' => now()]);
        $teacher = $subscription->teacher;
        $teacher->teacherVerification()->firstOrFail()->forceFill([
            'plan_onboarding_completed_at' => now(),
        ])->save();
        $package = $this->package('basic');
        $checkout = route('teacher.subscription.checkout', $package);

        $this->actingAs($teacher->fresh())->get(route('teacher.subscription.index'))
            ->assertOk()
            ->assertSeeText('Tienes 0 créditos de IA disponibles.')
            ->assertViewHas('isSuspendedSubscription', true)
            ->assertViewHas('canCheckout', true);
        $this->actingAs($teacher->fresh())->get($checkout)->assertOk();

        $this->actingAs($teacher->fresh())->from($checkout)
            ->post(route('teacher.subscription.pay', $package), $this->cardPayload('4000000000000002'))
            ->assertRedirect($checkout)
            ->assertSessionHasErrors('payment_gateway');
        $this->assertSame('suspended', $subscription->fresh()->payment_status);

        $this->actingAs($teacher->fresh())
            ->post(route('teacher.subscription.pay', $package), $this->cardPayload('4242424242424242'))
            ->assertRedirect(route('teacher.subscription.index'))
            ->assertSessionHas('status');
        $subscription->refresh();
        $this->assertSame('paid', $subscription->payment_status);
        $this->assertNull($subscription->suspended_at);

        $this->actingAs($teacher->fresh())
            ->post(route('teacher.subscription.pay', $package), $this->cardPayload('4242424242424242'))
            ->assertRedirect(route('teacher.subscription.index'))
            ->assertSessionHasErrors('subscription');
        $this->assertDatabaseCount('acs_payment_transactions', 2);
    }

    public function test_upgrade_enables_the_higher_level_immediately_without_charging(): void
    {
        $subscription = $this->subscription('4242', now()->addDays(10));
        $teacher = $subscription->teacher;
        $advanced = $this->package('advanced');

        $this->actingAs($teacher)
            ->post(route('teacher.subscription.upgrade', $advanced))
            ->assertSessionHasNoErrors();

        $this->assertSame($advanced->id, app(SubscriptionLimitService::class)->resolveActivePackage($teacher->fresh())?->id);
        $this->assertDatabaseHas('acs_payment_transactions', [
            'teacher_subscription_id' => $subscription->id,
            'concept' => PaymentTransaction::CONCEPT_UPGRADE,
            'status' => PaymentTransaction::STATUS_NO_CHARGE,
            'amount' => '0.00',
        ]);
        // La siguiente renovación cobra el nuevo precio (RF-SUS-012)
        $this->assertSame($advanced->id, $subscription->fresh()->subscription_package_id);
        $this->actingAs($teacher->fresh())->get(route('teacher.subscription.index'))->assertSee('Actualización de nivel');
    }

    public function test_downgrade_and_new_checkout_are_rejected_while_the_plan_is_active(): void
    {
        $subscription = $this->subscription('4242', now()->addDays(10), 'advanced');
        $teacher = $subscription->teacher;

        $this->actingAs($teacher)
            ->post(route('teacher.subscription.upgrade', $this->package('basic')))
            ->assertSessionHasErrors('subscription');

        $this->actingAs($teacher)
            ->get(route('teacher.subscription.checkout', $this->package('pro')))
            ->assertRedirect(route('teacher.subscription.index'));

        $this->assertSame($this->package('advanced')->id, $subscription->fresh()->subscription_package_id);
    }

    public function test_a_renewal_paid_today_does_not_block_a_later_due_charge(): void
    {
        $subscription = $this->subscription('4242', now()->subHour());
        $this->artisan('subscriptions:renew');

        // Vuelve a vencer el mismo día con una tarjeta sin fondos: debe intentarse el cobro
        $subscription->refresh()->update(['card_last4' => '0002', 'expires_at' => now()->subMinute()]);
        $this->artisan('subscriptions:renew');

        $this->assertSame('overdue', $subscription->fresh()->payment_status);
        $this->assertSame(1, PaymentTransaction::where('teacher_subscription_id', $subscription->id)->where('status', 'failed')->count());
    }

    public function test_upgrade_is_rejected_when_the_renewal_is_cancelled(): void
    {
        $subscription = $this->subscription('4242', now()->addDays(10));
        $subscription->update(['auto_renew' => false, 'cancelled_at' => now()]);

        $this->actingAs($subscription->teacher)
            ->post(route('teacher.subscription.upgrade', $this->package('pro')))
            ->assertSessionHasErrors('subscription');

        $this->assertSame($this->package('basic')->id, $subscription->fresh()->subscription_package_id);
        $this->actingAs($subscription->teacher)->get(route('teacher.subscription.index'))->assertSee('Disponible al terminar tu periodo');
    }

    private function subscription(string $last4, \DateTimeInterface $expiresAt, string $tier = 'basic'): TeacherSubscription
    {
        $teacher = $this->verifiedTeacher();

        return TeacherSubscription::create([
            'teacher_id' => $teacher->id,
            'subscription_package_id' => $this->package($tier)->id,
            'payment_status' => 'paid',
            'starts_at' => now()->subMonth(),
            'expires_at' => $expiresAt,
            'auto_renew' => true,
            'card_brand' => 'Visa',
            'card_last4' => $last4,
            'card_holder_name' => 'Elena Morales Vega',
        ]);
    }

    private function package(string $tier): SubscriptionPackage
    {
        return SubscriptionPackage::where('tier', $tier)->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function cardPayload(string $number): array
    {
        return [
            'payment_method' => 'card',
            'card_holder_name' => 'Elena Morales Vega',
            'card_number' => $number,
            'card_expiry' => '12/30',
            'card_cvv' => '123',
            'authorize_recurring' => '1',
        ];
    }

    private function verifiedTeacher(): User
    {
        $teacher = User::factory()->teacher()->create();

        TeacherVerification::create([
            'user_id' => $teacher->id,
            'status' => TeacherVerification::STATUS_APPROVED,
            'verification_method' => TeacherVerification::METHOD_DOCUMENT_REVIEW,
            'institution_name' => 'Universidad Nacional Autónoma de México',
            'educational_level' => 'superior',
            'is_institutional_email' => true,
            'submitted_at' => now()->subHour(),
            'reviewed_at' => now(),
            'plan_onboarding_completed_at' => now(),
        ]);

        return $teacher->fresh();
    }
}
