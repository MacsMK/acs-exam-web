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
use App\Http\Requests\ResendTwoFactorRequest;
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
            $this->withDemoCode($user, ['user_id' => $user->id]),
            'Registro exitoso. Verifica tu código de un solo uso.',
            201,
        );
    }

    public function registerStudent(RegisterStudentRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated(), 'student');

        return ApiResponse::success(
            $this->withDemoCode($user, ['user_id' => $user->id]),
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

        return ApiResponse::success(
            $this->withDemoCode($user, []),
            'Credenciales válidas. Verifica tu código de un solo uso.',
        );
    }

    /**
     * `demo_code` es un campo opcional solo para entornos de demostración:
     * únicamente se incluye cuando el correo NO sale de verdad
     * (MAIL_MAILER=log o array), igual que el código ya visible en
     * storage/logs/laravel.log. Con un correo real (smtp, ses, etc.) el
     * código llega solo por ese medio y la API nunca lo devuelve, porque
     * entregarlo en la respuesta anularía el segundo factor.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withDemoCode(User $user, array $data): array
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $data['demo_code'] = $user->two_factor_code;
        }

        return $data;
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

        $query = User::where('two_factor_code', $code)
            ->where('two_factor_expires_at', '>', now())
            ->whereIn('role', ['teacher', 'student']);

        if ($request->filled('email')) {
            $query->where('email', $request->string('email')->toString());
        }

        $user = $query->latest('two_factor_expires_at')->first();

        if ($user === null) {
            throw ValidationException::withMessages(['code' => ['El código es inválido o expiró. Solicita uno nuevo.']]);
        }

        $this->authService->verifyTwoFactorCode($user, $code);

        $token = $user->createToken('acs-exam-mobile')->plainTextToken;

        return ApiResponse::success(['token' => $token, 'role' => $user->role]);
    }

    /**
     * api.v1.auth.two-factor.resend (RF-AUT-006, ADR-035). Responde lo mismo
     * exista o no una verificación pendiente para el correo, para no revelar
     * qué cuentas existen; solo avisa (429) cuando falta esperar el intervalo.
     */
    public function resendTwoFactor(ResendTwoFactorRequest $request): JsonResponse
    {
        $user = $this->authService->resendTwoFactorCode($request->string('email')->toString());

        return ApiResponse::success(
            $user !== null ? $this->withDemoCode($user, []) : [],
            'Si existe una verificación pendiente para ese correo, se envió un nuevo código.',
        );
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
