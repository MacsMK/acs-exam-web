<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        channels.php
 * Ubicación:      routes/channels.php
 * Requerimiento:  RW-12, RW-16, RNF-SEG-004 (Canales Privados Laravel Reverb)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.2.0
 * Fecha Creación: 08/11/2026
 * Descripción:    Autorización de canales privados WebSocket con validación
 *                 estricta de parámetros, cuenta activa, verificación docente
 *                 aprobada y aislamiento multi-docente (ADR-057).
 * ============================================================================
 */

use Illuminate\Support\Facades\Broadcast;

use App\Models\Exam;
use App\Models\User;

Broadcast::channel('App.Models.User.{id}', function ($user, $id): bool {
    return (int) $user->id === (int) $id;
});

/**
 * Canal privado de monitoreo docente en vivo por examen (RW-12, RW-16, RNF-SEG-004, ADR-057).
 */
Broadcast::channel('exam.{examId}', function (User $user, mixed $examId): bool {
    if ($user->role !== 'teacher' || ! $user->is_active || ! $user->isVerifiedTeacher()) {
        return false;
    }

    $rawId = is_string($examId) || is_int($examId) ? (string) $examId : '';
    if ($rawId === '' || ! ctype_digit($rawId)) {
        return false;
    }

    $numericId = (int) $rawId;
    if ($numericId <= 0) {
        return false;
    }

    $exam = Exam::withoutGlobalScopes()->find($numericId);

    return $exam !== null && (int) $exam->teacher_id === (int) $user->id;
});
