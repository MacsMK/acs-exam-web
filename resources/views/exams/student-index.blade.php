{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/exams/student-index.blade.php
    Descripción: Catálogo de exámenes asignados y disponibles para el alumno.
    Estándares:  MPW-V2 §2.4, §5.2.2, §7.3, §7.8.2, Tabla 20 (Verbos oficiales).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Mis Exámenes Asignados')

@section('content')
<div class="space-y-6">
    {{-- Encabezado de la vista --}}
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">
                Mis Exámenes Asignados
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Evaluaciones programadas y publicadas por los docentes de tus grupos escolares.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <x-button :href="route('student.groups.join.form')" variant="neutral" size="md">
                <x-icon name="add" class="h-4 w-4 mr-1.5" />
                Unirme a un grupo
            </x-button>
        </div>
    </div>

    {{-- Grid de exámenes disponibles --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($exams as $exam)
            <article class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                <div>
                    {{-- Encabezado de la tarjeta --}}
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                            <x-nav-icon name="exams" class="h-6 w-6" />
                        </div>

                        <x-badge variant="accent">Disponible</x-badge>
                    </div>

                    {{-- Título y descripción --}}
                    <h3 class="font-bold text-base text-acode-primary leading-snug line-clamp-2">
                        {{ $exam->title }}
                    </h3>

                    <p class="mt-2 text-xs text-slate-500 line-clamp-2">
                        {{ $exam->description ?? 'Evaluación institucional contrarreloj.' }}
                    </p>

                    {{-- Metadatos del examen --}}
                    <div class="mt-4 space-y-2 border-t border-slate-100 py-3 text-xs text-slate-600">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Preguntas:</span>
                            <span class="font-semibold text-slate-700">
                                {{ $exam->sections->sum(fn ($s) => ($s->exam_questions_count ?? ($s->relationLoaded('examQuestions') ? $s->examQuestions->count() : 0)) ?: $s->requested_quantity) }} reactivos
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Tiempo límite:</span>
                            <span class="font-mono text-slate-700 font-medium">
                                {{ $exam->total_duration_minutes ?? $exam->duration_minutes ?? 60 }} minutos
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

                {{-- Botón de acción primario --}}
                <div class="pt-4 border-t border-slate-100">
                    <form method="POST" action="{{ route('attempts.start', $exam) }}">
                        @csrf
                        <x-button type="submit" variant="primary" size="md" class="w-full">
                            <x-icon name="clock" class="w-4 h-4 mr-1.5" />
                            Iniciar examen
                        </x-button>
                    </form>
                </div>
            </article>
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
</div>
@endsection
