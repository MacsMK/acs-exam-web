{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/exams/student-index.blade.php
    Ubicación:   resources/views/exams/student-index.blade.php
    Descripción: Catálogo de exámenes asignados y disponibles para el alumno,
                 homologado al UI Kit A-Code (MPW-V3 §5.2.2, §7.3, §7.8.2; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Mis Exámenes Asignados')

@section('content')
@php
    $totalQuestionsCatalog = $exams->sum(fn ($exam) => $exam->sections->sum(
        fn ($s) => ($s->exam_questions_count ?? ($s->relationLoaded('examQuestions') ? $s->examQuestions->count() : 0)) ?: $s->requested_quantity
    ));
    $expiringSoonCount = $exams->filter(fn ($exam) => $exam->ends_at && $exam->ends_at->isFuture() && $exam->ends_at->diffInHours(now()) <= 48)->count();
@endphp

<div x-data="{ search: '' }" class="space-y-6">
    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        title="Mis Exámenes Asignados"
        subtitle="Evaluaciones programadas y publicadas por los docentes de tus grupos escolares."
    >
        <x-slot:actions>
            <x-button :href="route('student.grades.index')" variant="neutral" size="md">
                <x-nav-icon name="grades" class="w-4 h-4" />
                Abrir calificaciones
            </x-button>

            <x-button :href="route('student.groups.join.form')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4" />
                Unirme a un grupo
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Tarjetas KPI Resumen --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-kpi-card
            label="Exámenes disponibles"
            :value="$exams->count()"
            meta="Listos para presentar"
            nav-icon="exams"
            tone="tech"
        />

        <x-kpi-card
            label="Reactivos acumulados"
            :value="$totalQuestionsCatalog"
            meta="En evaluaciones vigentes"
            icon="document"
            tone="primary"
        />

        <x-kpi-card
            label="Próximos a vencer"
            :value="$expiringSoonCount"
            meta="Cierran en las próximas 48 h"
            icon="clock"
            :tone="$expiringSoonCount > 0 ? 'warning' : 'accent'"
        />
    </div>

    {{-- Nivel 3: Buscador rápido (cuando hay exámenes) --}}
    @if ($exams->isNotEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="w-full sm:max-w-md">
                    <x-form.input
                        id="student-exam-search"
                        type="search"
                        prefixIcon="search"
                        x-model="search"
                        placeholder="Buscar examen por título o descripción..."
                        aria-label="Buscar examen"
                    />
                </div>

                <template x-if="search.trim() !== ''">
                    <x-button type="button" variant="neutral" size="md" x-on:click="search = ''">
                        <x-icon name="filter" class="w-4 h-4 text-slate-500" />
                        Limpiar filtros
                    </x-button>
                </template>
            </div>
        </div>
    @endif

    {{-- Nivel 4: Grid de exámenes disponibles --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($exams as $exam)
            @php
                $examQuestionsTotal = $exam->sections->sum(fn ($s) => ($s->exam_questions_count ?? ($s->relationLoaded('examQuestions') ? $s->examQuestions->count() : 0)) ?: $s->requested_quantity);
                $examHaystack = mb_strtolower($exam->title . ' ' . ($exam->description ?? ''));
            @endphp
            <article
                x-show="!search.trim() || @js($examHaystack).includes(search.trim().toLowerCase())"
                class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-acode-tech hover:shadow-md"
            >
                <div>
                    {{-- Encabezado de la tarjeta --}}
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                            <x-nav-icon name="exams" class="h-6 w-6" />
                        </div>

                        <x-badge variant="accent">Disponible</x-badge>
                    </div>

                    {{-- Título y descripción --}}
                    <h3 class="text-base font-bold text-acode-primary leading-snug line-clamp-2">
                        {{ $exam->title }}
                    </h3>

                    <p class="mt-2 text-sm text-slate-600 line-clamp-2">
                        {{ $exam->description ?? 'Evaluación institucional contrarreloj.' }}
                    </p>

                    {{-- Metadatos del examen --}}
                    <div class="mt-4 space-y-2 border-t border-slate-100 py-3 text-xs text-slate-600">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Preguntas:</span>
                            <span class="font-semibold text-acode-primary">
                                {{ $examQuestionsTotal }} reactivos
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Tiempo máximo:</span>
                            <span class="font-mono font-medium text-slate-700">
                                {{ $exam->maxDurationMinutes() }} minutos
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Fecha límite:</span>
                            <span class="font-mono font-medium text-slate-700">
                                {{ $exam->ends_at?->format('d/m/Y H:i') ?? 'Sin vencimiento' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Acciones de la tarjeta --}}
                <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
                    <x-button :href="route('student.exams.instructions', $exam)" variant="neutral" size="md" class="shrink-0" title="Ver instrucciones previas">
                        <x-icon name="view" class="w-4 h-4" />
                        Detalles
                    </x-button>

                    <form method="POST" action="{{ route('attempts.start', $exam) }}" class="flex-1">
                        @csrf
                        <x-button type="submit" variant="primary" size="md" class="w-full">
                            <x-icon name="clock" class="w-4 h-4" />
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
                    <x-button :href="route('student.groups.join.form')" variant="primary" size="md">
                        <x-icon name="add" class="w-4 h-4" />
                        Unirme a un grupo
                    </x-button>
                </x-empty-state>
            </div>
        @endforelse
    </div>
</div>
@endsection
