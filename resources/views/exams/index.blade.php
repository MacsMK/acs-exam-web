{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/index.blade.php (MPW-V2 §5.2.2, §7.3, §7.8.2, §8.4, §8.8, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Catálogo de Exámenes')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Catálogo de Exámenes</h1>
            <p class="text-sm text-slate-500 mt-0.5">Diseña, ensambla mediante sorteo algorítmico y publica evaluaciones docentes.</p>
        </div>

        <x-button :href="route('exams.create')" variant="primary" size="md">
            <x-icon name="add" class="w-4 h-4 mr-1.5" />
            Crear examen
        </x-button>
    </div>

    {{-- Barra de Búsqueda Predictiva (MPW-V2 §8.8) --}}
    <x-search-filter
        :action="route('exams.index')"
        placeholder="Buscar examen por título o descripción…"
    />

    <x-table>
        <x-slot:header>
            <x-table.th>Título de la Evaluación</x-table.th>
            <x-table.th align="center">Escala</x-table.th>
            <x-table.th>Ventana de Aplicación</x-table.th>
            <x-table.th align="center">Estado</x-table.th>
            <x-table.th align="right">Acciones</x-table.th>
        </x-slot:header>

        @forelse ($exams as $exam)
            <tr>
                <x-table.td>
                    <p class="font-bold text-slate-900 leading-snug">{{ $exam->title }}</p>
                    <p class="text-xs text-slate-400 mt-0.5 line-clamp-1">{{ $exam->description ?? 'Sin descripción adicional' }}</p>
                </x-table.td>
                <x-table.td align="center">
                    <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded bg-slate-100 text-slate-700">
                        {{ $exam->grading_scale }}
                    </span>
                </x-table.td>
                <x-table.td>
                    <div class="text-xs text-slate-600 flex flex-col gap-0.5">
                        <span class="flex items-center gap-1">
                            <span class="text-slate-400">Inicio:</span> {{ $exam->starts_at?->format('d/m/Y H:i') ?? 'Inmediato' }}
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="text-slate-400">Cierre:</span> {{ $exam->ends_at?->format('d/m/Y H:i') ?? 'Sin límite' }}
                        </span>
                    </div>
                </x-table.td>
                <x-table.td align="center">
                    @if ($exam->is_published)
                        <x-badge variant="accent">Publicado</x-badge>
                    @else
                        <x-badge variant="warning">Borrador</x-badge>
                    @endif
                </x-table.td>
                <x-table.td align="right">
                    <div class="flex items-center justify-end space-x-2">
                        @if ($exam->is_published)
                            <x-button :href="route('exams.monitor', $exam)" variant="primary" size="sm">
                                <x-icon name="clock" class="w-3.5 h-3.5 mr-1 text-emerald-300" />
                                Monitoreo
                            </x-button>
                        @endif
                        <x-button :href="route('exams.revision.index', $exam)" variant="ghost" size="sm">
                            <x-icon name="check" class="w-3.5 h-3.5 mr-1" />
                            Revisiones
                        </x-button>
                        <x-button :href="route('exams.assemble', $exam)" variant="secondary" size="sm">
                            <x-icon name="edit" class="w-3.5 h-3.5 mr-1" />
                            Editar
                        </x-button>
                    </div>
                </x-table.td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="p-0">
                    <x-empty-state
                        class="border-0 shadow-none rounded-none py-10"
                        icon="clock"
                        title="Sin exámenes registrados"
                        message="Todavía no has creado exámenes evaluadores. Comienza diseñando el primero."
                    >
                        <x-button :href="route('exams.create')" variant="primary" size="sm">
                            <x-icon name="add" class="w-4 h-4 mr-1.5" />
                            Crear examen
                        </x-button>
                    </x-empty-state>
                </td>
            </tr>
        @endforelse
    </x-table>
@endsection
