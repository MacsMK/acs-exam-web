{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/student-index.blade.php (MPW-V2 §2.4, §5.2.2, §7.3, §7.8.2, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Mis Exámenes Asignados')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Mis Exámenes Asignados</h1>
        <p class="text-sm text-slate-500 mt-1">Evaluaciones programadas y publicadas por los docentes de tus grupos escolares.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($exams as $exam)
            <x-card class="flex flex-col justify-between hover:shadow-md transition border-slate-200">
                <div>
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="font-bold text-base text-acode-primary leading-snug">{{ $exam->title }}</h3>
                        <x-badge variant="accent">Disponible</x-badge>
                    </div>

                    <p class="text-xs text-slate-500 mb-4 line-clamp-2">
                        {{ $exam->description ?? 'Evaluación institucional contrarreloj.' }}
                    </p>

                    <div class="space-y-1.5 py-3 border-t border-slate-100 text-xs text-slate-600">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Preguntas:</span>
                            <span class="font-semibold text-slate-700">
                                {{ $exam->sections->sum(fn ($s) => ($s->exam_questions_count ?? ($s->relationLoaded('examQuestions') ? $s->examQuestions->count() : 0)) ?: $s->requested_quantity) }} reactivos
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Fecha límite:</span>
                            <span class="font-mono text-slate-700 font-medium">
                                {{ $exam->ends_at?->format('d/m/Y H:i') ?? 'Sin vencimiento' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <form method="POST" action="{{ route('attempts.start', $exam) }}">
                        @csrf
                        <x-button type="submit" variant="primary" size="md" class="w-full">
                            <x-icon name="clock" class="w-4 h-4 mr-1.5" />
                            Iniciar examen
                        </x-button>
                    </form>
                </div>
            </x-card>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-empty-state
                    icon="clock"
                    title="No tienes exámenes pendientes"
                    message="No se encontraron evaluaciones publicadas en tus asignaturas activas."
                >
                    <div class="text-xs text-slate-500">
                        Si tu profesor te compartió un folio escolar,
                        <a href="{{ route('student.groups.join.form') }}" class="text-acode-tech font-semibold hover:underline">
                            únete al grupo aquí
                        </a>.
                    </div>
                </x-empty-state>
            </div>
        @endforelse
    </div>
@endsection
