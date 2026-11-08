<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        SubscriptionLimitExceededException.php
 * Ubicación:      app/Exceptions/SubscriptionLimitExceededException.php
 * Requerimiento:  RF-SUS-007, RF-SUS-010, RNF-SEG-003 (MPW-V2 §6.6.2)
 * Autor:          Equipo de Ingeniería (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Excepción de dominio disparada cuando un docente alcanza
 *                 las cotas de su paquete o el límite de prueba freemium.
 * ============================================================================
 */

namespace App\Exceptions;

use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SubscriptionLimitExceededException extends DomainException implements HttpExceptionInterface
{
    public function __construct(
        string $message,
        private readonly string $limitType,
        private readonly int $currentUsage,
        private readonly ?int $maxLimit,
        private readonly string $tier = 'freemium',
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 403; // Forbidden por política comercial SaaS
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return ['X-Subscription-Limit-Type' => $this->limitType];
    }

    /**
     * Renderizado uniforme para Web y API v1 (MPW-V2 §6.6 / MPM-V4 §6.5.2).
     */
    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiResponse::error($this->getMessage(), 403, [
                'limit_type' => $this->limitType,
                'current_usage' => $this->currentUsage,
                'max_limit' => $this->maxLimit,
                'tier' => $this->tier,
            ]);
        }

        return back()->withInput()->withErrors([
            'subscription' => $this->getMessage(),
        ]);
    }

    public function getLimitType(): string
    {
        return $this->limitType;
    }

    public function getCurrentUsage(): int
    {
        return $this->currentUsage;
    }

    public function getMaxLimit(): ?int
    {
        return $this->maxLimit;
    }

    public function getTier(): string
    {
        return $this->tier;
    }
}
