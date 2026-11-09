@extends('layouts.app')

@section('title', 'Revisión: '.$exam->title)

@section('content')
@php
    $totalQuestions = $exam->sections->sum(
        fn ($section) => $section->examQuestions->count()
    );

    $totalPoints = $exam->sections->sum(
        fn ($section) => $section->examQuestions->sum(
            fn ($question) => (float) $question->points_weight
        )
    );

    $resultModes = [
        'final_score' => 'Calificación final',
        'estimated_score' => 'Calificación estimada',
        'objective_only_pending' => 'Preguntas objetivas, pendientes de revisión',
        'hidden' => 'Resultados ocultos',
    ];

    $hasSections = $exam->sections->isNotEmpty();
    $hasQuestions = $totalQuestions > 0;
    $hasGroups = $exam->groups->isNotEmpty();
    $hasHundredPoints = abs($totalPoints - 100) < 0.001;
@endphp

<div class="mx-auto max-w-5xl space-y-6">
    <a href="{{ route('exams.configuration', $exam) }}"
    class="inline-flex items-center gap-2 text-sm font-semibold text-acode-tech transition hover:underline">
        <span aria-hidden="true">←</span>
        Volver a Configuración
    </a>
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-acode-tech">
            Etapa 4 de 4 · Revisión
        </p>
        <h1 class="mt-1 text-2xl font-bold text-acode-primary">
            Revisar examen
        </h1>
        <p class="mt-2 text-sm text-slate-500">
            Comprueba la información del examen antes de su publicación.
        </p>
    </div>

    {{-- Etapas --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="grid grid-cols-4 gap-2">
            @foreach (['Datos', 'Preguntas', 'Configuración', 'Revisión'] as $index => $label)
                <div class="flex flex-col items-center gap-2 text-center">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold
                        {{ $index === 3
                            ? 'bg-acode-primary text-white'
                            : 'bg-acode-accent text-white' }}">
                        {{ $index < 3 ? '✓' : '4' }}
                    </span>
                    <span class="text-xs {{ $index === 3 ? 'font-semibold text-acode-primary' : 'text-slate-500' }}">
                        {{ $label }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Resumen general --}}
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-acode-primary">
                {{ $exam->title }}
            </h2>

            <span class="rounded-full px-3 py-1 text-xs font-semibold
                {{ $exam->is_published
                    ? 'bg-acode-accent/10 text-acode-accent'
                    : 'bg-acode-warning/20 text-acode-primary' }}">
                {{ $exam->is_published ? 'Publicado' : 'Borrador' }}
            </span>
        </div>

        <p class="mt-3 text-sm text-slate-600">
            {{ $exam->description ?: 'Sin descripción.' }}
        </p>

        <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ([
                ['Apartados', $exam->sections->count()],
                ['Reactivos', $totalQuestions],
                ['Puntos', number_format($totalPoints, 2)],
                ['Grupos', $exam->groups->count()],
            ] as [$label, $value])
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-xs text-slate-500">{{ $label }}</p>
                    <p class="mt-1 text-xl font-bold text-acode-primary">
                        {{ $value }}
                    </p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Comprobaciones preliminares --}}
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-acode-primary">
            Comprobaciones del examen
        </h2>

        <p class="mt-2 text-sm text-slate-500">
            Estas comprobaciones son informativas y todavía no sustituyen
            la validación completa de publicación.
        </p>

        <div class="mt-5 space-y-3">
            @foreach ([
                ['Tiene apartados', $hasSections],
                ['Tiene reactivos', $hasQuestions],
                ['Tiene grupos asignados', $hasGroups],
                ['La ponderación suma 100 puntos', $hasHundredPoints],
            ] as [$label, $passed])
                <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 p-3">
                    <span class="text-sm text-slate-700">{{ $label }}</span>

                    <span class="text-xs font-semibold {{ $passed ? 'text-acode-accent' : 'text-[#DC2626]' }}">
                        {{ $passed ? 'Cumple' : 'Pendiente' }}
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Configuración --}}
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-acode-primary">
                Configuración
            </h2>

            @unless ($exam->is_published)
                <a href="{{ route('exams.configuration', $exam) }}"
                   class="text-sm font-semibold text-acode-tech hover:underline">
                    Editar
                </a>
            @endunless
        </div>

        <dl class="mt-5 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-slate-500">Escala de calificación</dt>
                <dd class="mt-1 font-semibold text-acode-primary">
                    {{ $exam->grading_scale }}
                </dd>
            </div>

            <div>
                <dt class="text-slate-500">Resultados</dt>
                <dd class="mt-1 font-semibold text-acode-primary">
                    {{ $resultModes[$exam->result_visibility_mode] ?? 'No definido' }}
                </dd>
            </div>

            <div>
                <dt class="text-slate-500">Orden de preguntas</dt>
                <dd class="mt-1 font-semibold text-acode-primary">
                    {{ $exam->randomize_question_order ? 'Aleatorio' : 'Fijo' }}
                </dd>
            </div>

            <div>
                <dt class="text-slate-500">Cámara web</dt>
                <dd class="mt-1 font-semibold text-acode-primary">
                    {{ $exam->require_webcam ? 'Solicitada' : 'No solicitada' }}
                </dd>
            </div>

            <div>
                <dt class="text-slate-500">Máximo de incidencias</dt>
                <dd class="mt-1 font-semibold text-acode-primary">
                    {{ $exam->max_allowed_incidents }}
                </dd>
            </div>

            <div>
                <dt class="text-slate-500">Grupos asignados</dt>
                <dd class="mt-1 font-semibold text-acode-primary">
                    {{ $exam->groups->pluck('name')->join(', ') ?: 'Sin grupos' }}
                </dd>
            </div>

            <div>
                <dt class="text-slate-500">Inicio registrado</dt>
                <dd class="mt-1 font-semibold text-acode-primary">
                    {{ $exam->starts_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                </dd>
            </div>

            <div>
                <dt class="text-slate-500">Término registrado</dt>
                <dd class="mt-1 font-semibold text-acode-primary">
                    {{ $exam->ends_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                </dd>
            </div>
        </dl>

        <p class="mt-4 text-xs text-slate-500">
            Los horarios se muestran según la interpretación actual de Laravel.
            La conversión de zona horaria está pendiente de validación.
        </p>
    </section>

    {{-- Apartados y preguntas --}}
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-acode-primary">
                Apartados y reactivos
            </h2>

            @unless ($exam->is_published)
                <a href="{{ route('exams.assemble', $exam) }}"
                   class="text-sm font-semibold text-acode-tech hover:underline">
                    Editar
                </a>
            @endunless
        </div>

        @forelse ($exam->sections as $section)
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-5 py-4">
                    <h3 class="font-semibold text-acode-primary">
                        {{ $section->title }}
                    </h3>

                    <span class="text-xs text-slate-500">
                        {{ $section->examQuestions->count() }} reactivos
                    </span>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse ($section->examQuestions as $examQuestion)
                        <div class="flex items-start justify-between gap-4 px-5 py-3">
                            <p class="text-sm text-slate-700">
                                {{ $loop->iteration }}.
                                {{ $examQuestion->question?->title ?? 'Reactivo no disponible' }}
                            </p>

                            <span class="shrink-0 text-xs font-medium text-acode-tech">
                                {{ $examQuestion->points_weight }} puntos
                            </span>
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-slate-500">
                            Apartado sin reactivos.
                        </p>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <p class="text-sm text-slate-500">
                    Este examen todavía no tiene apartados.
                </p>
            </div>
        @endforelse
    </section>

    {{-- Publicación pendiente --}}
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-acode-primary">
            Publicación del examen
        </h2>

        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            La publicación estará disponible cuando terminemos de implementar
            y verificar todas las condiciones necesarias para aplicar el examen.
        </p>

        @unless ($exam->is_published)
            <button type="button" disabled
                    class="mt-4 cursor-not-allowed rounded-lg bg-slate-200 px-5 py-3 text-sm font-semibold text-slate-500">
                Publicar examen · Pendiente
            </button>
        @endunless
    </section>

    <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-between">
        <a href="{{ route('exams.configuration', $exam) }}"
           class="rounded-lg border border-slate-300 px-5 py-3 text-center text-sm font-semibold text-acode-primary hover:bg-slate-50">
            Volver a Configuración
        </a>

        <a href="{{ route('exams.index') }}"
           class="rounded-lg bg-acode-primary px-5 py-3 text-center text-sm font-semibold text-white hover:opacity-90">
            Guardar borrador y salir
        </a>
    </div>
</div>
@endsection