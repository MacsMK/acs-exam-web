<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        CreateSuperAdminCommand.php
 * Ubicación:      app/Console/Commands/CreateSuperAdminCommand.php
 * Requerimiento:  RF-AUT-010 (Creación restringida de superadministradores)
 * Actividad:      ACT-WEB-049
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Procedimiento técnico restringido para dar de alta cuentas
 *                 de superadministrador. Solo puede ejecutarlo el personal
 *                 con acceso a la consola del servidor; no existe registro
 *                 público para este rol. La contraseña se captura de forma
 *                 oculta y la operación queda en la bitácora de auditoría.
 *
 *                 Uso:  php artisan acs:crear-superadmin
 * ============================================================================
 */

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'acs:crear-superadmin';

    protected $description = 'Crea una cuenta de superadministrador (procedimiento restringido, RF-AUT-010).';

    public function handle(): int
    {
        $this->info('Alta de superadministrador (RF-AUT-010). Los datos se validan antes de guardar.');

        $data = [
            'first_name' => trim((string) $this->ask('Nombre(s)')),
            'last_name' => trim((string) $this->ask('Apellido(s)')),
            'email' => mb_strtolower(trim((string) $this->ask('Correo electrónico'))),
            'phone' => trim((string) $this->ask('Teléfono (10 dígitos)')),
            'password' => (string) $this->secret('Contraseña (mínimo 8 caracteres)'),
            'password_confirmation' => (string) $this->secret('Confirmar contraseña'),
        ];

        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:100', 'regex:/\A(?=.*\p{L})[\p{L}\p{M} ]+\z/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/\A(?=.*\p{L})[\p{L}\p{M} ]+\z/u'],
            'email' => ['required', 'email', 'max:255', 'unique:acs_users,email'],
            'phone' => ['required', 'regex:/^[0-9]{10}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'first_name.required' => 'El campo nombre es obligatorio',
            'first_name.regex' => 'El nombre no tiene un formato válido',
            'first_name.max' => 'El nombre no debe exceder los 100 caracteres',
            'last_name.required' => 'El campo apellido es obligatorio',
            'last_name.regex' => 'El apellido no tiene un formato válido',
            'last_name.max' => 'El apellido no debe exceder los 100 caracteres',
            'email.required' => 'El campo correo electrónico es obligatorio',
            'email.email' => 'El correo electrónico no tiene un formato válido',
            'email.unique' => 'El correo electrónico ya está registrado',
            'phone.required' => 'El campo teléfono es obligatorio',
            'phone.regex' => 'El teléfono debe contener 10 dígitos',
            'password.required' => 'El campo contraseña es obligatorio',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Los campos contraseña y confirmar contraseña deben coincidir',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            $this->error('No se creó la cuenta.');

            return self::FAILURE;
        }

        if (! $this->confirm("¿Crear la cuenta de superadministrador para {$data['email']}?", false)) {
            $this->warn('Operación cancelada. No se creó la cuenta.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'role' => 'super_admin',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => null,
                'action' => 'super_admin.created_by_console',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'new_values' => ['email' => $user->email, 'role' => 'super_admin'],
                'ip_address' => null,
                'user_agent' => 'artisan acs:crear-superadmin',
            ]);

            return $user;
        });

        $this->info("Superadministrador creado: {$user->email}. Al iniciar sesión recibirá el código de verificación en ese correo.");

        return self::SUCCESS;
    }
}
