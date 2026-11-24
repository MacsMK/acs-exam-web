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

@section('title', 'Crear grupo')

@section('content')
@php
    $backUrl = $catalogQuery === null ? route('groups.index') : route('teacher.gradebook.hub', $catalogQuery);
@endphp
<div class="space-y-6">

    {{-- Encabezado Institucional --}}
    <x-page-header
        :institutional="true"
        title="Crear grupo"
        :breadcrumbs="[
            ['label' => $catalogQuery === null ? 'Grupos' : 'Calificaciones', 'href' => $backUrl],
            ['label' => 'Crear grupo'],
        ]"
    >
        <x-slot:actions>
            <x-button :href="$backUrl" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                {{ $catalogQuery === null ? 'Volver' : 'Volver a calificaciones' }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Formulario en tarjeta institucional --}}
    <x-card>
        <form
            id="create-group-form"
            method="POST"
            action="{{ route('groups.store', $catalogContext) }}"
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

            {{-- Descripción opcional (RF-GRU-001) --}}
            <x-form.group name="description" label="Descripción (opcional)" help-text="Hasta 500 caracteres.">
                <x-form.textarea
                    name="description"
                    rows="3"
                    maxlength="500"
                    placeholder="Ej. Grupo del turno matutino, semestre agosto–diciembre"
                    aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}"
                >{{ old('description') }}</x-form.textarea>
            </x-form.group>

            {{-- Aviso informativo de generación automática de QR/Folio --}}
            <div class="flex items-start gap-3 rounded-xl border border-acode-accent/30 bg-acode-accent/10 px-4 py-3.5 text-acode-primary">
                <x-icon name="qrCode" class="mt-0.5 h-5 w-5 shrink-0 text-acode-accent" />
                <p class="text-sm font-medium leading-relaxed">
                    Al crear el grupo podrás compartir su código QR o su folio para inscribir estudiantes.
                </p>
            </div>

            {{-- Pie de acciones con protección ante cambios sin guardar --}}
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <x-unsaved-changes
                    form-id="create-group-form"
                    :cancel-url="$backUrl"
                />

                <x-button type="submit" variant="primary" size="md">
                    <x-icon name="save" class="w-4 h-4" />
                    Crear grupo
                </x-button>
            </div>
        </form>
    </x-card>

</div>
@endsection
