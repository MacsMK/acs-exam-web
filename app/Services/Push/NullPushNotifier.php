<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        NullPushNotifier.php
 * Ubicación:      app/Services/Push/NullPushNotifier.php
 * Requerimiento:  Notificaciones push (MPM-V4 §6.8, Tabla 14); código de
 *                 DE-01 pendiente de registro
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Notificador por defecto cuando FCM_ENABLED=false (demo,
 *                 desarrollo y CI): no contacta a Firebase, solo deja una
 *                 línea en el log para poder revisar qué se habría enviado.
 * ============================================================================
 */

namespace App\Services\Push;

use App\Contracts\PushNotifierContract;
use App\Support\PushMessage;
use Illuminate\Support\Facades\Log;

class NullPushNotifier implements PushNotifierContract
{
    public function sendToUsers(array $userIds, PushMessage $message): int
    {
        Log::info('Push omitido: FCM deshabilitado (FCM_ENABLED=false).', [
            'type' => $message->type,
            'exam_id' => $message->examId,
            'group_id' => $message->groupId,
            'recipients' => count($userIds),
        ]);

        return 0;
    }
}
