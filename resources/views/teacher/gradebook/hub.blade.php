@extends('layouts.app')

@section('title', 'Sábanas de Calificaciones')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary">Sábanas y Periodos de Calificación</h1>
            <p class="text-sm text-slate-500 mt-1">
                Consulte y capture calificaciones ponderadas por grupo, gestione parciales y exporte a Excel.
            </p>
        </div>
    </div>

    @if ($groups->isEmpty())
        <x-card>
            <x-empty-state
                title="Sin grupos escolares registrados"
                description="Cree un grupo escolar para definir esquemas evaluativos, asociar exámenes y capturar calificaciones."
            >
                <x-slot:action>
                    <x-button variant="primary" href="{{ route('groups.create') }}">
                        Crear grupo
                    </x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($groups as $group)
                <x-card class="flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $group->is_closed ? 'bg-slate-100 text-slate-600' : 'bg-emerald-100 text-emerald-800' }}">
                                {{ $group->is_closed ? 'Semestre cerrado' : 'Grupo activo' }}
                            </span>
                            <span class="text-xs font-mono bg-acode-surface px-2 py-0.5 rounded text-acode-primary font-semibold">
                                Folio: {{ $group->folio }}
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-acode-primary mb-1">
                            {{ $group->name }}
                        </h3>
                        <p class="text-xs text-slate-500 mb-4">
                            Materia: {{ $group->subject ?? 'Sin materia asignada' }}
                        </p>

                        <div class="bg-slate-50 rounded-lg p-3 text-xs text-slate-600 space-y-1 mb-4">
                            <div class="flex justify-between">
                                <span>Alumnos inscritos:</span>
                                <span class="font-semibold text-slate-800">{{ $group->students_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Periodos configurados:</span>
                                <span class="font-semibold text-slate-800">{{ $group->evaluation_periods_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Exámenes aplicados:</span>
                                <span class="font-semibold text-slate-800">{{ $group->exams_count }}</span>
                            </div>
                        </div>

                        {{-- Lista de periodos del grupo --}}
                        <div class="space-y-1.5 mb-4">
                            <p class="text-xs font-semibold text-slate-700">Periodos evaluativos:</p>
                            @forelse ($group->evaluationPeriods as $period)
                                <div class="flex items-center justify-between text-xs py-1 px-2 rounded bg-white border border-slate-100 hover:border-acode-tech/40 transition">
                                    <span class="font-medium text-slate-700 truncate max-w-[140px]">{{ $period->name }}</span>
                                    <a href="{{ route('groups.periods.gradebook', [$group, $period]) }}" class="text-acode-tech hover:underline font-semibold shrink-0">
                                        Abrir sábana →
                                    </a>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 italic">No hay periodos creados aún.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                        <x-button variant="neutral" size="sm" href="{{ route('groups.show', $group) }}">
                            Ver grupo
                        </x-button>
                        <x-button variant="secondary" size="sm" href="{{ route('groups.periods.index', $group) }}">
                            Configurar periodos
                        </x-button>
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $groups->links() }}
        </div>
    @endif
</div>
@endsection
