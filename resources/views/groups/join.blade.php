{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/groups/join.blade.php
    Ubicación:   resources/views/groups/join.blade.php
    Descripción: Formulario institucional para que el alumno se inscriba a un grupo
                 mediante folio alfanumérico (MPW-V3 §7.3, §7.8.2, §8.5; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Unirme a un Grupo Escolar')

@section('content')
<div class="space-y-6">
    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        title="Unirme a un Grupo Escolar"
        subtitle="Ingresa el folio alfanumérico proporcionado por tu profesor para inscribirte a su asignatura."
        :breadcrumbs="[
            ['label' => 'Mis Grupos', 'href' => route('student.groups.index')],
            ['label' => 'Unirme a un grupo'],
        ]"
    >
        <x-slot:actions>
            <x-button :href="route('student.groups.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Tarjeta de captura focalizada --}}
    <div class="mx-auto max-w-xl">
        <x-card padding="p-6 sm:p-8">
            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-acode-surface text-acode-tech">
                <x-nav-icon name="groups" class="h-7 w-7" />
            </div>

            <div class="mb-6 text-center">
                <h2 class="text-lg font-bold text-acode-primary tracking-tight">
                    Código de inscripción de grupo
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Escribe el folio exactamente como te lo compartió tu docente titular.
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
                    <x-form.input
                        name="access_folio"
                        size="lg"
                        x-model="folio"
                        x-on:input="folio = folio.toUpperCase()"
                        required
                        maxlength="12"
                        autofocus
                        placeholder="Ej. 7F3K9QRT"
                        class="mt-1 text-center font-mono font-bold uppercase tracking-widest text-acode-primary"
                    />
                </x-form.group>

                <div class="flex items-start gap-3 rounded-xl border border-acode-tech/20 bg-acode-surface/60 p-4 text-xs text-slate-600">
                    <x-icon name="key" class="mt-0.5 h-5 w-5 shrink-0 text-acode-tech" />
                    <p class="leading-relaxed">
                        Cada grupo cuenta con una clave única de inscripción. Al enviar la solicitud quedarás registrado en la lista oficial y tendrás acceso a sus exámenes programados.
                    </p>
                </div>

                <div class="flex flex-col-reverse sm:flex-row items-center gap-3 pt-2">
                    <x-button :href="route('dashboard')" variant="neutral" size="md" class="w-full sm:flex-1">
                        Cancelar operación
                    </x-button>

                    <x-button type="submit" variant="primary" size="md" class="w-full sm:flex-1">
                        <x-icon name="add" class="w-4 h-4" />
                        Enviar solicitud
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
</div>
@endsection
