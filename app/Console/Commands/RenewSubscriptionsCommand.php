<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        RenewSubscriptionsCommand.php
 * Ubicación:      app/Console/Commands/RenewSubscriptionsCommand.php
 * Requerimiento:  RF-SUS-005 (Renovación mensual, CAM-010)
 * Actividad:      ACT-WEB-023
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Comando diario (routes/console.php) que cobra las
 *                 renovaciones vencidas con la tarjeta domiciliada y
 *                 reintenta los cobros rechazados durante 3 días.
 * ============================================================================
 */

namespace App\Console\Commands;

use App\Services\TeacherSubscriptionService;
use Illuminate\Console\Command;

class RenewSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:renew';

    protected $description = 'Cobra las renovaciones mensuales domiciliadas vencidas (RF-SUS-005).';

    public function handle(TeacherSubscriptionService $subscriptions): int
    {
        $counts = $subscriptions->processRenewals();

        $this->info("Renovadas: {$counts['renewed']}. Rechazadas: {$counts['failed']}.");

        return self::SUCCESS;
    }
}
