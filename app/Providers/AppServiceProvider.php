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
use App\Models\PersonalAccessToken;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Repositories\Contracts\GroupRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Eloquent\EloquentAttemptRepository;
use App\Repositories\Eloquent\EloquentExamRepository;
use App\Repositories\Eloquent\EloquentGroupRepository;
use App\Repositories\Eloquent\EloquentQuestionRepository;
use App\Services\MockAiEvaluator;
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
        // AI_EVALUATOR_DRIVER=mock en demo (sin llave externa); cambiar a
        // un driver real (ej. "gemini") cuando exista esa integración.
        $this->app->bind(AiEvaluatorContract::class, match (env('AI_EVALUATOR_DRIVER', 'mock')) {
            default => MockAiEvaluator::class,
        });

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
