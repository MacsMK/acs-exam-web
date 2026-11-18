<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherSubscriptionService.php
 * Ubicación:      app/Services/TeacherSubscriptionService.php
 * Requerimiento:  RF-SUS-001..015, RF-SUS-008, RF-SUS-010, RF-SUS-013, RW-01..05, ADR-058
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Servicio transaccional de autoservicio SaaS para docentes:
 *                 consulta de cuotas, preparación de checkout, procesamiento de
 *                 pagos instantáneos con pasarela desacoplada, activación de
 *                 prueba gratuita (onboarding post-verificación) y bitácora.
 * ============================================================================
 */

namespace App\Services;

use App\Contracts\PaymentGatewayContract;
use App\Exceptions\SubscriptionLimitExceededException;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Group;
use App\Models\PaymentTransaction;
use App\Models\Question;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\TeacherVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Servicio transaccional de suscripciones, checkout simulado y onboarding docente.
 *
 * @package App\Services
 */
class TeacherSubscriptionService
{
    /**
     * CLABE interbancaria de referencia para transferencias SPEI simuladas.
     */
    public const SIMULATED_CLABE = '646180110400000018';

    /**
     * Razón social beneficiaria para transferencias SPEI simuladas.
     */
    public const SIMULATED_BENEFICIARY = 'A-Code Solutions S.A. de C.V.';

    /**
     * Cota máxima de reintentos al generar folios únicos de pago (MPW-V2 §5.2.1).
     */
    private const MAX_REFERENCE_ATTEMPTS = 20;

    /**
     * Alfabeto alfanumérico no ambiguo para folios de transacciones de pago.
     */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Inicializa el servicio inyectando el gestor de límites SaaS y la pasarela de pago.
     *
     * @param SubscriptionLimitService $subscriptionLimitService Servicio de cálculo y límites de cuotas.
     * @param PaymentGatewayContract $paymentGateway Driver de pasarela de pago configurado.
     */
    public function __construct(
        private readonly SubscriptionLimitService $subscriptionLimitService,
        private readonly PaymentGatewayContract $paymentGateway,
    ) {
    }

    /**
     * Compila el estado de suscripción, consumo de cuotas, catálogo de paquetes,
     * historial reciente de pagos y bandera de onboarding post-verificación.
     *
     * @param User $teacher Cuenta docente autenticada.
     * @param bool $isOnboardingRequest Indica si el acceso proviene de redirección de onboarding.
     * @return array{
     *     teacher: User,
     *     activePackage: ?SubscriptionPackage,
     *     currentSubscription: ?TeacherSubscription,
     *     isSuspendedSubscription: bool,
     *     isOnboarding: bool,
     *     activeGroupsCount: int,
     *     cumulativeGroupsCount: int,
     *     questionsCount: int,
     *     examsCount: int,
     *     cumulativeExamsCount: int,
     *     packages: Collection<int, SubscriptionPackage>,
     *     transactions: Collection<int, PaymentTransaction>,
     *     whatsappNumber: string
     * }
     */
    public function getSubscriptionOverview(User $teacher, bool $isOnboardingRequest = false): array
    {
        $teacher->loadMissing(['teacherVerification', 'subscription.subscriptionPackage']);

        $isSuspendedSubscription = false;

        try {
            $activePackage = $this->subscriptionLimitService->resolveActivePackage($teacher);
        } catch (SubscriptionLimitExceededException) {
            $activePackage = null;
            $isSuspendedSubscription = true;
        }

        $currentSubscription = $teacher->subscription;

        $activeGroupsCount = Group::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->where('is_closed', false)
            ->count();

        $cumulativeGroupsCount = Group::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->count();

        $questionsQuery = Question::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id);

        if ($activePackage !== null) {
            $questionsQuery->whereNull('deleted_at');
        }

        $questionsCount = $questionsQuery->count();

        $examsCount = Exam::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->where('is_published', true)
            ->count();

        $cumulativeExamsCount = Exam::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->count();

        $packages = SubscriptionPackage::where('is_active', true)
            ->orderBy('monthly_price')
            ->get();

        $transactions = PaymentTransaction::with('package')
            ->where('teacher_id', $teacher->id)
            ->latest('id')
            ->limit(10)
            ->get();

        $isOnboarding = $teacher->needsPlanOnboarding();

