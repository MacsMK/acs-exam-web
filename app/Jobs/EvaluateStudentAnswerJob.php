<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        EvaluateStudentAnswerJob.php
 * Ubicación:      app/Jobs/EvaluateStudentAnswerJob.php
 * Requerimiento:  RF-IAR-001..014, RF-EVA-004..013, RNF-CON-002, ADR-059
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Trabajo encolable que ejecuta de forma asíncrona (o síncrona
 *                 en modo sync) la evaluación semántica con IA de una respuesta
 *                 abierta o de programación delegando a AiEvaluationService.
 * ============================================================================
 */

namespace App\Jobs;

use App\Services\AiEvaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job encolable para evaluación bajo demanda de reactivos cualitativos con IA.
 *
 * @package App\Jobs
 */
class EvaluateStudentAnswerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Número máximo de intentos del trabajo ante fallas transitorias.
     *
     * @var int
     */
    public int $tries = 2;

    /**
     * Intervalos de espera progresiva (segundos) entre reintentos.
     *
     * @var list<int>
     */
    public array $backoff = [5, 15];

    /**
     * Tiempo máximo de ejecución del trabajo en segundos.
     *
     * @var int
     */
    public int $timeout = 30;

    /**
     * Crea una nueva instancia del trabajo de evaluación con IA.
     *
     * @param int $studentAnswerId Identificador de la respuesta a evaluar.
     * @param int $teacherId Identificador del docente propietario del examen.
     * @param bool $isReevaluation Indica si corresponde a una reevaluación explícita.
     */
    public function __construct(
        public readonly int $studentAnswerId,
        public readonly int $teacherId,
        public readonly bool $isReevaluation = false,
    ) {
    }

    /**
     * Ejecuta la evaluación semántica delegando al servicio de dominio.
     *
     * @param AiEvaluationService $aiEvaluationService Servicio de evaluación IA.
     * @return void
     */
    public function handle(AiEvaluationService $aiEvaluationService): void
    {
        $aiEvaluationService->executeEvaluation(
            $this->studentAnswerId,
            $this->teacherId,
            $this->isReevaluation,
        );
    }
}
