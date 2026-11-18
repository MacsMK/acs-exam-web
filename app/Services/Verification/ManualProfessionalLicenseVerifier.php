<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ManualProfessionalLicenseVerifier.php
 * Ubicación:      app/Services/Verification/ManualProfessionalLicenseVerifier.php
 * Requerimiento:  RF-AUT-001, ADR-057 (Verificación Híbrida de Identidad Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Implementación por defecto ('manual') del verificador de
 *                 cédulas profesionales SEP. Normaliza dígitos, valida el
 *                 formato oficial de 7 a 8 dígitos y prepara la metadata de
 *                 cotejo manual para el portal del Superadministrador.
 * ============================================================================
 */

namespace App\Services\Verification;

use App\Contracts\ProfessionalLicenseVerifierContract;

/**
 * Verificador manual de cédula profesional SEP (driver por defecto).
 *
 * @package App\Services\Verification
 */
class ManualProfessionalLicenseVerifier implements ProfessionalLicenseVerifierContract
{
    /**
     * Identificador canónico del proveedor de revisión manual.
     */
    public const PROVIDER_NAME = 'manual';

    /**
     * Estado devuelto cuando la cédula cumple formato oficial y pasa a cotejo humano.
     */
    public const STATUS_MANUAL_REVIEW = 'manual_review';

    /**
     * Estado devuelto cuando la cédula no cumple el formato numérico oficial de 7 a 8 dígitos.
     */
    public const STATUS_INVALID_FORMAT = 'invalid_format';

    /**
     * Enlace oficial del Registro Nacional de Profesionistas (SEP).
     */
    public const SEP_LOOKUP_URL = 'https://cedulaprofesional.sep.gob.mx/';

    /**
     * Normaliza la cédula recibida, verifica su formato oficial (7 a 8 dígitos)
     * y devuelve el resultado estructurado para auditoría y revisión manual.
     *
     * @param string $licenseNumber Número de cédula profesional declarado.
     * @param string $fullName Nombre completo del titular declarado.
     * @return array{verified: bool, status: string, provider: string, details: array<string, mixed>}
     */
    public function verify(string $licenseNumber, string $fullName): array
    {
        $trimmed = trim($licenseNumber);
        $normalized = preg_replace('/[\s-]+/u', '', $trimmed) ?? $trimmed;
        $isValidFormat = preg_match('/^[0-9]{7,8}$/', $normalized) === 1;

        return [
            'verified' => false,
            'status' => $isValidFormat ? self::STATUS_MANUAL_REVIEW : self::STATUS_INVALID_FORMAT,
            'provider' => self::PROVIDER_NAME,
            'details' => [
                'license_number' => $normalized,
                'declared_name' => trim($fullName),
                'lookup_url' => self::SEP_LOOKUP_URL,
            ],
        ];
    }
}
