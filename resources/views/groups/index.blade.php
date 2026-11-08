{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: groups/index.blade.php (MPW-V2 §5.2.2, §7.3, §7.8.2, §8.4, §8.8, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Grupos Escolares')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Grupos Escolares</h1>
            <p class="text-sm text-slate-500 mt-0.5">Administra tus salones y materias para matriculación de alumnos.</p>
        </div>

        <x-button :href="route('groups.create')" variant="primary" size="md">
            <x-icon name="add" class="w-4 h-4 mr-1.5" />
            Crear grupo
        </x-button>
    </div>

    {{-- Barra de Búsqueda y Filtrado (MPW-V2 §8.8) --}}
    <x-search-filter
        :action="route('groups.index')"
        placeholder="Buscar grupo por nombre o materia…"
    />

    <x-table>
        <x-slot:header>
            <x-table.th>Nombre del Grupo</x-table.th>
            <x-table.th>Materia / Asignatura</x-table.th>
            <x-table.th align="center">Folio de Acceso</x-table.th>
            <x-table.th align="center">Alumnos</x-table.th>
            <x-table.th align="center">Estado</x-table.th>
            <x-table.th align="right">Acciones</x-table.th>
        </x-slot:header>

        @forelse ($groups as $group)
            <tr>
                <x-table.td>
                    <span class="font-bold text-slate-800">{{ $group->name }}</span>
                </x-table.td>
                <x-table.td>
                    <span class="text-slate-600">{{ $group->subject ?? '—' }}</span>
                </x-table.td>
                <x-table.td align="center">
                    <span class="font-mono text-xs font-semibold bg-acode-surface px-2.5 py-1 rounded-md border border-slate-200 text-acode-primary select-all">
                        {{ $group->access_folio }}
                    </span>
                </x-table.td>
                <x-table.td align="center">
                    <span class="font-semibold text-slate-700">{{ $group->students_count }}</span>
                </x-table.td>
                <x-table.td align="center">
                    @if ($group->is_closed)
                        <x-badge variant="neutral">Cerrado</x-badge>
                    @else
                        <x-badge variant="accent">Activo</x-badge>
                    @endif
                </x-table.td>
                <x-table.td align="right">
                    <x-button :href="route('groups.show', $group)" variant="secondary" size="sm">
                        <x-icon name="view" class="w-3.5 h-3.5 mr-1" />
                        Ver detalle
                    </x-button>
                </x-table.td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="p-0">
                    <x-empty-state
                        class="border-0 shadow-none rounded-none py-10"
                        icon="userGroup"
                        title="Sin grupos escolares"
                        message="Todavía no tienes grupos asignados. Da de alta tu primer grupo escolar."
                    >
                        <x-button :href="route('groups.create')" variant="primary" size="sm">
                            <x-icon name="add" class="w-4 h-4 mr-1.5" />
                            Crear grupo
                        </x-button>
                    </x-empty-state>
                </td>
            </tr>
        @endforelse
    </x-table>
@endsection
