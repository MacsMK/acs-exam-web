{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: groups/index.blade.php (MPW-V2 §5.2.2, §7.8) --}}
@extends('layouts.app')

@section('title', 'Mis grupos')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-acode-primary">Mis grupos</h1>
        <x-button :href="route('groups.create')" variant="primary">
            Crear grupo
        </x-button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
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
                @forelse ($groups as $group)
                    <tr class="even:bg-acode-surface hover:bg-acode-tech/5">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $group->name }}</td>
                        <td class="px-4 py-3">{{ $group->subject ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono">{{ $group->access_folio }}</td>
                        <td class="px-4 py-3">{{ $group->students_count }}</td>
                        <td class="px-4 py-3">
                            @if ($group->is_closed)
                                <x-badge variant="neutral">Cerrado</x-badge>
                            @else
                                <x-badge variant="accent">Activo</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('groups.show', $group) }}" class="text-acode-tech font-medium hover:underline">Ver detalle</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-0">
                            <x-empty-state
                                class="border-0 shadow-none rounded-none"
                                message="Todavía no tienes grupos. Crea el primero para poder asignarle exámenes."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
