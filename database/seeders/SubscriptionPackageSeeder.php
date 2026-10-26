<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

/**
 * Catálogo de paquetes (MPW-V2 Tabla 8): basic/advanced/pro.
 */
class SubscriptionPackageSeeder extends Seeder
{
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
                'ai_requests_limit' => null,
                'monthly_price' => 299.00,
            ],
            [
                'tier' => 'advanced',
                'name' => 'Avanzado',
                'max_groups' => 10,
                'max_questions' => 10000,
                'max_concurrent_students' => 120,
                'max_active_exams' => 25,
                'ai_requests_limit' => null,
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
            SubscriptionPackage::firstOrCreate(['tier' => $package['tier']], $package);
        }
    }
}
