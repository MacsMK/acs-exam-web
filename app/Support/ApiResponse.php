<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ApiResponse.php
 * Ubicación:   app/Support/ApiResponse.php
 * Descripción: Envoltura uniforme de toda respuesta de la API v1 (MPM-V4
 *              §6.5.2): {status, code, message, data, errors}. Todo
 *              controlador de app/Http/Controllers/Api/V1 construye su
 *              respuesta con esta clase, nunca con response()->json()
 *              directo, porque el cliente móvil (core/network/
 *              api_response.dart, acs-exam-mobile) decodifica toda
 *              respuesta esperando exactamente esta forma antes de
 *              construir sus modelos de dominio.
 * ============================================================================
 */

namespace App\Support;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiResponse
{
    /**
     * Opciones de serialización JSON de toda la API (ADR-021):
     * - JSON_PRESERVE_ZERO_FRACTION: un float exacto como 40.0 viaja como
     *   `40.0`, no como `40`. Sin esto PHP lo serializa como entero y un
     *   modelo Dart que espera `double` falla con "type 'int' is not a
     *   subtype of type 'double'".
     * - JSON_UNESCAPED_UNICODE: los acentos viajan como UTF-8 legible.
     */
    private const JSON_OPTIONS = JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE;

    private const JSON_HEADERS = ['Content-Type' => 'application/json; charset=utf-8'];

    public static function success(mixed $data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $code, self::JSON_HEADERS, self::JSON_OPTIONS);
    }

    public static function error(string $message, int $code = 422, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'code' => $code,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $code, self::JSON_HEADERS, self::JSON_OPTIONS);
    }

    /**
     * Traduce cualquier excepción no capturada a la misma envoltura, para
     * que un 422/401/403/404/500 también respete el contrato de §6.5.2 en
     * vez del formato nativo de Laravel. Conectado en bootstrap/app.php,
     * solo para peticiones bajo api/*.
     */
    public static function fromException(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return self::error(
                $e->validator->errors()->first() ?: 'Los datos enviados no son válidos.',
                $e->status,
                $e->errors(),
            );
        }

        if ($e instanceof AuthenticationException) {
            return self::error('No autenticado.', 401);
        }

        if ($e instanceof AuthorizationException) {
            return self::error('No autorizado.', 403);
        }

        if ($e instanceof ModelNotFoundException) {
            return self::error('Recurso no encontrado.', 404);
        }

        if ($e instanceof DomainException) {
            return self::error($e->getMessage() !== '' ? $e->getMessage() : 'Conflicto con las reglas de negocio del recurso.', 409);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() !== '' ? $e->getMessage() : match (true) {
                $status === 401 => 'No autenticado.',
                $status === 403 => 'No autorizado.',
                $status === 404 => 'Recurso no encontrado.',
                default => 'Ocurrió un error al procesar la solicitud.',
            };

            return self::error($message, $status);
        }

        return self::error('Ocurrió un error inesperado en el servidor.', 500);
    }
}
