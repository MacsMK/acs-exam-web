<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     ApiResponseTest.php
 * Ubicación:   tests/Unit/ApiResponseTest.php
 * Descripción: Verifica la serialización JSON de la envoltura de la API
 *              (ADR-021): los decimales exactos conservan su parte
 *              fraccionaria para que el cliente móvil (Dart) los lea como
 *              `double`, y los acentos no se escapan.
 * ============================================================================
 */

namespace Tests\Unit;

use App\Support\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_preserves_zero_fraction_in_decimals(): void
    {
        $body = ApiResponse::success(['score' => 40.0, 'weight' => 100.0])->getContent();

        $this->assertStringContainsString('"score":40.0', $body);
        $this->assertStringContainsString('"weight":100.0', $body);
    }

    public function test_success_does_not_escape_accents(): void
    {
        $body = ApiResponse::success(null, 'Sesión cerrada.')->getContent();

        $this->assertStringContainsString('Sesión cerrada.', $body);
    }

    public function test_error_keeps_the_envelope_and_status_code(): void
    {
        $response = ApiResponse::error('Este intento ya no está activo.', 409);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertStringContainsString('"status":"error"', $response->getContent());
        $this->assertStringContainsString('Este intento ya no está activo.', $response->getContent());
    }

    public function test_response_declares_utf8_json_content_type(): void
    {
        $response = ApiResponse::success();

        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('charset=utf-8', strtolower((string) $response->headers->get('Content-Type')));
    }
}
