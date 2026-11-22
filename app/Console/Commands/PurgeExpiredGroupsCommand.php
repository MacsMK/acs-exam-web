<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        PurgeExpiredGroupsCommand.php
 * Ubicación:      app/Console/Commands/PurgeExpiredGroupsCommand.php
 * Requerimiento:  RF-GRU-015 (Eliminación automática de grupos, CAM-009)
 * Actividad:      ACT-WEB-022
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Comando diario (routes/console.php) que envía los avisos de
 *                 30 días y elimina los grupos cerrados hace 12 meses que no
 *                 se reabrieron. Es idempotente.
 * ============================================================================
 */

namespace App\Console\Commands;

use App\Services\GroupRetentionService;
use Illuminate\Console\Command;

class PurgeExpiredGroupsCommand extends Command
{
    protected $signature = 'groups:purge-expired';

    protected $description = 'Avisa y elimina los grupos cerrados que cumplieron su vigencia (RF-GRU-015).';

    public function handle(GroupRetentionService $retention): int
    {
        $warned = $retention->sendWarnings();
        $deleted = $retention->purgeExpired();

        $this->info("Avisos enviados: {$warned}. Grupos eliminados: {$deleted}.");

        return self::SUCCESS;
    }
}
