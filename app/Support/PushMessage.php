<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        PushMessage.php
 * Ubicación:      app/Support/PushMessage.php
 * Requerimiento:  RF-EXA-054 (Notificación de programación o publicación
 *                 de exámenes); los avisos exam.opened, exam.started y
 *                 exam.finished aún no tienen código en DE-01
 *                 (MPM-V4 §6.8, Tabla 14)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Mensaje push inmutable con el contrato de la Tabla 14 del
 *                 manual móvil: cada mensaje lleva los campos de datos
 *                 `type`, `exam_id` y `group_id`, siempre como texto porque
 *                 FCM solo admite cadenas en el bloque `data`.
 * ============================================================================
 */

namespace App\Support;

final readonly class PushMessage
{
    /** Alumno: se asignó o programó un examen (abre la lista de exámenes). */
    public const EXAM_ASSIGNED = 'exam.assigned';

    /** Alumno: el examen ya está disponible. */
    public const EXAM_OPENED = 'exam.opened';

    /** Docente: el examen ya comenzó (abre el avance del examen). */
    public const EXAM_STARTED = 'exam.started';

    /** Docente: el examen ya terminó (abre el avance y las calificaciones). */
    public const EXAM_FINISHED = 'exam.finished';

    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public int $examId,
        public int $groupId,
    ) {
    }

    /**
     * Bloque `data` del mensaje FCM (MPM-V4 Tabla 14).
     *
     * @return array{type: string, exam_id: string, group_id: string}
     */
    public function data(): array
    {
        return [
            'type' => $this->type,
            'exam_id' => (string) $this->examId,
            'group_id' => (string) $this->groupId,
        ];
    }
}
