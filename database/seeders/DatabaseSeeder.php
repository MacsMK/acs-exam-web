<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PrivacySeeder::class,
            SubscriptionPackageSeeder::class,
            UserSeeder::class,
            GroupSeeder::class,
            QuestionBankSeeder::class,
            ExamSeeder::class,
        ]);
    }
}
