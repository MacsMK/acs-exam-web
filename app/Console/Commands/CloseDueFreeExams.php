<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        CloseDueFreeExams.php
 * Ubicación:      app/Console/Commands/CloseDueFreeExams.php
 * Requerimiento:  RF-EXA-043
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Finaliza los intentos libres al vencimiento registrado en el servidor.
 * ============================================================================
 */

namespace App\Console\Commands;

use App\Models\StudentAttempt;
use App\Services\AttemptService;
use Illuminate\Console\Command;

class CloseDueFreeExams extends Command
{
    protected $signature = 'exams:close-due-free';
    protected $description = 'Finaliza intentos de navegación libre cuyo plazo registrado ya venció';

    /**
     * Entrega los intentos libres vencidos y respeta los ya finalizados.
     *
     * @param AttemptService $attempts Servicio de intentos y entrega.
     * @return int Código de salida o identificador de la operación.
     */
    public function handle(AttemptService $attempts): int
    {
        StudentAttempt::where('status', 'in_progress')->where('free_close_at', '<=', now())
            ->chunkById(100, function ($due) use ($attempts): void {
                foreach ($due as $attempt) {
                    $attempts->closeFreeIfDue($attempt);
                }
            });

        return self::SUCCESS;
    }
}
