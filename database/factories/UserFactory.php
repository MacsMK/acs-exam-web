<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        UserFactory.php
 * Ubicación:      database/factories/UserFactory.php
 * Requerimiento:  N/A (soporte de pruebas; sin RF asociado)
 * Autor:          Calvo Sandoval Miguel Alexander (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 03/11/2026
 * Descripción:    Fábrica de acs_users. El modelo User declara HasFactory,
 *                 pero la carpeta database/factories estaba vacía, así que
 *                 User::factory() fallaba. Genera personas con nombres y
 *                 apellidos reales en español, correo único con dominio
 *                 .test (nunca envía correo real) y la contraseña de
 *                 demostración Demo1234.
 * ============================================================================
 */

namespace Database\Factories;

use App\Models\User;
use Database\Seeders\Support\OrganicNames;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Modelo al que pertenece la fábrica.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * Contador para que dos usuarios generados nunca compartan correo.
     */
    private static int $sequence = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = ++self::$sequence;
        $person = OrganicNames::person($sequence);
        $emailName = OrganicNames::emailSlug($person['first_name'].' '.$person['last_name']);

        return [
            'role' => 'student',
            'first_name' => $person['first_name'],
            'last_name' => $person['last_name'],
            'email' => "{$emailName}.{$sequence}@alumnos.acode-solutions.test",
            'phone' => null,
            'password' => 'Demo1234',
            'is_active' => true,
            'email_verified_at' => now(),
        ];
    }

    /**
     * Docente: teléfono obligatorio de 10 dígitos (RegisterTeacherRequest).
     */
    public function teacher(): static
    {
        return $this->state(fn (): array => [
            'role' => 'teacher',
            'phone' => '55'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Alumno (rol por defecto), explícito para legibilidad en las pruebas.
     */
    public function student(): static
    {
        return $this->state(fn (): array => ['role' => 'student']);
    }

    /**
     * Cuenta desactivada por el administrador.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Cuenta que aún no verifica su correo.
     */
    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }
}
