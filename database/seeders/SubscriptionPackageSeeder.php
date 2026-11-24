<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        SubscriptionPackageSeeder.php
 * Ubicación:      database/seeders/SubscriptionPackageSeeder.php
 * Requerimiento:  RF-SUS / RF-IAR-011 (Catálogo de paquetes y cupos de IA)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Puebla el catálogo oficial de paquetes de suscripción
 *                 (basic/advanced/pro, MPW-V3 Tabla 8) y garantiza los cupos
 *                 oficiales de peticiones de IA (200 / 600 / ilimitado).
 * ============================================================================
 */

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

/**
 * Catálogo de paquetes (MPW-V3 Tabla 8): basic/advanced/pro.
 *
 * @package Database\Seeders
 */
class SubscriptionPackageSeeder extends Seeder
{
    /**
     * Ejecuta el sembrado idempotente de los paquetes de suscripción.
     *
     * @return void
     */
    public function run(): void
    {
        $packages = [
            [
                'tier' => 'basic',
                'name' => 'Básico',
                'max_groups' => 3,
                'max_questions' => 5000,
                'max_concurrent_students' => 40,
                'max_active_exams' => 5,
                'ai_requests_limit' => 200,
                'monthly_price' => 299.00,
            ],
            [
                'tier' => 'advanced',
                'name' => 'Avanzado',
                'max_groups' => 10,
                'max_questions' => 10000,
                'max_concurrent_students' => 120,
                'max_active_exams' => 25,
                'ai_requests_limit' => 600,
                'monthly_price' => 599.00,
            ],
            [
                'tier' => 'pro',
                'name' => 'Pro',
                'max_groups' => null,
                'max_questions' => null,
                'max_concurrent_students' => 500,
                'max_active_exams' => null,
                'ai_requests_limit' => null,
                'monthly_price' => 999.00,
            ],
        ];

        foreach ($packages as $package) {
            $record = SubscriptionPackage::firstOrCreate(['tier' => $package['tier']], $package);

            if (
                in_array($record->tier, ['basic', 'advanced'], true)
                && $record->ai_requests_limit === null
                && $package['ai_requests_limit'] !== null
            ) {
                $record->update(['ai_requests_limit' => $package['ai_requests_limit']]);
            }
        }
    }
}
