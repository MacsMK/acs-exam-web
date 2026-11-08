<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AppServiceProvider.php
 * Ubicación:   app/Providers/AppServiceProvider.php
 * Descripción: Registra bindings de contratos de servicios y repositorios,
 *              el modelo de token de Sanctum con el nombre de tabla
 *              institucional (acs_personal_access_tokens) y la prevención
 *              estricta de lazy loading en entornos no productivos.
 * ============================================================================
 */

namespace App\Providers;

use App\Contracts\AiEvaluatorContract;
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
use App\Services\Push\FcmPushNotifier;
use App\Services\Push\NullPushNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Driver de evaluación asistida por IA: 'gemini' (GeminiAiEvaluator con
        // fallback a MockAiEvaluator) o 'mock' (MockAiEvaluator léxico local).
        $this->app->bind(AiEvaluatorContract::class, static function ($app) {
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

        $this->app->bind(ExamRepositoryInterface::class, EloquentExamRepository::class);
        $this->app->bind(GroupRepositoryInterface::class, EloquentGroupRepository::class);
        $this->app->bind(QuestionRepositoryInterface::class, EloquentQuestionRepository::class);
        $this->app->bind(AttemptRepositoryInterface::class, EloquentAttemptRepository::class);
    }

    /**
     * Bootstrap any application services.
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
