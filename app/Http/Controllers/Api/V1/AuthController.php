<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AuthController.php
 * Ubicación:      app/Http/Controllers/Api/V1/AuthController.php
 * Requerimiento:  RF-AUT-001..011, MPM-V3 §6.6.1, ADR-010, ADR-035, ADR-057
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Controlador de autenticación API v1 consumido por la app
 *                 móvil (acs-exam-mobile): registro docente híbrido y de
 *                 alumnos, login en dos pasos, reenvío 2FA y emisión/revocación
 *                 de tokens Sanctum bajo sobre uniforme ApiResponse.
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
use App\Services\ProfilePhotoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de autenticación API v1 (Sanctum + 2FA + verificación docente).
 *
 * @package App\Http\Controllers\Api\V1
 */
class AuthController extends Controller
{
    /**
     * Inicializa el controlador inyectando el servicio transaccional de autenticación.
     *
     * @param AuthService $authService Servicio de autenticación y 2FA.
     */
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * Registra una cuenta docente vía API v1 aplicando acreditación híbrida.
     *
     * @param RegisterTeacherRequest $request Solicitud validada de alta docente.
     * @return JsonResponse
     */
    public function registerTeacher(RegisterTeacherRequest $request): JsonResponse
    {
        $user = $this->authService->register(
            $request->validated(),
            'teacher',
            $request->file('verification_document'),
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success(
            $this->withDemoCode($user, [
                'user_id' => $user->id,
                'verification_status' => $user->teacher_verification_status,
            ]),
            'Registro exitoso. Verifica tu código de un solo uso.',
            201,
        );
    }

    /**
     * Registra una cuenta de alumno vía API v1 y emite su primer código 2FA.
     *
     * @param RegisterStudentRequest $request Solicitud validada de alta de alumno.
     * @return JsonResponse
     */
    public function registerStudent(RegisterStudentRequest $request, ProfilePhotoService $profilePhotoService): JsonResponse
    {
        $user = $this->authService->register(collect($request->validated())->except('photo')->all(), 'student');

        // Fotografía opcional del estudiante enviada como multipart (RF-AUT-008)
        if ($request->hasFile('photo')) {
            $profilePhotoService->store($user, $request->file('photo'));
        }

        return ApiResponse::success(
            $this->withDemoCode($user, ['user_id' => $user->id]),
            'Registro exitoso. Verifica tu código de un solo uso.',
            201,
        );
    }

    /**
     * Valida credenciales de acceso y dispara el envío del código 2FA.
     *
     * @param LoginRequest $request Solicitud validada con correo y contraseña.
     * @return JsonResponse
     */
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
     * Completa el segundo factor (api.v1.auth.two-factor, MPM-V3 Tabla 12,
     * ADR-010) y emite un token personal Sanctum para la app móvil.
     *
     * @param VerifyTwoFactorRequest $request Solicitud validada con código 2FA.
     * @return JsonResponse
     *
     * @throws ValidationException Si no existe un código vigente coincidente.
     */
    public function twoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        $code = $request->string('code')->toString();

        $query = User::whereIn('role', ['teacher', 'student']);

        if ($request->filled('email')) {
            $user = $query->where('email', $request->string('email')->toString())->first();
        } else {
            $matches = $query->where('two_factor_code', $code)
                ->where('two_factor_expires_at', '>', now())->whereNotNull('two_factor_delivery')->limit(2)->get();
            $user = $matches->count() === 1 ? $matches->first() : null;
        }

        if ($user === null) {
            throw ValidationException::withMessages(['code' => ['El código es inválido o expiró. Solicita uno nuevo.']]);
        }

        $this->authService->verifyTwoFactorCode($user, $code);

        $token = $user->createToken('acs-exam-mobile')->plainTextToken;

        return ApiResponse::success(['token' => $token, 'role' => $user->role]);
    }

    /**
     * Reenvía el código 2FA respetando la ventana de espera (RF-AUT-006, ADR-035)
     * con respuesta uniforme para evitar enumeración de cuentas.
     *
     * @param ResendTwoFactorRequest $request Solicitud validada con correo.
     * @return JsonResponse
     */
    public function resendTwoFactor(ResendTwoFactorRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->resendTwoFactorCode($request->string('email')->toString());
        } catch (ValidationException) {
            // Un fallo de entrega mantiene la misma respuesta para un correo conocido o desconocido.
            $user = null;
        }

        return ApiResponse::success(
            $user !== null ? $this->withDemoCode($user, []) : [],
            'Si existe una verificación pendiente para ese correo, se envió un nuevo código.',
        );
    }

    /**
     * Devuelve el perfil del usuario autenticado por token Sanctum.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        return ApiResponse::success(['user' => request()->user()]);
    }

    /**
     * Revoca el token Sanctum activo de la sesión móvil actual.
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Sesión cerrada.');
    }

    /**
     * Agrega `demo_code` únicamente cuando el mailer no envía correo real
     * (`log` o `array`) para facilitar demostraciones sin exponer 2FA en producción.
     *
     * @param User $user Cuenta con código 2FA recién emitido.
     * @param array<string, mixed> $data Payload base de la respuesta JSON.
     * @return array<string, mixed>
     */
    private function withDemoCode(User $user, array $data): array
    {
        $code = $this->authService->demoTwoFactorCode($user);
        if ($code !== null) {
            $data['demo_code'] = $code;
        }

        return $data;
    }
}
