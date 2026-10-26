@extends('layouts.app')

@section('title', 'Mis grupos')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-acode-primary">Mis grupos</h1>
        <a href="{{ route('groups.create') }}" class="h-10 px-4 inline-flex items-center rounded-md bg-acode-primary text-white text-sm font-semibold hover:bg-[#0e1f36]">
            + Nuevo grupo
        </a>
    </div>

    @if ($groups->isEmpty())
        <div class="bg-white rounded-lg border border-slate-200 p-8 text-center text-sm text-slate-500">
            Todavía no tienes grupos. Crea el primero para poder asignarle exámenes.
        </div>
    @else
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-acode-primary text-white text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Nombre</th>
                        <th class="text-left px-4 py-3">Materia</th>
                        <th class="text-left px-4 py-3">Folio</th>
                        <th class="text-left px-4 py-3">Alumnos</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group)
                        <tr class="even:bg-acode-surface hover:bg-blue-50/70">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $group->name }}</td>
                            <td class="px-4 py-3">{{ $group->subject ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono">{{ $group->access_folio }}</td>
                            <td class="px-4 py-3">{{ $group->students_count }}</td>
                            <td class="px-4 py-3">
                                @if ($group->is_closed)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-200 text-slate-600">Cerrado</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-acode-accent/15 text-acode-accent">Activo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('groups.show', $group) }}" class="text-acode-tech font-medium">Ver →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
