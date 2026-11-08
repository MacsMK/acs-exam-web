<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     PushNotifierContract.php
 * Ubicación:   app/Contracts/PushNotifierContract.php
 * Descripción: Contrato del envío de notificaciones push (MPM-V4 §6.8). Aísla
 *              a Firebase Cloud Messaging detrás de una interfaz, igual que
 *              AiEvaluatorContract aísla a la IA: el resto del código no sabe
 *              si el envío es real (FcmPushNotifier) o está deshabilitado
 *              (NullPushNotifier).
 * ============================================================================
 */

namespace App\Contracts;

use App\Support\PushMessage;

interface PushNotifierContract
{
    /**
     * Envía el mensaje a todos los dispositivos registrados de los usuarios.
     * Nunca lanza excepciones: un fallo de red o de Firebase no debe romper
     * el flujo del examen.
     *
     * @param  list<int>  $userIds
     * @return int Cantidad de dispositivos a los que FCM aceptó el mensaje.
     */
    public function sendToUsers(array $userIds, PushMessage $message): int;
}
