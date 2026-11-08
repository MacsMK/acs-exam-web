<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     DeviceController.php
 * Ubicación:   app/Http/Controllers/Api/V1/DeviceController.php
 * Descripción: api.v1.devices.store (MPM-V4 §6.8, MPW-V2 §6.3): registra el
 *              token de Firebase Cloud Messaging del dispositivo.
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRegisterRequest;
use App\Models\UserDevice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    public function store(DeviceRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $platform = in_array($data['platform'] ?? 'android', ['android', 'ios'], true)
            ? $data['platform']
            : 'android';

        UserDevice::updateOrCreate(
            ['user_id' => $request->user()->id, 'fcm_token' => $data['token']],
            ['platform' => $platform, 'last_active_at' => now()],
        );

        return ApiResponse::success(null, 'Dispositivo registrado.');
    }
}
