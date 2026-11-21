{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/groups/create.blade.php
    Ubicación:   resources/views/groups/create.blade.php
    Descripción: Formulario institucional para crear un nuevo grupo escolar
                 con generación automática de código QR y folio alfanumérico
                 (MPW-V3 §7.3, §7.4.2, §8.1–§8.12; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Crear grupo escolar')

@section('content')
<div class="space-y-6">

    {{-- Encabezado Institucional --}}
    <x-page-header
        title="Crear grupo escolar"
        subtitle="Registra un nuevo grupo para inscribir estudiantes mediante código QR o folio alfanumérico."
        :breadcrumbs="[
            ['label' => 'Grupos', 'href' => route('groups.index')],
            ['label' => 'Crear grupo'],
        ]"
    >
        <x-slot:actions>
            <x-button :href="route('groups.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Formulario en tarjeta institucional --}}
    <x-card
        title="Información del grupo"
        subtitle="Define el nombre y la asignatura para habilitar el aula y generar automáticamente su folio de acceso."
    >
        <form
            id="create-group-form"
            method="POST"
            action="{{ route('groups.store') }}"
            class="space-y-6"
        >
            @csrf

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                {{-- Nombre del grupo --}}
                <x-form.group name="name" label="Nombre del grupo" required>
                    <x-form.input
                        name="name"
                        size="md"
                        value="{{ old('name') }}"
                        required
                        maxlength="120"
                        placeholder="Ej. Ingeniería de Software 5A"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                    />
                </x-form.group>

                {{-- Materia --}}
                <x-form.group name="subject" label="Materia (opcional)">
                    <x-form.input
                        name="subject"
                        size="md"
                        value="{{ old('subject') }}"
                        maxlength="120"
                        placeholder="Ej. Auditoría de Sistemas"
                        aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}"
                    />
                </x-form.group>
            </div>

            {{-- Aviso informativo de generación automática de QR/Folio --}}
            <div class="flex items-start gap-3 rounded-xl border border-acode-accent/30 bg-acode-accent/10 px-4 py-3.5 text-acode-primary">
                <x-icon name="qrCode" class="mt-0.5 h-5 w-5 shrink-0 text-acode-accent" />
                <p class="text-sm font-medium leading-relaxed">
                    El código QR y el folio alfanumérico de incorporación se generarán automáticamente al guardar el grupo.
                </p>
            </div>

            {{-- Pie de acciones con protección ante cambios sin guardar --}}
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <x-unsaved-changes
                    form-id="create-group-form"
                    :cancel-url="route('groups.index')"
                />

                <x-button type="submit" variant="primary" size="md">
                    <x-icon name="save" class="w-4 h-4" />
                    Guardar grupo
                </x-button>
            </div>
        </form>
    </x-card>

</div>
@endsection
