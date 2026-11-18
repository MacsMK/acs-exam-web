<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        UserSeeder.php
 * Ubicación:      database/seeders/UserSeeder.php
 * Requerimiento:  RF-AUT / Verificación Híbrida de Identidad Docente
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.2.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Semilla de usuarios de demostración con credenciales
 *                 conocidas, verificación docente aprobada para el docente demo
 *                 y código de invitación institucional de demostración.
 * ============================================================================
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\PrivacyNotice;
use App\Models\SubscriptionPackage;
use App\Models\TeacherInvitationCode;
use App\Models\TeacherSubscription;
use App\Models\TeacherVerification;
use App\Models\User;

/**
 * Usuarios de demo con credenciales conocidas (ver README) y ya
 * verificados, para que la expo no empiece pidiendo pasar por el 2FA de
 * un usuario que no existe.
 *
 * @package Database\Seeders
 */
class UserSeeder extends Seeder
{
    /**
     * Siembra las cuentas demo, sus aceptaciones de privacidad, suscripción,
     * verificación docente aprobada y código institucional de demostración.
     *
     * @return void
     */
    public function run(): void
    {
        $notice = PrivacyNotice::query()
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->latest('id')
            ->first() ?? PrivacyNotice::first();

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
            ->map(function (string $fullName, int $index): User {
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

        if ($notice !== null) {
            foreach ([$admin, $teacher, ...$students] as $user) {
                $user->privacyAcceptances()->firstOrCreate(
                    ['privacy_notice_id' => $notice->id],
                    ['accepted_at' => now(), 'ip_address' => '127.0.0.1'],
                );
            }
        }

        $demoVerification = TeacherVerification::firstOrCreate(
            ['user_id' => $teacher->id],
            [
                'status' => TeacherVerification::STATUS_APPROVED,
                'verification_method' => TeacherVerification::METHOD_INVITATION_CODE,
                'institution_name' => 'Instituto Tecnológico A-Code Demo',
                'educational_level' => 'superior',
                'is_institutional_email' => true,
                'submitted_at' => now()->subDays(10),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(10),
            ],
        );

        if ($demoVerification->plan_onboarding_completed_at === null) {
            $demoVerification->forceFill([
                'plan_onboarding_completed_at' => now()->subDays(10),
            ])->save();
        }

        $proPackage = SubscriptionPackage::where('tier', 'pro')->first();

        if ($proPackage !== null) {
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

        // SEC-TV-11: evitar sembrar códigos de invitación demo abiertos en producción.
        if (! app()->isProduction()) {
            TeacherInvitationCode::firstOrCreate(
                ['code' => 'ACODE-DEMO-2026'],
                [
                    'institution_name' => 'Institución Demo A-Code',
                    'description' => 'Código de invitación institucional de demostración para acreditación docente.',
                    'max_uses' => 25,
                    'uses_count' => 0,
                    'expires_at' => null,
                    'is_active' => true,
                    'created_by' => $admin->id,
                ],
            );

            $this->command?->info('Código invitación docente demo: ACODE-DEMO-2026');
        }
    }
}
