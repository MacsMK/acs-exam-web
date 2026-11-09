{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/groups/join.blade.php
    Descripción: Formulario institucional para que el alumno se inscriba a un grupo.
    Estándares:  MPW-V2 §7.3, §7.8.2, §8.5, Tabla 20 (Verbos oficiales), Tabla 28.
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Unirme a un Grupo Escolar')

@section('content')
<div class="max-w-xl mx-auto py-4 space-y-6">
    {{-- Encabezado con navegación de regreso --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-acode-tech hover:underline">
            <x-icon name="arrowLeft" class="h-4 w-4" />
            <span>Volver al inicio</span>
        </a>
    </div>

    {{-- Tarjeta principal centrada --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        {{-- Icono institucional --}}
        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-acode-surface text-acode-tech">
            <x-nav-icon name="groups" class="h-7 w-7" />
        </div>

        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">
                Unirme a un Grupo Escolar
            </h1>
            <p class="mt-2 text-sm text-slate-500 leading-relaxed">
                Ingresa el folio de acceso provisto por tu profesor para inscribirte a su asignatura.
            </p>
        </div>

        <form method="POST" action="{{ route('student.groups.join') }}" class="space-y-6" x-data="{ folio: '{{ old('access_folio', request('folio', '')) }}' }">
            @csrf

            <x-form.group
                name="access_folio"
                label="Folio alfanumérico de acceso"
                helpText="Solicita este código al docente titular de tu materia o clase."
                required
            >
                <div class="relative mt-1">
                    <input
                        type="text"
                        name="access_folio"
                        id="access_folio"
                        x-model="folio"
                        x-on:input="folio = folio.toUpperCase()"
                        required
                        maxlength="12"
                        autofocus
                        placeholder="Ej. 7F3K9QRT"
                        class="h-12 w-full rounded-xl border border-slate-300 px-4 text-center font-mono text-lg font-bold uppercase tracking-widest text-acode-primary shadow-sm outline-none transition focus:border-acode-tech focus:ring-2 focus:ring-acode-tech/20"
                    />
                </div>
            </x-form.group>

            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-xs text-slate-500 flex items-start gap-3">
                <x-icon name="key" class="h-5 w-5 text-acode-tech shrink-0 mt-0.5" />
                <p class="leading-relaxed">
                    Cada grupo cuenta con una clave única de inscripción. Al ingresar serás asignado a la lista oficial y tendrás acceso a sus exámenes programados.
                </p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-button :href="route('dashboard')" variant="neutral" size="md" class="flex-1">
                    Cancelar operación
                </x-button>

                <x-button type="submit" variant="primary" size="md" class="flex-1">
                    <x-icon name="add" class="w-4 h-4 mr-1.5" />
                    Enviar solicitud
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection
