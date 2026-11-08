<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamAttemptIncidentReported.php
 * Ubicación:      app/Events/ExamAttemptIncidentReported.php
 * Requerimiento:  RW-12, RW-16, ADR-019 (WebSockets Laravel Reverb)
 * Autor:          Equipo de Ingeniería (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Evento transmitido en tiempo real al docente cuando se
 *                 detecta un incidente de sospecha anti-trampa en un intento.
 * ============================================================================
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento transmitido en vivo al docente cuando se detecta un incidente anti-fraude.
 *
 * @package App\Events
 */
class ExamAttemptIncidentReported implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $examId,
        public readonly int $attemptId,
        public readonly int $studentId,
        public readonly string $studentName,
        public readonly string $incidentType,
        public readonly string $description,
        public readonly int $incidentCount,
        public readonly string $occurredAt,
        public readonly bool $isCancelled = false,
    ) {
    }

    /**
     * Canal privado exclusivo del examen.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('exam.' . $this->examId);
    }

    /**
     * Nombre del evento para Laravel Echo / WebSockets.
     */
    public function broadcastAs(): string
    {
        return 'exam.incident.reported';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'exam_id' => $this->examId,
            'attempt_id' => $this->attemptId,
            'student_id' => $this->studentId,
            'student_name' => $this->studentName,
            'incident_type' => $this->incidentType,
            'description' => $this->description,
            'incident_count' => $this->incidentCount,
            'occurred_at' => $this->occurredAt,
            'is_cancelled' => $this->isCancelled,
        ];
    }
}
