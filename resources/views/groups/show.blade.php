{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: groups/show.blade.php (MPW-V2 §5.2.2, §7.8) --}}
@extends('layouts.app')

@section('title', $group->name)

@section('content')
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-acode-primary">{{ $group->name }}</h1>
        @if ($group->is_closed)
            <x-badge variant="neutral">Cerrado</x-badge>
        @else
            <x-badge variant="accent">Activo</x-badge>
        @endif
    </div>
    <p class="text-sm text-slate-500 mb-6">{{ $group->subject ?? 'Sin materia asignada' }}</p>

    <x-card class="mb-6 max-w-lg">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Folio de acceso</p>
        <p class="font-mono text-2xl text-acode-primary tracking-widest">{{ $group->access_folio }}</p>
        <p class="text-xs text-slate-500 mt-2">Tus alumnos usan este folio para unirse al grupo desde "Unirme a un grupo".</p>
    </x-card>

    <h2 class="text-lg font-semibold text-acode-primary mb-3">Alumnos inscritos ({{ $group->students_count ?? $group->students->count() }})</h2>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-acode-primary text-white text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-3">Nombre</th>
                    <th class="text-left px-4 py-3">Correo</th>
                    <th class="text-left px-4 py-3">Se unió</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($group->students as $student)
                    <tr class="even:bg-acode-surface">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $student->full_name }}</td>
                        <td class="px-4 py-3">{{ $student->email }}</td>
                        <td class="px-4 py-3">{{ $student->pivot->joined_at }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="p-0">
                            <x-empty-state
                                class="border-0 shadow-none rounded-none"
                                message="Aún no se ha unido ningún alumno."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @unless ($group->is_closed)
        <form method="POST" action="{{ route('groups.close', $group) }}" onsubmit="return confirm('¿Cerrar este grupo? Es el cierre semestral y conserva su historial.');">
            @csrf
            <x-button type="submit" variant="danger">
                Cerrar grupo
            </x-button>
        </form>
    @endunless
@endsection
