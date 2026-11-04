<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        DispatchDueExamPushCommand.php
 * Ubicación:      app/Console/Commands/DispatchDueExamPushCommand.php
 * Requerimiento:  Notificaciones push (MPM-V4 §6.8, Tabla 14); código de
 *                 DE-01 pendiente de registro
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Comando programado cada minuto (routes/console.php) que
 *                 envía exam.opened cuando abre la ventana de un examen y
 *                 exam.finished cuando vence. Es idempotente: cada aviso se
 *                 envía una sola vez por examen.
 * ============================================================================
 */

namespace App\Console\Commands;

use App\Services\ExamNotificationService;
use Illuminate\Console\Command;

class DispatchDueExamPushCommand extends Command
{
    protected $signature = 'exams:push-due';

    protected $description = 'Envía las notificaciones push de exámenes que abrieron o vencieron.';

    public function handle(ExamNotificationService $notifications): int
    {
        $counts = $notifications->dispatchDue();

        $this->info(sprintf('Abiertos: %d. Terminados: %d.', $counts['opened'], $counts['finished']));

        return self::SUCCESS;
    }
}
