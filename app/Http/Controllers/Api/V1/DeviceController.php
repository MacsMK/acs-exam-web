<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     DeviceController.php
 * Ubicación:   app/Http/Controllers/Api/V1/DeviceController.php
 * Descripción: api.v1.devices.store (MPM-V4 §6.8): registra el token de
 *              Firebase Cloud Messaging del dispositivo.
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * `platform` no viene en el cuerpo de la petición
     * (DeviceRepository.registerDeviceToken del cliente móvil solo manda
     * `{"token": "..."}`): el proyecto solo construye para Android por
     * ahora (M-12, ya confirmado por el equipo móvil — "Flutter §1.1 y
     * §1.4: excluye iOS explícitamente"), así que se asume ese valor en
     * vez de exigir un campo que el cliente nunca manda.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);

        UserDevice::updateOrCreate(
            ['user_id' => $request->user()->id, 'fcm_token' => $data['token']],
            ['platform' => 'android', 'last_active_at' => now()],
        );

        return ApiResponse::success(null, 'Dispositivo registrado.');
    }
}
