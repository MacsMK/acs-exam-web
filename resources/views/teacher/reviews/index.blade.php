@extends('layouts.app')

@section('title', 'Revisiones de Exámenes')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary">Centro de Revisiones Docentes</h1>
            <p class="text-sm text-slate-500 mt-1">
                Recoteja y ajuste calificaciones provisionales emitidas por la IA para reactivos abiertos y de programación.
            </p>
        </div>
    </div>

    @if ($exams->isEmpty())
        <x-card>
            <x-empty-state
                title="Sin exámenes registrados"
                description="Cuando cree exámenes y los alumnos comiencen a responder, aquí podrá revisar sus entregas y ajustar las ponderaciones de la IA."
            >
                <x-slot:action>
                    <x-button variant="primary" href="{{ route('exams.create') }}">
                        Crear examen
                    </x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($exams as $exam)
                <x-card class="flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $exam->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ $exam->is_active ? 'Activo' : 'Cerrado' }}
                            </span>
                            @if ($exam->pending_reviews_count > 0)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 animate-pulse">
                                    {{ $exam->pending_reviews_count }} por recotejar
                                </span>
                            @endif
                        </div>

                        <h3 class="text-lg font-bold text-acode-primary mb-2 line-clamp-2">
                            {{ $exam->title }}
                        </h3>

                        <div class="text-xs text-slate-500 space-y-1 mb-4">
                            <p><strong>Intentos registrados:</strong> {{ $exam->student_attempts_count }}</p>
                            <p><strong>Escala:</strong> {{ $exam->grading_scale }}</p>
                            <p><strong>Visibilidad:</strong> {{ match($exam->result_visibility_mode) {
                                'show_immediately' => 'Inmediata',
                                'estimated_score' => 'Calificación estimada',
                                'objective_only_pending' => 'Solo opción múltiple',
                                default => 'Oculto al alumno'
                            } }}</p>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
                        <span class="text-xs text-slate-400">
                            {{ $exam->created_at->format('d/m/Y') }}
                        </span>
                        <x-button variant="secondary" size="sm" href="{{ route('exams.revision.index', $exam) }}">
                            Revisar intentos
                        </x-button>
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $exams->links() }}
        </div>
    @endif
</div>
@endsection
