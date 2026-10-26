<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AppServiceProvider.php
 * Ubicación:   app/Providers/AppServiceProvider.php
 * Descripción: Registra el modelo de token de Sanctum con el nombre de
 *              tabla institucional (acs_personal_access_tokens).
 * ============================================================================
 */

namespace App\Providers;

use App\Contracts\AiEvaluatorContract;
use App\Models\PersonalAccessToken;
use App\Services\MockAiEvaluator;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ya tenemos nuestra propia migración (BD01-2026-024) para
        // acs_personal_access_tokens con el prefijo institucional; sin
        // esto, Sanctum además intentaría crear su tabla default
        // "personal_access_tokens" (duplicado que no se usaría).
        Sanctum::ignoreMigrations();
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
