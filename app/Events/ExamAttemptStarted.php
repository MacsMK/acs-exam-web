<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamAttemptStarted.php
 * Ubicación:      app/Events/ExamAttemptStarted.php
 * Requerimiento:  RW-12, RW-16, RNF-CON-001 (WebSockets Laravel Reverb)
 * Autor:          Equipo de Ingeniería (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Evento transmitido en tiempo real al docente cuando un
 *                 estudiante inicia su intento de examen.
 * ============================================================================
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento transmitido en tiempo real al docente cuando un estudiante inicia su intento.
 *
 * @package App\Events
 */
class ExamAttemptStarted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $examId,
        public readonly int $attemptId,
        public readonly int $studentId,
        public readonly string $studentName,
        public readonly string $startedAt,
        public readonly string $status = 'in_progress',
    ) {
    }

    /**
     * Canal privado exclusivo del examen (protegido para el docente titular).
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
        return 'exam.attempt.started';
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
            'started_at' => $this->startedAt,
            'status' => $this->status,
        ];
    }
}
