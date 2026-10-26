<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PrivacyNotice;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuarios de demo con credenciales conocidas (ver README) y ya
 * verificados, para que la expo no empiece pidiendo pasar por el 2FA de
 * un usuario que no existe.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $notice = PrivacyNotice::first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@acode-solutions.test'],
            [
                'role' => 'super_admin',
                'first_name' => 'Admin',
                'last_name' => 'A-Code',
                'phone' => '5500000000',
                'password' => 'Demo1234',
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
        );

        $teacher = User::firstOrCreate(
            ['email' => 'docente.demo@acode-solutions.test'],
            [
                'role' => 'teacher',
                'first_name' => 'Laura',
                'last_name' => 'Martínez',
                'phone' => '5511111111',
                'password' => 'Demo1234',
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
        );

        $students = collect(['Ana García', 'Luis Pérez', 'Sofía Ramírez', 'Carlos Torres'])
            ->map(function (string $fullName, int $index) {
                [$first, $last] = explode(' ', $fullName, 2);

                return User::firstOrCreate(
                    ['email' => "alumno{$index}.demo@acode-solutions.test"],
                    [
                        'role' => 'student',
                        'first_name' => $first,
                        'last_name' => $last,
                        'phone' => null,
                        'password' => 'Demo1234',
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ],
                );
            });

        if ($notice) {
            foreach ([$admin, $teacher, ...$students] as $user) {
                $user->privacyAcceptances()->firstOrCreate(
                    ['privacy_notice_id' => $notice->id],
                    ['accepted_at' => now(), 'ip_address' => '127.0.0.1'],
                );
            }
        }

        $proPackage = SubscriptionPackage::where('tier', 'pro')->first();

        if ($proPackage) {
            TeacherSubscription::firstOrCreate(
                ['teacher_id' => $teacher->id],
                [
                    'subscription_package_id' => $proPackage->id,
                    'payment_status' => 'paid',
                    'starts_at' => now()->subDays(10),
                    'expires_at' => now()->addYear(),
                ],
            );
        }

        $this->command?->info('Docente demo: docente.demo@acode-solutions.test / Demo1234');
        $this->command?->info('Alumno demo: alumno0.demo@acode-solutions.test / Demo1234');
    }
}
