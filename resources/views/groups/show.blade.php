@extends('layouts.app')

@section('title', $group->name)

@section('content')
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-acode-primary">{{ $group->name }}</h1>
        @if ($group->is_closed)
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-200 text-slate-600">Cerrado</span>
        @else
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-acode-accent/15 text-acode-accent">Activo</span>
        @endif
    </div>
    <p class="text-sm text-slate-500 mb-6">{{ $group->subject ?? 'Sin materia asignada' }}</p>

    <div class="bg-white rounded-lg border border-slate-200 p-5 mb-6 max-w-lg">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Folio de acceso</p>
        <p class="font-mono text-2xl text-acode-primary tracking-widest">{{ $group->access_folio }}</p>
        <p class="text-xs text-slate-500 mt-2">Tus alumnos usan este folio para unirse al grupo desde "Unirme a un grupo".</p>
    </div>

    <h2 class="text-lg font-semibold text-acode-primary mb-3">Alumnos inscritos ({{ $group->students->count() }})</h2>

    @if ($group->students->isEmpty())
        <div class="bg-white rounded-lg border border-slate-200 p-6 text-sm text-slate-500">
            Aún no se ha unido ningún alumno.
        </div>
    @else
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-acode-primary text-white text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Nombre</th>
                        <th class="text-left px-4 py-3">Correo</th>
                        <th class="text-left px-4 py-3">Se unió</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group->students as $student)
                        <tr class="even:bg-acode-surface">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $student->full_name }}</td>
                            <td class="px-4 py-3">{{ $student->email }}</td>
                            <td class="px-4 py-3">{{ $student->pivot->joined_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @unless ($group->is_closed)
        <form method="POST" action="{{ route('groups.close', $group) }}" onsubmit="return confirm('¿Cerrar este grupo? Es el cierre semestral (RW-35) y conserva su historial.');">
            @csrf
            <button type="submit" class="h-10 px-4 rounded-md bg-acode-danger text-white text-sm font-semibold hover:opacity-90">
                Cerrar grupo
            </button>
        </form>
    @endunless
@endsection