        return [
            'teacher' => $teacher,
            'activePackage' => $activePackage,
            'currentSubscription' => $currentSubscription,
            'isSuspendedSubscription' => $isSuspendedSubscription,
            'isOnboarding' => $isOnboarding,
            'activeGroupsCount' => $activeGroupsCount,
            'cumulativeGroupsCount' => $cumulativeGroupsCount,
            'questionsCount' => $questionsCount,
            'examsCount' => $examsCount,
            'cumulativeExamsCount' => $cumulativeExamsCount,
            'packages' => $packages,
            'transactions' => $transactions,
            'whatsappNumber' => (string) config('services.acode.whatsapp_sales', '5215500000000'),
        ];
    }

    /**
     * Prepara los datos requeridos para la pantalla de checkout del paquete seleccionado.
     *
     * @param User $teacher Cuenta docente autenticada.
     * @param SubscriptionPackage $package Paquete de suscripción elegido.
     * @return array{
     *     teacher: User,
     *     package: SubscriptionPackage,
     *     activePackage: ?SubscriptionPackage,
     *     speiClabe: string,
     *     speiBeneficiary: string,
     *     whatsappNumber: string
     * }
     */
    public function getCheckoutData(User $teacher, SubscriptionPackage $package): array
    {
        abort_unless((bool) $package->is_active, 404);

        try {
            $activePackage = $this->subscriptionLimitService->resolveActivePackage($teacher);
        } catch (SubscriptionLimitExceededException) {
            $activePackage = null;
        }

        return [
            'teacher' => $teacher,
            'package' => $package,
            'activePackage' => $activePackage,
            'speiClabe' => self::SIMULATED_CLABE,
            'speiBeneficiary' => self::SIMULATED_BENEFICIARY,
            'whatsappNumber' => (string) config('services.acode.whatsapp_sales', '5215500000000'),
        ];
    }

    /**
     * Procesa un cobro instantáneo mediante la pasarela desacoplada y persiste la
     * transacción (aprobada o fallida) junto con la activación atómica del plan.
     *
     * @param User $teacher Cuenta docente verificada que realiza el pago.
     * @param SubscriptionPackage $package Paquete SaaS contratado o renovado.
     * @param array<string, mixed> $paymentData Datos validados del instrumento de pago.
     * @param string|null $ipAddress Dirección IP del cliente web.
     * @param string|null $userAgent User-Agent del navegador solicitante.
     * @return PaymentTransaction Registro persistido de la transacción de pago.
     */
    public function processInstantCheckout(
        User $teacher,
        SubscriptionPackage $package,
        array $paymentData,
        ?string $ipAddress,
        ?string $userAgent,
    ): PaymentTransaction {
        abort_unless($teacher->role === 'teacher' && $teacher->isVerifiedTeacher(), 403);
        abort_unless((bool) $package->is_active, 404);

        // Ejecuta la pasarela fuera de DB::transaction() para no retener locks SQL ante I/O externo.
        $result = $this->paymentGateway->process($package, $teacher, $paymentData);

        /** @var PaymentTransaction $transaction */
        $transaction = DB::transaction(function () use (
            $teacher,
            $package,
            $result,
            $ipAddress,
            $userAgent,
        ): PaymentTransaction {
            $lockedTeacher = User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $lockedPackage = SubscriptionPackage::whereKey($package->id)->lockForUpdate()->firstOrFail();
            abort_unless((bool) $lockedPackage->is_active, 404);

            $referenceCode = $this->generateUniqueReferenceCode();
            $now = now();

            if ($result['approved']) {
                $subscription = $lockedTeacher->teacherSubscriptions()
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                $subscriptionAttributes = [
                    'subscription_package_id' => $lockedPackage->id,
                    'payment_status' => 'paid',
                    'starts_at' => $now,
                    'expires_at' => $now->copy()->addMonth(),
                    'suspended_at' => null,
                    'notes' => "Pago instantáneo simulado ({$result['method']}) · Folio {$referenceCode}",
                ];

                if ($subscription !== null) {
                    $subscription->update($subscriptionAttributes);
                } else {
                    $subscription = TeacherSubscription::create([
                        'teacher_id' => $lockedTeacher->id,
                        ...$subscriptionAttributes,
                    ]);
                }

                $transaction = PaymentTransaction::create([
                    'reference_code' => $referenceCode,
                    'teacher_id' => $lockedTeacher->id,
                    'subscription_package_id' => $lockedPackage->id,
                    'teacher_subscription_id' => $subscription->id,
                    'gateway_provider' => $result['provider'],
                    'payment_method' => $result['method'],
                    'status' => PaymentTransaction::STATUS_PAID,
                    'amount' => $lockedPackage->monthly_price,
                    'currency' => 'MXN',
                    'card_brand' => $result['card_brand'],
                    'card_last4' => $result['card_last4'],
                    'card_holder_name' => $result['card_holder_name'],
                    'spei_tracking_key' => $result['spei_tracking_key'],
                    'failure_code' => null,
                    'failure_message' => null,
                    'metadata' => $result['metadata'],
                    'paid_at' => $now,
                ]);

                $verification = TeacherVerification::where('user_id', $lockedTeacher->id)
                    ->lockForUpdate()
                    ->first();

                if ($verification !== null && $verification->plan_onboarding_completed_at === null) {
                    $verification->forceFill([
                        'plan_onboarding_completed_at' => $now,
                    ])->save();
                }

                AuditLog::create([
                    'user_id' => $lockedTeacher->id,
                    'action' => 'subscription.payment_succeeded',
                    'auditable_type' => PaymentTransaction::class,
                    'auditable_id' => $transaction->id,
                    'new_values' => [
                        'reference_code' => $referenceCode,
                        'subscription_package_id' => $lockedPackage->id,
                        'package_tier' => $lockedPackage->tier,
                        'amount' => (string) $lockedPackage->monthly_price,
                        'currency' => 'MXN',
                        'payment_method' => $result['method'],
                        'gateway_provider' => $result['provider'],
                        'card_brand' => $result['card_brand'],
                        'card_last4' => $result['card_last4'],
                        'spei_tracking_key' => $result['spei_tracking_key'],
                    ],
                    'ip_address' => $ipAddress,
                    'user_agent' => $this->truncateUserAgent($userAgent),
                ]);

                return $transaction;
            }

            $transaction = PaymentTransaction::create([
                'reference_code' => $referenceCode,
                'teacher_id' => $lockedTeacher->id,
                'subscription_package_id' => $lockedPackage->id,
                'teacher_subscription_id' => null,
                'gateway_provider' => $result['provider'],
                'payment_method' => $result['method'],
                'status' => PaymentTransaction::STATUS_FAILED,
                'amount' => $lockedPackage->monthly_price,
                'currency' => 'MXN',
                'card_brand' => $result['card_brand'],
                'card_last4' => $result['card_last4'],
                'card_holder_name' => $result['card_holder_name'],
                'spei_tracking_key' => $result['spei_tracking_key'],
                'failure_code' => $result['failure_code'],
                'failure_message' => $result['failure_message'],
                'metadata' => $result['metadata'],
                'paid_at' => null,
            ]);

            AuditLog::create([
                'user_id' => $lockedTeacher->id,
                'action' => 'subscription.payment_failed',
                'auditable_type' => PaymentTransaction::class,
                'auditable_id' => $transaction->id,
                'new_values' => [
                    'reference_code' => $referenceCode,
                    'subscription_package_id' => $lockedPackage->id,
                    'package_tier' => $lockedPackage->tier,
                    'amount' => (string) $lockedPackage->monthly_price,
                    'currency' => 'MXN',
                    'payment_method' => $result['method'],
                    'gateway_provider' => $result['provider'],
                    'failure_code' => $result['failure_code'],
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);

            return $transaction;
        });

        if ($transaction->status === PaymentTransaction::STATUS_PAID) {
            $this->sendPaymentReceiptEmail($teacher, $package, $transaction);
        }

        return $transaction;
    }

    /**
     * Completa el onboarding post-verificación eligiendo la Prueba Gratuita (Freemium)
     * y registra el evento correspondiente en la bitácora de auditoría.
     *
     * @param User $teacher Cuenta docente verificada.
     * @param string|null $ipAddress Dirección IP del solicitante.
     * @param string|null $userAgent User-Agent del navegador solicitante.
     * @return void
     */
    public function activateFreeTrialOnboarding(
        User $teacher,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        abort_unless($teacher->role === 'teacher' && $teacher->isVerifiedTeacher(), 403);

        DB::transaction(function () use ($teacher, $ipAddress, $userAgent): void {
            $lockedTeacher = User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();

            $verification = TeacherVerification::where('user_id', $lockedTeacher->id)
                ->lockForUpdate()
                ->first();

            if ($verification !== null && $verification->plan_onboarding_completed_at === null) {
                $verification->forceFill([
                    'plan_onboarding_completed_at' => now(),
                ])->save();
            }

            AuditLog::create([
                'user_id' => $lockedTeacher->id,
                'action' => 'subscription.freemium_selected',
                'auditable_type' => TeacherVerification::class,
                'auditable_id' => $verification?->id ?? $lockedTeacher->id,
                'new_values' => [
                    'tier' => 'freemium',
                    'max_cumulative_groups' => SubscriptionLimitService::FREEMIUM_MAX_CUMULATIVE_GROUPS,
                    'max_cumulative_exams' => SubscriptionLimitService::FREEMIUM_MAX_CUMULATIVE_EXAMS,
                    'max_questions' => SubscriptionLimitService::FREEMIUM_MAX_QUESTIONS,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);
        });
    }

    /**
     * Genera un folio único con formato ACS-PAY-YYYYMMDD-XXXXXX respetando la cota
     * máxima de reintentos establecida en MPW-V2 §5.2.1.
     *
     * @return string Folio único de transacción.
     *
     * @throws RuntimeException Si se excede MAX_REFERENCE_ATTEMPTS sin obtener un folio libre.
     */
    private function generateUniqueReferenceCode(): string
    {
        $dateSegment = now()->format('Ymd');
        $alphabetLength = strlen(self::REFERENCE_ALPHABET);

        for ($attempt = 1; $attempt <= self::MAX_REFERENCE_ATTEMPTS; $attempt++) {
            $suffix = '';

            for ($index = 0; $index < 6; $index++) {
                $suffix .= self::REFERENCE_ALPHABET[random_int(0, $alphabetLength - 1)];
            }

            $candidate = sprintf('ACS-PAY-%s-%s', $dateSegment, $suffix);

            if (! PaymentTransaction::where('reference_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('No fue posible generar un folio único de pago tras múltiples intentos.');
    }

    /**
     * Envía el recibo de pago por correo electrónico sin interrumpir el flujo si el
     * servidor SMTP presenta fallas transitorias.
     *
     * @param User $teacher Docente titular del pago.
     * @param SubscriptionPackage $package Paquete contratado.
     * @param PaymentTransaction $transaction Transacción aprobada.
     * @return void
     */
    private function sendPaymentReceiptEmail(
        User $teacher,
        SubscriptionPackage $package,
        PaymentTransaction $transaction,
    ): void {
        $teacher->loadMissing('subscription');
        $renewalDate = $teacher->subscription?->expires_at?->format('d/m/Y')
            ?? now()->addMonth()->format('d/m/Y');

        $amountFormatted = number_format((float) $transaction->amount, 2);
        $subject = "Recibo de suscripción {$package->name} · Folio {$transaction->reference_code}";
        $body = implode("\n", [
            "Hola {$teacher->full_name},",
            '',
            'Confirmamos el pago exitoso de tu suscripción docente en la Plataforma Evaluadora A-Code Solutions.',
            '',
            "Folio de transacción: {$transaction->reference_code}",
            "Plan contratado: {$package->name}",
            "Monto pagado: \${$amountFormatted} {$transaction->currency}",
            "Método de pago: {$transaction->maskedPaymentSummary()}",
            "Próxima renovación: {$renewalDate}",
            '',
            'Tu plan ya se encuentra activo. Puedes consultar el detalle en la sección Mi Suscripción.',
        ]);

        try {
            Mail::raw($body, static function ($message) use ($teacher, $subject): void {
                $message->to($teacher->email)->subject($subject);
            });
        } catch (Throwable $e) {
            Log::warning('No fue posible enviar el recibo de pago de suscripción docente.', [
                'teacher_id' => $teacher->id,
                'reference_code' => $transaction->reference_code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Acota el User-Agent al límite de la columna de acs_audit_logs.
     *
     * @param string|null $userAgent Cadena User-Agent original.
     * @return string|null Cadena truncada a 255 caracteres o null.
     */
    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        return mb_substr($userAgent, 0, 255);
    }
}
