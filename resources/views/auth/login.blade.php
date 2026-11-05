@extends('layouts.login')

@section('title', 'Iniciar sesión')

@section('content')
    <h2 class="mb-2 text-3xl font-bold tracking-tight text-acode-primary">
        Inicia sesión
    </h2>

    <p class="mb-8 text-sm text-slate-500">
        Accede a tu cuenta para continuar.
    </p>

    <form method="POST" action="{{ route('auth.login.store') }}" class="space-y-5">
        @csrf

        {{-- Correo electrónico --}}
        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-acode-primary">
                Correo electrónico
            </label>

            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="nombre@correo.com"
                autocomplete="username"
                required
                autofocus
                class="h-12 w-full rounded-lg border border-slate-300 px-4 text-sm text-slate-800 outline-none transition focus:border-acode-tech focus:ring-2 focus:ring-acode-tech/20"
            >
        </div>

        {{-- Contraseña --}}
        <div>
            <label for="password" class="mb-2 block text-sm font-semibold text-acode-primary">
                Contraseña
            </label>

            <div class="relative">
                <input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Ingresa tu contraseña"
                    autocomplete="current-password"
                    required
                    class="h-12 w-full rounded-lg border border-slate-300 px-4 pr-12 text-sm text-slate-800 outline-none transition focus:border-acode-tech focus:ring-2 focus:ring-acode-tech/20"
                >

                <button
                    type="button"
                    id="toggle-password"
                    aria-label="Mostrar contraseña"
                    aria-pressed="false"
                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-lg text-slate-500 transition hover:text-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech"
                >
                    <svg id="password-eye" xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Envío del formulario --}}
        <button
            type="submit"
            class="mt-2 h-12 w-full rounded-lg bg-acode-primary text-sm font-semibold text-white shadow-sm transition hover:bg-[#0e1f36] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech"
        >
            Iniciar sesión
        </button>
    </form>

    {{-- Accesos de registro --}}
    <p class="mt-8 text-center text-sm leading-relaxed text-slate-500">
        ¿Aún no tienes cuenta?
        <span class="inline-block whitespace-nowrap">
            <a href="{{ route('auth.register.teacher.show') }}" class="font-semibold text-acode-tech underline-offset-4 hover:underline focus-visible:underline">
                Docente
            </a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('auth.register.student.show') }}" class="font-semibold text-acode-tech underline-offset-4 hover:underline focus-visible:underline">
                Alumno
            </a>
        </span>
    </p>

    {{-- Visibilidad de la contraseña --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const password = document.getElementById('password');
            const toggle = document.getElementById('toggle-password');

            toggle.addEventListener('click', function () {
                const visible = password.type === 'password';

                password.type = visible ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', String(visible));
                toggle.setAttribute(
                    'aria-label',
                    visible ? 'Ocultar contraseña' : 'Mostrar contraseña'
                );
            });
        });
    </script>
@endsection
