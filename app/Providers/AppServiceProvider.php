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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sanctum (a partir de v3/v4) no autocarga ninguna migración
        // propia por default — solo hay que decirle que use nuestro
        // modelo, que ya apunta a la tabla con el prefijo institucional
        // (acs_personal_access_tokens, migración BD01-2026-024).
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Forzar la URL raíz a partir de APP_URL en vez de confiar en el
        // Host que reciba la petición. Necesario detrás de proxies que
        // reescriben el Host hacia el backend interno (ej. GitHub
        // Codespaces port forwarding, que le manda a Laravel
        // "Host: localhost:<puerto>" aunque el dominio público sea otro):
        // sin esto, redirect()->route(...) arma la URL con ese Host
        // interno y el navegador termina apuntando a un "localhost" que
        // no existe para quien no sea el dueño de la máquina/Codespace.
        if ($url = config('app.url')) {
            URL::forceRootUrl($url);
        }
    }
}
