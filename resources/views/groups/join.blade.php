{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: groups/join.blade.php (MPW-V2 §7.3, §7.8.2, §8.5, Tabla 20, Tabla 28) --}}
@extends('layouts.app')

@section('title', 'Unirme a un Grupo Escolar')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Unirme a un Grupo Escolar</h1>
        <p class="text-sm text-slate-500 mt-1">Ingresa el folio de acceso provisto por tu profesor para inscribirte a su asignatura.</p>
    </div>

    <x-card class="max-w-md">
        <form method="POST" action="{{ route('student.groups.join') }}" class="space-y-5">
            @csrf

            <x-form.group
                name="access_folio"
                label="Folio alfanumérico de acceso"
                helpText="Solicita este código al docente titular de tu materia o clase."
                required
            >
                <x-form.input
                    type="text"
                    name="access_folio"
                    value="{{ old('access_folio', request('folio')) }}"
                    required
                    maxlength="12"
                    autofocus
                    placeholder="Ej. 7F3K9QRT"
                    prefixIcon="key"
                    class="font-mono text-base uppercase tracking-widest text-center"
                />
            </x-form.group>

            <div class="pt-2 flex items-center gap-3">
                <x-button type="submit" variant="primary" size="md" class="w-full">
                    <x-icon name="add" class="w-4 h-4 mr-1.5" />
                    Enviar solicitud
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
