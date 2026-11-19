{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: account/profile.blade.php (RF-AUT-013; MPW-V2 §7.3, §8.6, §8.7, §8.10.4) --}}
@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
<div class="space-y-6 max-w-3xl mx-auto">
    <div class="border-b border-slate-200 pb-4">
        <h1 class="text-2xl font-bold text-acode-primary">Mi perfil</h1>
        <p class="text-sm text-slate-500 mt-1">
            Consulta los datos de tu cuenta, el aviso de privacidad y cierra tu sesión.
        </p>
    </div>

    {{-- Datos de la cuenta --}}
    <x-card>
        <x-slot:title>Datos de la cuenta</x-slot:title>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                <dt class="text-xs text-slate-500">Nombre</dt>
                <dd class="mt-1 text-sm font-semibold text-acode-primary">{{ $user->full_name }}</dd>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                <dt class="text-xs text-slate-500">Correo electrónico</dt>
                <dd class="mt-1 text-sm font-semibold text-acode-primary break-all">{{ $user->email }}</dd>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                <dt class="text-xs text-slate-500">Tipo de cuenta</dt>
                <dd class="mt-1 text-sm font-semibold text-acode-primary">
                    {{ $user->role === 'super_admin' ? 'Administrador' : 'Estudiante' }}
                </dd>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                <dt class="text-xs text-slate-500">Fecha de registro</dt>
                <dd class="mt-1 text-sm font-semibold text-acode-primary">{{ $user->created_at?->format('d/m/Y') }}</dd>
            </div>
            @if ($user->phone)
                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                    <dt class="text-xs text-slate-500">Teléfono</dt>
                    <dd class="mt-1 text-sm font-semibold text-acode-primary">{{ $user->phone }}</dd>
                </div>
            @endif
        </dl>
    </x-card>

    {{-- Aviso de privacidad --}}
    <x-card>
        <x-slot:title>Aviso de privacidad</x-slot:title>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <p class="text-sm text-slate-600">
                @if ($acceptance)
                    Aceptaste la versión <strong class="text-acode-primary">{{ $acceptance->privacyNotice?->version }}</strong>
                    el {{ $acceptance->accepted_at?->format('d/m/Y \a \l\a\s H:i') }}.
                @else
                    No hay registro de aceptación del aviso de privacidad para esta cuenta.
                @endif
            </p>
            <x-button :href="route('privacy.show.public')" variant="neutral" size="sm" class="shrink-0 whitespace-nowrap">
                Abrir aviso de privacidad
            </x-button>
        </div>
    </x-card>

    {{-- Sesión --}}
    <x-card>
        <x-slot:title>Sesión</x-slot:title>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <p class="text-sm text-slate-600">
                Al cerrar sesión tendrás que volver a ingresar tu correo, contraseña y código de verificación.
            </p>
            <form
                method="POST"
                action="{{ route('auth.logout') }}"
                class="shrink-0"
                x-data
                x-on:submit.prevent="window.AcodeAlert.confirm({
                    title: '¿Cerrar sesión?',
                    text: 'Tendrás que volver a ingresar tu correo, contraseña y código de verificación.',
                    confirmButtonText: 'Cerrar sesión',
                    cancelButtonText: 'Cancelar',
                    isDanger: false,
                    onConfirm: () => $el.submit(),
                })"
            >
                @csrf
                <x-button type="submit" variant="primary" size="sm" class="shrink-0 whitespace-nowrap">
                    Cerrar sesión
                </x-button>
            </form>
        </div>
    </x-card>
</div>
@endsection
