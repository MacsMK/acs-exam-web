<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        OrganicDataSeeder.php
 * Ubicación:      database/seeders/OrganicDataSeeder.php
 * Requerimiento:  N/A (herramienta de datos de demostración; sin RF asociado)
 * Autor:          Calvo Sandoval Miguel Alexander (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Siembra docentes, grupos y alumnos con nombres reales en
 *                 español (acentos, nombres compuestos) para demostraciones
 *                 y pruebas manuales con volumen realista. Es OPT-IN: no se
 *                 invoca desde DatabaseSeeder para no alterar los usuarios
 *                 de demostración del README.
 *
 *                 Uso:  php artisan db:seed --class=OrganicDataSeeder
 *
 *                 Es idempotente (firstOrCreate por correo y por docente +
 *                 nombre de grupo): puede ejecutarse varias veces sin
 *                 duplicar registros. Todas las cuentas usan la contraseña
 *                 Demo1234 y el dominio .test (no envía correo real).
 * ============================================================================
 */

namespace Database\Seeders;

use App\Models\Group;
use App\Models\PrivacyNotice;
use App\Models\SubscriptionPackage;
use App\Models\TeacherSubscription;
use App\Models\User;
use Database\Seeders\Support\OrganicNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrganicDataSeeder extends Seeder
{
    /**
     * Contraseña común de las cuentas sembradas (ver README).
     */
    private const DEMO_PASSWORD = 'Demo1234';

    /**
     * Cantidad de alumnos sembrados.
     */
    private const STUDENT_COUNT = 30;

    /**
     * Docentes: nombre, apellidos y teléfono de 10 dígitos.
     *
     * @var list<array{first_name: string, last_name: string, phone: string}>
     */
    private const TEACHERS = [
        ['first_name' => 'Roberto', 'last_name' => 'Hernández Aguilar', 'phone' => '5512345678'],
        ['first_name' => 'Mariana', 'last_name' => 'Villanueva Cortés', 'phone' => '3312345678'],
        ['first_name' => 'José Antonio', 'last_name' => 'Salazar Ibarra', 'phone' => '8112345678'],
    ];

    /**
     * Materias; cada docente recibe dos grupos de estas materias.
     *
     * @var list<string>
     */
    private const SUBJECTS = [
        'Auditoría de Sistemas',
        'Ingeniería de Software',
        'Bases de Datos',
        'Redes de Computadoras',
        'Seguridad Informática',
        'Programación Móvil',
    ];

    public function run(): void
    {
        // Se cifra una sola vez: el cast "hashed" de User respeta un hash ya
        // vigente, así que 33 cuentas no cuestan 33 cifrados bcrypt.
        $password = Hash::make(self::DEMO_PASSWORD);
        $notice = PrivacyNotice::first();
        $proPackage = SubscriptionPackage::where('tier', 'pro')->first();

        $groups = [];

        foreach (self::TEACHERS as $teacherIndex => $data) {
            $teacher = $this->seedTeacher($data, $password, $notice, $proPackage);

            foreach ([0, 1] as $slot) {
                $subject = self::SUBJECTS[($teacherIndex * 2 + $slot) % count(self::SUBJECTS)];
                $groups[] = $this->seedGroup($teacher, $subject, $slot === 0 ? 'A' : 'B');
            }
        }

        $this->seedStudents($groups, $password, $notice);

        $this->command?->info(
            sprintf(
                'Datos orgánicos: %d docentes, %d grupos y %d alumnos (contraseña %s).',
                count(self::TEACHERS),
                count($groups),
                self::STUDENT_COUNT,
                self::DEMO_PASSWORD,
            ),
        );
    }

    /**
     * @param  array{first_name: string, last_name: string, phone: string}  $data
     */
    private function seedTeacher(
        array $data,
        string $password,
        ?PrivacyNotice $notice,
        ?SubscriptionPackage $package,
    ): User {
        $surname = Str::before($data['last_name'], ' ');
        $email = OrganicNames::emailSlug($data['first_name'].' '.$surname).'@docentes.acode-solutions.test';

        $teacher = User::firstOrCreate(
            ['email' => $email],
            [
                'role' => 'teacher',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
        );

        $this->acceptPrivacy($teacher, $notice);

        if ($package !== null) {
            TeacherSubscription::firstOrCreate(
                ['teacher_id' => $teacher->id],
                [
                    'subscription_package_id' => $package->id,
                    'payment_status' => 'paid',
                    'starts_at' => now()->subDays(10),
                    'expires_at' => now()->addYear(),
                ],
            );
        }

        return $teacher;
    }

    private function seedGroup(User $teacher, string $subject, string $section): Group
    {
        return Group::firstOrCreate(
            ['teacher_id' => $teacher->id, 'name' => "{$subject} - Grupo {$section}"],
            [
                'subject' => $subject,
                'access_folio' => $this->uniqueFolio(),
            ],
        );
    }

    /**
     * @param  list<Group>  $groups
     */
    private function seedStudents(array $groups, string $password, ?PrivacyNotice $notice): void
    {
        $usedEmails = [];

        for ($index = 0; $index < self::STUDENT_COUNT; $index++) {
            // Se arranca en 100 para no coincidir con las posiciones que usa
            // UserFactory al generar alumnos efímeros de las pruebas.
            $person = OrganicNames::person(100 + $index);
            $base = OrganicNames::emailSlug(
                $person['first_name'].' '.Str::before($person['last_name'], ' '),
            );

            $email = $base.'@alumnos.acode-solutions.test';
            if (isset($usedEmails[$email])) {
                $email = $base.'.'.($index + 1).'@alumnos.acode-solutions.test';
            }
            $usedEmails[$email] = true;

            $student = User::firstOrCreate(
                ['email' => $email],
                [
                    'role' => 'student',
                    'first_name' => $person['first_name'],
                    'last_name' => $person['last_name'],
                    // Teléfono opcional para alumnos: lo capturan uno de cada dos.
                    'phone' => $index % 2 === 0 ? $this->studentPhone($index) : null,
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $this->acceptPrivacy($student, $notice);

            // Reparto en rueda; cada quinto alumno cursa además otro grupo.
            $primary = $groups[$index % count($groups)];
            $primary->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);

            if ($index % 5 === 4) {
                $secondary = $groups[($index + 1) % count($groups)];
                $secondary->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);
            }
        }
    }

    /**
     * Teléfono de 10 dígitos (lada 55) determinista por posición.
     */
    private function studentPhone(int $index): string
    {
        return '55'.str_pad((string) (10000000 + $index * 37), 8, '0', STR_PAD_LEFT);
    }

    private function acceptPrivacy(User $user, ?PrivacyNotice $notice): void
    {
        if ($notice === null) {
            return;
        }

        $user->privacyAcceptances()->firstOrCreate(
            ['privacy_notice_id' => $notice->id],
            ['accepted_at' => now(), 'ip_address' => '127.0.0.1'],
        );
    }

    /**
     * Folio de acceso de 8 caracteres que no colisiona con otro grupo.
     */
    private function uniqueFolio(): string
    {
        do {
            $folio = strtoupper(Str::random(8));
        } while (Group::where('access_folio', $folio)->exists());

        return $folio;
    }
}
