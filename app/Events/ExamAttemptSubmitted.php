<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamAttemptSubmitted.php
 * Ubicación:      app/Events/ExamAttemptSubmitted.php
 * Requerimiento:  RW-12, RW-16, RF-EVA-010 (WebSockets Laravel Reverb)
 * Autor:          Equipo de Ingeniería (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Evento transmitido en tiempo real al docente cuando un
 *                 alumno entrega su examen o concluye su tiempo.
 * ============================================================================
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento transmitido en vivo al docente cuando un estudiante entrega su examen.
 *
 * @package App\Events
 */
class ExamAttemptSubmitted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $examId,
        public readonly int $attemptId,
        public readonly int $studentId,
        public readonly string $studentName,
        public readonly string $submittedAt,
        public readonly ?float $finalScore = null,
        public readonly string $status = 'submitted',
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
        return 'exam.attempt.submitted';
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
            'submitted_at' => $this->submittedAt,
            'final_score' => $this->finalScore,
            'status' => $this->status,
        ];
    }
}
