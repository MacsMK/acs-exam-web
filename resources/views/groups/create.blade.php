{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: groups/create.blade.php (MPW-V2 §7.3, §7.8.2, §8.5, Tabla 20, Tabla 28) --}}
@extends('layouts.app')

@section('title', 'Crear Grupo Escolar')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Crear Grupo Escolar</h1>
        <p class="text-sm text-slate-500 mt-1">Registra un nuevo grupo para asignar exámenes y enrolar alumnos mediante folio.</p>
    </div>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('groups.store') }}" class="space-y-5">
            @csrf

            <x-form.group name="name" label="Nombre del grupo escolar" required>
                <x-form.input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    maxlength="120"
                    placeholder="Ej. Ingeniería de Software 5A"
                    prefixIcon="userGroup"
                />
            </x-form.group>

            <x-form.group name="subject" label="Materia o asignatura (opcional)">
                <x-form.input
                    type="text"
                    name="subject"
                    value="{{ old('subject') }}"
                    maxlength="120"
                    placeholder="Ej. Auditoría de Sistemas y Seguridad"
                    prefixIcon="shield"
                />
            </x-form.group>

            <div class="p-3 bg-acode-surface rounded-xl border border-slate-200 text-xs text-slate-600 flex items-start gap-2.5">
                <x-icon name="key" class="w-4 h-4 text-acode-tech shrink-0 mt-0.5" />
                <p>
                    Al dar de alta este grupo se generará de manera automática un <strong>folio alfanumérico único</strong> y un <strong>código QR de acceso</strong> que podrás compartir con tus estudiantes.
                </p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-button type="submit" variant="primary" size="md">
                    <x-icon name="add" class="w-4 h-4 mr-1.5" />
                    Crear grupo
                </x-button>

                <x-button :href="route('groups.index')" variant="neutral" size="md">
                    Volver
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
