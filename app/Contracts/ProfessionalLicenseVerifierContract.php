<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ProfessionalLicenseVerifierContract.php
 * Ubicación:      app/Contracts/ProfessionalLicenseVerifierContract.php
 * Requerimiento:  RF-AUT-001, ADR-057 (Verificación Híbrida de Identidad Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Contrato enchufable para validar cédulas profesionales SEP.
 *                 Desacopla el servicio de acreditación docente del proveedor
 *                 concreto (revisión manual por defecto o futura integración
 *                 API gubernamental/comercial), replicando el patrón de
 *                 AiEvaluatorContract y PushNotifierContract.
 * ============================================================================
 */

namespace App\Contracts;

/**
 * Contrato de verificación de cédulas profesionales ante proveedores SEP.
 *
 * @package App\Contracts
 */
interface ProfessionalLicenseVerifierContract
{
    /**
     * Valida o registra la verificación de una cédula profesional declarada.
     *
     * @param string $licenseNumber Número de cédula profesional declarado por el docente.
     * @param string $fullName Nombre completo del titular para cotejo de identidad.
     * @return array{verified: bool, status: string, provider: string, details: array<string, mixed>}
     */
    public function verify(string $licenseNumber, string $fullName): array;
}
