{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: auth/login.blade.php (MPW-V2 §7.3, §7.8.2, §8.5, §8.10.5, Tabla 20, Tabla 28) --}}
@extends('layouts.guest')

@section('title', 'Iniciar Sesión')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold text-acode-primary tracking-tight">Iniciar Sesión</h1>
        <p class="text-xs text-slate-500 mt-1">Ingresa tus credenciales oficiales para acceder a la plataforma.</p>
    </div>

    <form method="POST" action="{{ route('auth.login.store') }}" class="space-y-4">
        @csrf

        {{-- Correo Electrónico Institucional --}}
        <x-form.group name="email" label="Correo electrónico institucional" required>
            <x-form.input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                prefixIcon="user"
                placeholder="usuario@acode.solutions"
            />
        </x-form.group>

        {{-- Contraseña con alternador de visibilidad Alpine.js --}}
        <div x-data="{ showPass: false }">
            <x-form.group name="password" label="Contraseña" required>
                <div class="relative w-full">
                    <x-form.input
                        ::type="showPass ? 'text' : 'password'"
                        name="password"
                        required
                        prefixIcon="lock"
                        placeholder="••••••••••••"
                        class="pr-10"
                    />
                    <button
                        type="button"
                        @click="showPass = !showPass"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-acode-tech transition focus:outline-none"
                        tabindex="-1"
                        aria-label="Mostrar u ocultar contraseña"
                    >
                        <x-icon name="eye" class="w-5 h-5" x-show="!showPass" />
                        <x-icon name="eyeSlash" class="w-5 h-5" x-show="showPass" x-cloak />
                    </button>
                </div>
            </x-form.group>
        </div>

        {{-- Botón de Acción Normativo (Continuar) --}}
        <div class="pt-2">
            <x-button type="submit" variant="primary" size="md" class="w-full">
                Continuar
            </x-button>
        </div>
    </form>

    <div class="mt-6 pt-4 border-t border-slate-100 text-center text-xs text-slate-500">
        ¿Aún no te has registrado?
        <div class="mt-2 flex items-center justify-center gap-3 font-semibold">
            <a href="{{ route('auth.register.teacher.show') }}" class="text-acode-tech hover:underline flex items-center gap-1">
                <x-icon name="userTie" class="w-4 h-4" />
                <span>Registro Docente</span>
            </a>
            <span class="text-slate-300">|</span>
            <a href="{{ route('auth.register.student.show') }}" class="text-acode-tech hover:underline flex items-center gap-1">
                <x-icon name="user" class="w-4 h-4" />
                <span>Registro Alumno</span>
            </a>
        </div>
    </div>
@endsection
