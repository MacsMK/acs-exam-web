@extends('layouts.app')

@section('title', 'Nuevo grupo')

@section('content')
    <h1 class="text-2xl font-bold text-acode-primary mb-6">Nuevo grupo</h1>

    <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-lg">
        <form method="POST" action="{{ route('groups.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Nombre del grupo</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120"
                       placeholder="Ej. Ingeniería de Software 5A"
                       class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>

            <div>
                <label class="block text-[0.8125rem] font-medium text-slate-800 mb-1">Materia (opcional)</label>
                <input type="text" name="subject" value="{{ old('subject') }}" maxlength="120"
                       placeholder="Ej. Auditoría de Sistemas"
                       class="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech">
            </div>

            <p class="text-xs text-slate-500">
                Al crear el grupo se genera automáticamente un folio de acceso corto para que tus alumnos se unan.
            </p>

            <button type="submit" class="h-10 px-4 rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
                Crear grupo
            </button>
        </form>
    </div>
@endsection
