<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        FakePushNotifier.php
 * Ubicación:      tests/Support/FakePushNotifier.php
 * Requerimiento:  Notificaciones push (MPM-V4 §6.8, Tabla 14); código de
 *                 DE-01 pendiente de registro
 * Autor:          Calvo Sandoval Miguel Alexander (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Notificador de prueba que registra cada envío en memoria
 *                 para verificar a quién se avisó y con qué mensaje, sin
 *                 contactar a Firebase.
 * ============================================================================
 */

namespace Tests\Support;

use App\Contracts\PushNotifierContract;
use App\Support\PushMessage;

final class FakePushNotifier implements PushNotifierContract
{
    /**
     * @var list<array{users: list<int>, message: PushMessage}>
     */
    public array $sent = [];

    public function sendToUsers(array $userIds, PushMessage $message): int
    {
        sort($userIds);
        $this->sent[] = ['users' => $userIds, 'message' => $message];

        return count($userIds);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_map(static fn (array $entry): string => $entry['message']->type, $this->sent);
    }
}
