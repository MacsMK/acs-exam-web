<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     AuthController.php
 * Ubicación:   app/Http/Controllers/Api/V1/AuthController.php
 * Descripción: Auth de la app móvil (acs-exam-mobile, MPM-V4 §6.6.1):
 *              login en dos pasos, token Sanctum al final. Respuestas
 *              alineadas 1:1 contra ApiEndpoints/AuthRepository del
 *              cliente móvil (ver docs/04_DECISIONES_ADR.md).
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
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function registerTeacher(RegisterTeacherRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated(), 'teacher');

        return ApiResponse::success(
            ['user_id' => $user->id, 'demo_code' => $user->two_factor_code],
            'Registro exitoso. Verifica tu código de un solo uso.',
            201,
        );
    }

    public function registerStudent(RegisterStudentRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated(), 'student');

        return ApiResponse::success(
            ['user_id' => $user->id, 'demo_code' => $user->two_factor_code],
            'Registro exitoso. Verifica tu código de un solo uso.',
            201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->attemptCredentials(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        // `demo_code` expone el mismo código 2FA que ya se ve "gratis" en el
        // panel web (resources/views/auth/verify-2fa.blade.php) y en
        // storage/logs/laravel.log — no hay envío de correo real en este
        // proyecto (MAIL_MAILER=log), así que sin esto la app móvil no tiene
        // ninguna forma de conseguir el código salvo pedirle a alguien que
        // revise el log del backend. No es una fuga de seguridad nueva: es
        // la misma información que el panel web ya muestra en pantalla sin
        // ninguna restricción.
        return ApiResponse::success(
            ['demo_code' => $user->two_factor_code],
            'Credenciales válidas. Verifica tu código de un solo uso.',
        );
    }

    /**
     * api.v1.auth.two-factor (MPM-V4 Tabla 12). A diferencia del panel web
     * (sesión con `user_id` disponible desde el paso 1,
     * Auth\LoginController), la API móvil es sin estado entre `login()` y
     * este paso: `AuthRepository.verifyTwoFactor` del cliente Flutter
     * solo manda `{"code": "..."}`, sin ningún identificador de usuario
     * (acs-exam-mobile, auth_repository.dart). Se localiza al usuario por
     * el código vigente y no vencido — es la única información disponible
     * para hacerlo; el riesgo de colisión entre dos códigos idénticos
     * vigentes al mismo tiempo se acepta y queda documentado como
     * decisión de diseño (docs/04_DECISIONES_ADR.md).
     */
    public function twoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        $code = $request->string('code')->toString();

        $user = User::where('two_factor_code', $code)
            ->where('two_factor_expires_at', '>', now())
            ->whereIn('role', ['teacher', 'student'])
            ->latest('two_factor_expires_at')
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages(['code' => ['El código es inválido o expiró. Solicita uno nuevo.']]);
        }

        $this->authService->verifyTwoFactorCode($user, $code);

        $token = $user->createToken('acs-exam-mobile')->plainTextToken;

        return ApiResponse::success(['token' => $token, 'role' => $user->role]);
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success(['user' => request()->user()]);
    }

    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Sesión cerrada.');
    }
}
