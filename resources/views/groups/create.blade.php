{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: groups/create.blade.php (MPW-V2 §7.8) --}}
@extends('layouts.app')

@section('title', 'Crear grupo')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-6">Crear grupo</h1>

    <x-card class="max-w-lg">
        <form method="POST" action="{{ route('groups.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-medium text-slate-800 mb-1">Nombre del grupo</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120"
                       placeholder="Ej. Ingeniería de Software 5A"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-800 mb-1">Materia (opcional)</label>
                <input type="text" name="subject" value="{{ old('subject') }}" maxlength="120"
                       placeholder="Ej. Auditoría de Sistemas"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>

            <p class="text-xs text-slate-500">
                Al crear el grupo se genera automáticamente un folio de acceso corto para que tus alumnos se unan.
            </p>

            <x-button type="submit" variant="primary">
                Crear grupo
            </x-button>
        </form>
    </x-card>
@endsection
