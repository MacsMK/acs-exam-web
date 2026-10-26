<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AuthController.php
 * Ubicación:   app/Http/Controllers/Api/V1/AuthController.php
 * Descripción: Auth de la app móvil (acs-exam-mobile, MPM-V2 §6.6.1):
 *              registro/login en dos pasos, token Sanctum al final.
 * ============================================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterStudentRequest;
use App\Http\Requests\RegisterTeacherRequest;
use App\Http\Requests\VerifyTwoFactorRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function registerTeacher(RegisterTeacherRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated(), 'teacher');

        return response()->json([
            'message' => 'Registro exitoso. Verifica tu código de un solo uso.',
            'user_id' => $user->id,
        ], 201);
    }

    public function registerStudent(RegisterStudentRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated(), 'student');

        return response()->json([
            'message' => 'Registro exitoso. Verifica tu código de un solo uso.',
            'user_id' => $user->id,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->attemptCredentials(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'message' => 'Credenciales válidas. Verifica tu código de un solo uso.',
            'user_id' => $user->id,
        ]);
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->integer('user_id'));

        $this->authService->verifyTwoFactorCode($user, $request->string('code')->toString());

        $token = $user->createToken('acs-exam-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'role' => $user->role,
                'full_name' => $user->full_name,
                'email' => $user->email,
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json(['user' => request()->user()]);
    }

    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
