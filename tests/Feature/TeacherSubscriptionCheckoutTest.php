<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherSubscriptionCheckoutTest.php
 * Ubicación:      tests/Feature/TeacherSubscriptionCheckoutTest.php
 * Requerimiento:  RF-SUS-008, RF-SUS-013, RF-SUS-015, RF-ADM-005, RF-ADM-006,
 *                 RF-ADM-014, ADR-058 (Pasarela de Pago Simulada y Onboarding)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Certificación integral de la pasarela de pago simulada
 *                 (tarjeta Luhn y SPEI instantáneo), onboarding obligatorio
 *                 post-verificación docente con Prueba Gratuita (Freemium),
 *                 validaciones normativas MPW-V3 Tabla 18, protección estricta
 *                 sin persistencia de PAN/CVV, desbloqueo de cuotas y control
 *                 de acceso / visibilidad en el portal Superadministrador.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\TeacherVerification;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Database\Seeders\SubscriptionPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Pruebas Feature de onboarding post-verificación y checkout simulado docente.
 *
 * @package Tests\Feature
 */
class TeacherSubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPackageSeeder::class);
    }

    public function test_verified_teacher_pending_onboarding_sees_dashboard_overlay_is_redirected_from_modules_and_can_activate_free_trial(): void
    {
        $teacher = $this->createVerifiedTeacherAwaitingOnboarding();

        // 1. Docente verificado con plan_onboarding_completed_at = null ve 200 OK en /dashboard con overlay bloqueante (ADR-060)
        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('inert', false)
            ->assertSee('onboarding_overlay', false)
            ->assertSee('Confirmar prueba gratuita')
            ->assertSee('Básico')
            ->assertSee('Avanzado')
            ->assertSee('Pro');

        // Rutas operativas protegidas redirigen a /dashboard con alerta warning
        foreach ([route('groups.index'), route('questions.index'), route('exams.index'), route('teacher.profile.index')] as $protectedUrl) {
            $this->actingAs($teacher)
                ->get($protectedUrl)
                ->assertRedirect(route('dashboard'))
                ->assertSessionHas('warning');
        }

        // 2. La vista de suscripción mantiene el banner de bienvenida, Prueba Gratuita y catálogo de paquetes
        $onboardingResponse = $this->actingAs($teacher)
            ->get(route('teacher.subscription.index', ['onboarding' => 1]));

        $onboardingResponse->assertOk()
            ->assertSee('Cuenta verificada')
            ->assertSee('¡Tu cuenta docente ya está verificada!')
            ->assertSee('Continuar con prueba gratuita')
            ->assertSee('Básico')
            ->assertSee('Avanzado')
            ->assertSee('Pro');

        // 3. Elección de Prueba Gratuita cierra onboarding, audita y habilita acceso Freemium sin overlay
        $trialResponse = $this->actingAs($teacher)
            ->post(route('teacher.subscription.free-trial'));

        $trialResponse->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $verification = $teacher->teacherVerification()->firstOrFail();
        $this->assertNotNull($verification->plan_onboarding_completed_at);

        $auditLog = AuditLog::where('user_id', $teacher->id)
            ->where('action', 'subscription.freemium_selected')
            ->where('auditable_type', TeacherVerification::class)
            ->where('auditable_id', $verification->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('freemium', $auditLog->new_values['tier'] ?? null);

        $this->actingAs($teacher->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false)
            ->assertDontSee('Confirmar prueba gratuita');
        $this->actingAs($teacher->fresh())->get(route('groups.index'))->assertOk();
        $this->assertNull(app(SubscriptionLimitService::class)->resolveActivePackage($teacher->fresh()));

        // 4. Cuentas User::factory()->teacher() sin teacherVerification siguen entrando sin overlay ni redirect
        $legacyTeacher = User::factory()->teacher()->create();
        $this->assertNull($legacyTeacher->teacherVerification);
        $this->actingAs($legacyTeacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false);
        $this->actingAs($legacyTeacher)->get(route('groups.index'))->assertOk();
    }

    public function test_card_checkout_approved_creates_paid_subscription_transaction_receipt_and_never_stores_pan_or_cvv(): void
    {
        config(['mail.default' => 'array']);
        $this->flushArrayMailTransport();

        $teacher = $this->createVerifiedTeacherAwaitingOnboarding();
        $package = SubscriptionPackage::where('tier', 'advanced')->firstOrFail();

        // 1. Pantalla de checkout retorna 200 OK
        $this->actingAs($teacher)
            ->get(route('teacher.subscription.checkout', $package))
            ->assertOk()
            ->assertSee($package->name)
            ->assertSee('Pago en línea');

        // 2. Envío de cobro con tarjeta Visa válida (Luhn)
        $rawPan = '4242424242424242';
        $rawCvv = '123';

        $payResponse = $this->actingAs($teacher)
            ->post(route('teacher.subscription.pay', $package), [
                'payment_method' => 'card',
                'card_holder_name' => 'Elena Morales Vega',
                'card_number' => '4242 4242 4242 4242',
                'card_expiry' => '12/30',
                'card_cvv' => $rawCvv,
                'authorize_recurring' => '1',
            ]);

        $payResponse->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $transaction = PaymentTransaction::where('teacher_id', $teacher->id)->firstOrFail();
        $subscription = TeacherSubscription::where('teacher_id', $teacher->id)->firstOrFail();

        $this->assertSame(PaymentTransaction::STATUS_PAID, $transaction->status);
        $this->assertSame(PaymentTransaction::METHOD_CARD, $transaction->payment_method);
        $this->assertSame('visa', $transaction->card_brand);
        $this->assertSame('4242', $transaction->card_last4);
        $this->assertSame('Elena Morales Vega', $transaction->card_holder_name);
        $this->assertMatchesRegularExpression('/^ACS-PAY-\d{8}-[A-Z0-9]{6}$/', $transaction->reference_code);
        $this->assertSame($subscription->id, $transaction->teacher_subscription_id);
        $this->assertSame($package->id, $transaction->subscription_package_id);
        $this->assertNotNull($transaction->paid_at);

        $this->assertSame('paid', $subscription->payment_status);
        $this->assertSame($package->id, $subscription->subscription_package_id);
        $this->assertNull($subscription->suspended_at);
        $this->assertNotNull($subscription->starts_at);
        $this->assertNotNull($subscription->expires_at);
        $this->assertTrue(
            $subscription->expires_at->greaterThanOrEqualTo(now()->addDays(27))
            && $subscription->expires_at->lessThanOrEqualTo(now()->addDays(32)),
        );

        // Cierra plan_onboarding_completed_at automáticamente
        $verification = $teacher->teacherVerification()->firstOrFail();
        $this->assertNotNull($verification->plan_onboarding_completed_at);

        // Auditoría inmutable
        $auditLog = AuditLog::where('user_id', $teacher->id)
            ->where('action', 'subscription.payment_succeeded')
            ->where('auditable_type', PaymentTransaction::class)
            ->where('auditable_id', $transaction->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($transaction->reference_code, $auditLog->new_values['reference_code'] ?? null);
        $this->assertSame('4242', $auditLog->new_values['card_last4'] ?? null);

        // Recibo enviado por correo electrónico sin datos sensibles
        $emails = $this->capturedEmails();
        $this->assertCount(1, $emails);
        $this->assertSame([$teacher->email], $this->recipientEmails($emails[0]));
        $this->assertStringContainsString($transaction->reference_code, (string) $emails[0]->getSubject());
        $body = (string) $emails[0]->getTextBody();
        $this->assertStringContainsString($transaction->reference_code, $body);
        $this->assertStringContainsString($package->name, $body);
        $this->assertStringContainsString('visa •••• 4242', $body);
        $this->assertStringNotContainsString($rawPan, $body);

        // Garantía PCI-DSS / LFPDPPP: ni PAN completo ni CVV en BD
        $this->assertSensitiveCardDataNeverPersisted($rawPan, $rawCvv);

        // Desbloquea límites del paquete contratado y acceso operativo 200 OK
        $resolved = app(SubscriptionLimitService::class)->resolveActivePackage($teacher->fresh());
        $this->assertNotNull($resolved);
        $this->assertSame($package->id, $resolved->id);
        $this->assertSame('advanced', $resolved->tier);

        $this->actingAs($teacher->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false);
        $this->actingAs($teacher->fresh())->get(route('groups.index'))->assertOk();

        // Con suscripción activa no se vuelve a contratar ni a cobrar: se actualiza de nivel (RF-SUS-011/012, CAM-010)
        $proPackage = SubscriptionPackage::where('tier', 'pro')->firstOrFail();
        $this->actingAs($teacher->fresh())
            ->post(route('teacher.subscription.pay', $proPackage), [
                'payment_method' => 'card',
                'card_holder_name' => 'Elena Morales Vega',
                'card_number' => '4242 4242 4242 4242',
                'card_expiry' => '12/30',
                'card_cvv' => $rawCvv,
            ])
            ->assertRedirect(route('teacher.subscription.index'))
            ->assertSessionHasErrors('subscription');

        $this->actingAs($teacher->fresh())
            ->post(route('teacher.subscription.upgrade', $proPackage))
            ->assertRedirect(route('teacher.subscription.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
    }

    public function test_declined_card_0002_records_failed_transaction_without_activating_subscription_and_keeps_history_visible(): void
    {
        config(['mail.default' => 'array']);
        $this->flushArrayMailTransport();

        $teacher = $this->createVerifiedTeacherAwaitingOnboarding();
        $package = SubscriptionPackage::where('tier', 'basic')->firstOrFail();

        $declinedPan = '4000000000000002';
        $rawCvv = '456';

        $response = $this->actingAs($teacher)
            ->from(route('teacher.subscription.checkout', $package))
            ->post(route('teacher.subscription.pay', $package), [
                'payment_method' => 'card',
                'card_holder_name' => 'Elena Morales Vega',
                'card_number' => '4000 0000 0000 0002',
                'card_expiry' => '12/30',
                'card_cvv' => $rawCvv,
            ]);

        $response->assertRedirect(route('teacher.subscription.checkout', $package))
            ->assertSessionHasErrors('payment_gateway');

        $this->assertFalse(session()->hasOldInput('card_number'));
        $this->assertFalse(session()->hasOldInput('card_cvv'));
        $this->assertFalse(session()->hasOldInput('card_expiry'));

        // No activa ni crea suscripción pagada
        $this->assertDatabaseCount('acs_teacher_subscriptions', 0);
        $this->assertNull($teacher->teacherVerification()->firstOrFail()->plan_onboarding_completed_at);

        $transaction = PaymentTransaction::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame(PaymentTransaction::STATUS_FAILED, $transaction->status);
        $this->assertSame('insufficient_funds', $transaction->failure_code);
        $this->assertSame('visa', $transaction->card_brand);
        $this->assertSame('0002', $transaction->card_last4);
        $this->assertNull($transaction->teacher_subscription_id);
        $this->assertNull($transaction->paid_at);

        $auditLog = AuditLog::where('user_id', $teacher->id)
            ->where('action', 'subscription.payment_failed')
            ->where('auditable_type', PaymentTransaction::class)
            ->where('auditable_id', $transaction->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('insufficient_funds', $auditLog->new_values['failure_code'] ?? null);

        $this->assertCount(0, $this->capturedEmails());
        $this->assertSensitiveCardDataNeverPersisted($declinedPan, $rawCvv);

        // Historial visible en /docente/suscripcion (RF-SUS-013/015)
        $this->actingAs($teacher)
            ->get(route('teacher.subscription.index'))
            ->assertOk()
            ->assertSee($transaction->reference_code)
            ->assertSee('Rechazado')
            ->assertSee('visa •••• 0002');
    }

    public function test_instant_spei_checkout_generates_tracking_key_activates_paid_subscription_and_persists_transaction(): void
    {
        config(['mail.default' => 'array']);
        $this->flushArrayMailTransport();

        $teacher = $this->createVerifiedTeacherAwaitingOnboarding();
        $package = SubscriptionPackage::where('tier', 'pro')->firstOrFail();

        $response = $this->actingAs($teacher)
            ->post(route('teacher.subscription.pay', $package), [
                'payment_method' => 'spei',
                'spei_sender_bank' => 'BBVA México',
                'spei_account_holder' => 'Elena Morales Vega',
            ]);

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $transaction = PaymentTransaction::where('teacher_id', $teacher->id)->firstOrFail();
        $subscription = TeacherSubscription::where('teacher_id', $teacher->id)->firstOrFail();

        $this->assertSame(PaymentTransaction::METHOD_SPEI, $transaction->payment_method);
        $this->assertSame(PaymentTransaction::STATUS_PAID, $transaction->status);
        $this->assertSame('simulated_spei', $transaction->gateway_provider);
        $this->assertNotNull($transaction->spei_tracking_key);
        $this->assertMatchesRegularExpression('/^SPEI-\d{8}-[A-Z0-9]{8}$/', (string) $transaction->spei_tracking_key);
        $this->assertNull($transaction->card_brand);
        $this->assertNull($transaction->card_last4);
        $this->assertSame($subscription->id, $transaction->teacher_subscription_id);

        $this->assertSame('paid', $subscription->payment_status);
        $this->assertSame($package->id, $subscription->subscription_package_id);
        $this->assertNotNull($teacher->teacherVerification()->firstOrFail()->plan_onboarding_completed_at);

        $resolved = app(SubscriptionLimitService::class)->resolveActivePackage($teacher->fresh());
        $this->assertNotNull($resolved);
        $this->assertSame($package->id, $resolved->id);

        $emails = $this->capturedEmails();
        $this->assertCount(1, $emails);
        $this->assertStringContainsString((string) $transaction->spei_tracking_key, (string) $emails[0]->getTextBody());
    }

    public function test_checkout_request_validates_luhn_expired_date_cvv_by_brand_and_inactive_package_404(): void
    {
        $teacher = $this->createVerifiedTeacherAwaitingOnboarding();
        $package = SubscriptionPackage::where('tier', 'basic')->firstOrFail();

        // 1. Falla Luhn (4242 4242 4242 4243)
        $this->actingAs($teacher)
            ->post(route('teacher.subscription.pay', $package), [
                'payment_method' => 'card',
                'card_holder_name' => 'Elena Morales Vega',
                'card_number' => '4242 4242 4242 4243',
                'card_expiry' => '12/30',
                'card_cvv' => '123',
            ])
            ->assertSessionHasErrors([
                'card_number' => 'El número de tarjeta no es válido según la verificación bancaria',
            ]);

        // 2. Fecha de expiración pasada (01/20) + verificación de no flasheo PCI en sesión (dontFlash)
        $expiredResponse = $this->actingAs($teacher)
            ->post(route('teacher.subscription.pay', $package), [
                'payment_method' => 'card',
                'card_holder_name' => 'Elena Morales Vega',
                'card_number' => '4242 4242 4242 4242',
                'card_expiry' => '01/20',
                'card_cvv' => '123',
            ]);

        $expiredResponse->assertSessionHasErrors([
            'card_expiry' => 'La tarjeta ingresada se encuentra vencida',
        ]);
        $this->assertFalse(session()->hasOldInput('card_number'));
        $this->assertFalse(session()->hasOldInput('card_cvv'));
        $this->assertFalse(session()->hasOldInput('card_expiry'));

        // 3. CVV de 3 dígitos para American Express (378282246310005)
        $this->actingAs($teacher)
            ->post(route('teacher.subscription.pay', $package), [
                'payment_method' => 'card',
                'card_holder_name' => 'Elena Morales Vega',
                'card_number' => '3782 822463 10005',
                'card_expiry' => '12/30',
                'card_cvv' => '123',
            ])
            ->assertSessionHasErrors([
                'card_cvv' => 'El código de seguridad (CVV) debe contener 4 dígitos para tarjetas American Express',
            ]);

        // 4. CVV con letras
        $this->actingAs($teacher)
            ->post(route('teacher.subscription.pay', $package), [
                'payment_method' => 'card',
                'card_holder_name' => 'Elena Morales Vega',
                'card_number' => '4242 4242 4242 4242',
                'card_expiry' => '12/30',
                'card_cvv' => 'abc',
            ])
            ->assertSessionHasErrors([
                'card_cvv' => 'El código de seguridad (CVV) debe contener 3 o 4 dígitos',
            ]);

        // 4b. Banco emisor SPEI inválido (ADR-060)
        $this->actingAs($teacher)
            ->post(route('teacher.subscription.pay', $package), [
                'payment_method' => 'spei',
                'spei_sender_bank' => 'Banco Inexistente S.A.',
                'spei_account_holder' => 'Elena Morales Vega',
            ])
            ->assertSessionHasErrors([
                'spei_sender_bank' => 'La opción seleccionada en banco emisor no es válida',
            ]);

        $this->assertDatabaseCount('acs_payment_transactions', 0);

        // 5. Paquete inactivo retorna 404 en GET y POST
        $inactivePackage = SubscriptionPackage::where('tier', 'pro')->firstOrFail();
        $inactivePackage->update(['is_active' => false]);

        $this->actingAs($teacher)
            ->get(route('teacher.subscription.checkout', $inactivePackage))
            ->assertNotFound();

        $this->actingAs($teacher)
            ->post(route('teacher.subscription.pay', $inactivePackage), [
                'payment_method' => 'card',
                'card_holder_name' => 'Elena Morales Vega',
                'card_number' => '4242 4242 4242 4242',
                'card_expiry' => '12/30',
                'card_cvv' => '123',
            ])
            ->assertNotFound();
    }

    public function test_checkout_access_control_and_super_admin_teachers_index_shows_masked_payment_folio(): void
    {
        config(['mail.default' => 'array']);

        $package = SubscriptionPackage::where('tier', 'basic')->firstOrFail();
        $student = User::factory()->student()->create();
        $pendingTeacher = User::factory()->pendingVerification()->create();
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin.checkout@acode-solutions.test',
        ]);

        $cardPayload = [
            'payment_method' => 'card',
            'card_holder_name' => 'Prueba Seguridad',
            'card_number' => '4242424242424242',
            'card_expiry' => '12/30',
            'card_cvv' => '123',
        ];

        // 1. Invitados reciben 302 a /login
        $this->get(route('teacher.subscription.index'))->assertRedirect('/login');
        $this->get(route('teacher.subscription.checkout', $package))->assertRedirect('/login');
        $this->post(route('teacher.subscription.pay', $package), $cardPayload)->assertRedirect('/login');
        $this->post(route('teacher.subscription.free-trial'))->assertRedirect('/login');

        // 2. Alumnos reciben 403 Forbidden
        $this->actingAs($student)->get(route('teacher.subscription.index'))->assertForbidden();
        $this->actingAs($student)->get(route('teacher.subscription.checkout', $package))->assertForbidden();
        $this->actingAs($student)->post(route('teacher.subscription.pay', $package), $cardPayload)->assertForbidden();
        $this->actingAs($student)->post(route('teacher.subscription.free-trial'))->assertForbidden();

        // 3. Docente pending es redirigido por teacher.verified antes de llegar al checkout
        $this->actingAs($pendingTeacher)
            ->get(route('teacher.subscription.checkout', $package))
            ->assertRedirect(route('teacher.verification.status'));

        $this->actingAs($pendingTeacher)
            ->post(route('teacher.subscription.pay', $package), $cardPayload)
            ->assertRedirect(route('teacher.verification.status'));

        $this->actingAs($pendingTeacher)
            ->post(route('teacher.subscription.free-trial'))
            ->assertRedirect(route('teacher.verification.status'));

        $this->assertDatabaseCount('acs_payment_transactions', 0);

        // 4. Superadmin ve el folio y tarjeta enmascarada en /admin/docentes sin exponer PAN ni CVV (RF-ADM-014)
        $verifiedTeacher = $this->createVerifiedTeacherAwaitingOnboarding();
        $this->actingAs($verifiedTeacher)
            ->post(route('teacher.subscription.pay', $package), [
                'payment_method' => 'card',
                'card_holder_name' => 'Docente Folio Visible',
                'card_number' => '4242 4242 4242 4242',
                'card_expiry' => '12/30',
                'card_cvv' => '879',
            ])
            ->assertRedirect(route('dashboard'));

        $transaction = PaymentTransaction::where('teacher_id', $verifiedTeacher->id)->firstOrFail();

        $adminResponse = $this->actingAs($superAdmin)->get(route('admin.teachers.index'));

        $adminResponse->assertOk()
            ->assertSee($transaction->reference_code)
            ->assertSee('4242')
            ->assertSee('visa •••• 4242')
            ->assertDontSee('4242424242424242')
            ->assertDontSee('4242 4242 4242 4242')
            ->assertDontSee('879');
    }

    public function test_dashboard_two_step_onboarding_overlay_handles_declined_card_approved_card_spei_and_free_trial(): void
    {
        config(['mail.default' => 'array']);
        $this->flushArrayMailTransport();

        $basic = SubscriptionPackage::where('tier', 'basic')->firstOrFail();
        $advanced = SubscriptionPackage::where('tier', 'advanced')->firstOrFail();

        // 1. Flujo overlay: rechazo tarjeta 0002 -> regresa a dashboard con onboarding_selected_package_id, error payment_gateway y sin flashear PAN/CVV/vigencia
        $teacherCard = $this->createVerifiedTeacherAwaitingOnboarding();

        $this->actingAs($teacherCard)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('inert', false)
            ->assertSee('onboarding_overlay', false);

        $declinedOverlay = $this->actingAs($teacherCard)
            ->post(route('teacher.subscription.pay', $basic), [
                'onboarding_overlay' => '1',
                'payment_method' => 'card',
                'card_holder_name' => 'Mariana Soto Pérez',
                'card_number' => '4000 0000 0000 0002',
                'card_expiry' => '12/30',
                'card_cvv' => '789',
            ]);

        $declinedOverlay->assertRedirect(route('dashboard'))
            ->assertSessionHas('onboarding_selected_package_id', $basic->id)
            ->assertSessionHasErrors('payment_gateway');

        $this->assertFalse(session()->hasOldInput('card_number'));
        $this->assertFalse(session()->hasOldInput('card_cvv'));
        $this->assertFalse(session()->hasOldInput('card_expiry'));
        $this->assertNull($teacherCard->teacherVerification()->firstOrFail()->plan_onboarding_completed_at);
        $this->assertSensitiveCardDataNeverPersisted('4000000000000002', '789');

        // Error de validación desde overlay (Luhn inválido) también conserva onboarding_selected_package_id sin flashear tarjeta
        $invalidLuhnOverlay = $this->actingAs($teacherCard)
            ->post(route('teacher.subscription.pay', $basic), [
                'onboarding_overlay' => '1',
                'payment_method' => 'card',
                'card_holder_name' => 'Mariana Soto Pérez',
                'card_number' => '4242 4242 4242 4243',
                'card_expiry' => '12/30',
                'card_cvv' => '321',
            ]);

        $invalidLuhnOverlay->assertRedirect(route('dashboard'))
            ->assertSessionHas('onboarding_selected_package_id', $basic->id)
            ->assertSessionHasErrors('card_number');
        $this->assertFalse(session()->hasOldInput('card_number'));
        $this->assertFalse(session()->hasOldInput('card_cvv'));
        $this->assertFalse(session()->hasOldInput('card_expiry'));

        // Reintento exitoso con tarjeta aprobada desde el overlay -> cierra onboarding y desbloquea /dashboard y /groups
        $approvedOverlay = $this->actingAs($teacherCard)
            ->post(route('teacher.subscription.pay', $basic), [
                'onboarding_overlay' => '1',
                'payment_method' => 'card',
                'card_holder_name' => 'Mariana Soto Pérez',
                'card_number' => '4242 4242 4242 4242',
                'card_expiry' => '12/30',
                'card_cvv' => '123',
            ]);

        $approvedOverlay->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertNotNull($teacherCard->teacherVerification()->firstOrFail()->plan_onboarding_completed_at);
        $this->actingAs($teacherCard->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false)
            ->assertDontSee('Confirmar prueba gratuita');
        $this->actingAs($teacherCard->fresh())->get(route('groups.index'))->assertOk();

        // 2. Flujo overlay: pago SPEI instantáneo aprobado -> cierra onboarding y desbloquea
        $teacherSpei = $this->createVerifiedTeacherAwaitingOnboarding();
        $speiOverlay = $this->actingAs($teacherSpei)
            ->post(route('teacher.subscription.pay', $advanced), [
                'onboarding_overlay' => '1',
                'payment_method' => 'spei',
                'spei_sender_bank' => 'BBVA México',
                'spei_account_holder' => 'Jorge Méndez Ruiz',
            ]);

        $speiOverlay->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertNotNull($teacherSpei->teacherVerification()->firstOrFail()->plan_onboarding_completed_at);
        $this->actingAs($teacherSpei->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false);
        $this->actingAs($teacherSpei->fresh())->get(route('groups.index'))->assertOk();

        // 3. Flujo overlay: Prueba gratuita -> cierra onboarding y desbloquea
        $teacherTrial = $this->createVerifiedTeacherAwaitingOnboarding();
        $this->actingAs($teacherTrial)
            ->post(route('teacher.subscription.free-trial'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertNotNull($teacherTrial->teacherVerification()->firstOrFail()->plan_onboarding_completed_at);
        $this->actingAs($teacherTrial->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false);
        $this->actingAs($teacherTrial->fresh())->get(route('groups.index'))->assertOk();
    }

    public function test_super_admin_manual_paid_package_assignment_completes_plan_onboarding(): void
    {
        $teacher = $this->createVerifiedTeacherAwaitingOnboarding();
        $proPackage = SubscriptionPackage::where('tier', 'pro')->firstOrFail();
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin.assign@acode-solutions.test',
        ]);

        $this->assertTrue($teacher->needsPlanOnboarding());
        $this->assertNull($teacher->teacherVerification()->firstOrFail()->plan_onboarding_completed_at);

        // Asignación en estado 'pending' NO cierra el onboarding
        $this->actingAs($superAdmin)
            ->post(route('admin.teachers.assign', $teacher), [
                'subscription_package_id' => $proPackage->id,
                'payment_status' => 'pending',
                'notes' => 'En espera de comprobante',
            ])
            ->assertRedirect();

        $this->assertNull($teacher->teacherVerification()->firstOrFail()->plan_onboarding_completed_at);
        $this->assertTrue($teacher->fresh()->needsPlanOnboarding());

        // Asignación en estado 'paid' sí cierra plan_onboarding_completed_at y desbloquea al docente (ADR-060)
        $this->actingAs($superAdmin)
            ->post(route('admin.teachers.assign', $teacher), [
                'subscription_package_id' => $proPackage->id,
                'payment_status' => 'paid',
                'notes' => 'Convenio institucional liquidado',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull($teacher->teacherVerification()->firstOrFail()->plan_onboarding_completed_at);
        $this->assertFalse($teacher->fresh()->needsPlanOnboarding());

        $this->app['auth']->forgetGuards();
        $this->actingAs($teacher->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false);
        $this->actingAs($teacher->fresh())->get(route('groups.index'))->assertOk();
    }

    /**
     * Crea un docente verificado recién acreditado con onboarding pendiente.
     */
    private function createVerifiedTeacherAwaitingOnboarding(): User
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
        ]);

        return $teacher->fresh();
    }

    /**
     * Verifica que ninguna fila de acs_payment_transactions ni acs_audit_logs
     * conserve el PAN completo ni el código CVV.
     */
    private function assertSensitiveCardDataNeverPersisted(string $panDigits, string $cvv): void
    {
        $transactionsDump = json_encode(
            DB::table('acs_payment_transactions')->get()->all(),
            JSON_THROW_ON_ERROR,
        );
        $auditLogsDump = json_encode(
            DB::table('acs_audit_logs')->get()->all(),
            JSON_THROW_ON_ERROR,
        );

        $this->assertStringNotContainsString($panDigits, $transactionsDump);
        $this->assertStringNotContainsString($panDigits, $auditLogsDump);
        $this->assertStringNotContainsString('"card_number"', $transactionsDump);
        $this->assertStringNotContainsString('"card_number"', $auditLogsDump);
        $this->assertStringNotContainsString('"card_cvv"', $transactionsDump);
        $this->assertStringNotContainsString('"card_cvv"', $auditLogsDump);
        $this->assertStringNotContainsString("\"{$cvv}\"", $transactionsDump);
        $this->assertStringNotContainsString("\"{$cvv}\"", $auditLogsDump);
    }

    private function flushArrayMailTransport(): void
    {
        Mail::mailer('array')->getSymfonyTransport()->flush();
    }

    /**
     * @return list<Email>
     */
    private function capturedEmails(): array
    {
        return Mail::mailer('array')
            ->getSymfonyTransport()
            ->messages()
            ->map(static function (SentMessage $sent): Email {
                $original = $sent->getOriginalMessage();
                if (! $original instanceof Email) {
                    throw new \RuntimeException('Se esperaba un mensaje Symfony Email.');
                }

                return $original;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function recipientEmails(Email $email): array
    {
        return array_map(
            static fn (Address $address): string => $address->getAddress(),
            $email->getTo(),
        );
    }
}
