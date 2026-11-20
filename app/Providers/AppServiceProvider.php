<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AppServiceProvider.php
 * Ubicación:      app/Providers/AppServiceProvider.php
 * Requerimiento:  MPW-V2 §2.3, ADR-002, ADR-016, ADR-030, ADR-057
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Registra bindings de contratos de servicios (IA, Push FCM,
 *                 verificador enchufable de cédula SEP) y repositorios Eloquent,
 *                 configura el modelo institucional de tokens Sanctum y activa
 *                 la prevención estricta de lazy loading fuera de producción.
 * ============================================================================
 */

namespace App\Providers;

use App\Contracts\AiEvaluatorContract;
use App\Contracts\PaymentGatewayContract;
use App\Contracts\ProfessionalLicenseVerifierContract;
use App\Contracts\PushNotifierContract;
use App\Models\PersonalAccessToken;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Repositories\Contracts\GroupRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Eloquent\EloquentAttemptRepository;
use App\Repositories\Eloquent\EloquentExamRepository;
use App\Repositories\Eloquent\EloquentGroupRepository;
use App\Repositories\Eloquent\EloquentQuestionRepository;
use App\Services\Ai\GeminiAiEvaluator;
use App\Services\MockAiEvaluator;
use App\Services\Payment\SimulatedPaymentGateway;
use App\Services\Push\FcmPushNotifier;
use App\Services\Push\NullPushNotifier;
use App\Services\Verification\ManualProfessionalLicenseVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * Proveedor principal de servicios y contratos de la aplicación.
 *
 * @package App\Providers
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra en el contenedor de dependencias los contratos y repositorios.
     *
     * @return void
     */
    public function register(): void
    {
        // Driver de evaluación asistida por IA (ADR-059): 'gemini' (GeminiAiEvaluator
        // con modelo dinámico desde config y fallback a MockAiEvaluator) o 'mock'.
        $this->app->bind(AiEvaluatorContract::class, static function ($app): AiEvaluatorContract {
            return config('services.ai.driver', 'gemini') === 'gemini'
                ? $app->make(GeminiAiEvaluator::class)
                : $app->make(MockAiEvaluator::class);
        });

        // FCM_ENABLED=false (demo, desarrollo y CI) no contacta a Firebase;
        // true envía por FCM HTTP v1 con la cuenta de servicio de
        // FCM_CREDENTIALS_PATH (MPM-V4 §6.8).
        $this->app->bind(PushNotifierContract::class, static fn () => config('services.fcm.enabled')
            ? app(FcmPushNotifier::class)
            : app(NullPushNotifier::class));

        // Verificador enchufable de cédula profesional SEP (ADR-057):
        // 'manual' por defecto para revisión en el portal del Superadministrador.
        $this->app->bind(ProfessionalLicenseVerifierContract::class, static function ($app): ProfessionalLicenseVerifierContract {
            $driver = (string) config('services.sep_cedula.driver', 'manual');

            return match ($driver) {
                default => $app->make(ManualProfessionalLicenseVerifier::class),
            };
        });

        // Pasarela de pago enchufable para suscripciones docentes (ADR-058):
        // 'simulated' por defecto (tarjeta con Luhn y SPEI instantáneo sin cobro real).
        $this->app->bind(PaymentGatewayContract::class, static function ($app): PaymentGatewayContract {
            $driver = (string) config('services.payment_gateway.driver', 'simulated');

            return match ($driver) {
                default => $app->make(SimulatedPaymentGateway::class),
            };
        });

        $this->app->bind(ExamRepositoryInterface::class, EloquentExamRepository::class);
        $this->app->bind(GroupRepositoryInterface::class, EloquentGroupRepository::class);
        $this->app->bind(QuestionRepositoryInterface::class, EloquentQuestionRepository::class);
        $this->app->bind(AttemptRepositoryInterface::class, EloquentAttemptRepository::class);
    }

    /**
     * Inicializa reglas globales de Eloquent, Sanctum y esquema de URLs.
     *
     * @return void
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Sanctum (a partir de v3/v4) no autocarga ninguna migración
        // propia por default — solo hay que decirle que use nuestro
        // modelo, que ya apunta a la tabla con el prefijo institucional
        // (acs_personal_access_tokens, migración BD01-2026-024).
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        if ($url = config('app.url')) {
            URL::forceRootUrl($url);
        }
    }
}
