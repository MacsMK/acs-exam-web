@extends('layouts.app')

@section('title', 'Mi Perfil')

@section('content')
<div class="space-y-6 max-w-3xl mx-auto">
    <div class="border-b border-slate-200 pb-4">
        <h1 class="text-2xl font-bold text-acode-primary">Configuración de Perfil Docente</h1>
        <p class="text-sm text-slate-500 mt-1">
            Administre sus datos personales, información de contacto y credenciales de acceso.
        </p>
    </div>

    <form method="POST" action="{{ route('teacher.profile.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Datos Generales --}}
        <x-card>
            <x-slot:title>Información Personal</x-slot:title>
            <x-slot:subtitle>Datos registrados para la emisión de reportes académicos</x-slot:subtitle>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <x-form.group label="Nombre" for="first_name" :error="$errors->first('first_name')" required>
                    <x-form.input id="first_name" name="first_name" :value="old('first_name', $user->first_name)" required />
                </x-form.group>

                <x-form.group label="Apellidos" for="last_name" :error="$errors->first('last_name')" required>
                    <x-form.input id="last_name" name="last_name" :value="old('last_name', $user->last_name)" required />
                </x-form.group>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <x-form.group label="Correo Electrónico" for="email">
                    <x-form.input id="email" name="email" :value="$user->email" disabled class="bg-slate-50 text-slate-500 cursor-not-allowed" />
                    <p class="text-[11px] text-slate-400 mt-1">El correo electrónico no puede modificarse por motivos de seguridad.</p>
                </x-form.group>

                <x-form.group label="Teléfono (10 dígitos)" for="phone" :error="$errors->first('phone')" required>
                    <x-form.input id="phone" name="phone" :value="old('phone', $user->phone)" maxlength="10" placeholder="5512345678" required />
                </x-form.group>
            </div>
        </x-card>

        {{-- Seguridad y Contraseña --}}
        <x-card>
            <x-slot:title>Seguridad y Credenciales</x-slot:title>
            <x-slot:subtitle>Cambie su contraseña de acceso o verifique su segundo factor</x-slot:subtitle>

            <div class="p-3 bg-acode-surface/50 rounded-lg border border-slate-200/60 mb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-acode-tech/10 rounded text-acode-tech">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-acode-primary">Autenticación de Dos Pasos (2FA)</p>
                        <p class="text-[11px] text-slate-500">Se solicita un código de 6 dígitos enviado a su correo en cada inicio de sesión.</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    Activo
                </span>
            </div>

            <div class="space-y-4">
                <x-form.group label="Contraseña Actual (solo si desea cambiarla)" for="current_password" :error="$errors->first('current_password')">
                    <x-form.input id="current_password" name="current_password" type="password" />
                </x-form.group>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-form.group label="Nueva Contraseña" for="new_password" :error="$errors->first('new_password')">
                        <x-form.input id="new_password" name="new_password" type="password" />
                    </x-form.group>

                    <x-form.group label="Confirmar Nueva Contraseña" for="new_password_confirmation">
                        <x-form.input id="new_password_confirmation" name="new_password_confirmation" type="password" />
                    </x-form.group>
                </div>
            </div>

            <x-slot:footer>
                <x-button variant="primary" type="submit">
                    Guardar cambios
                </x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
@endsection
