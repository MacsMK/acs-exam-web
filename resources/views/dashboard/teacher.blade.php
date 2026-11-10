{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/dashboard/teacher.blade.php
    Ubicación:   resources/views/dashboard/teacher.blade.php
    Descripción: Panel de control docente con arquitectura jerárquica de 4 niveles:
                 hero institucional y barra de acciones rápidas, tarjetas KPI
                 interactivas, analítica de distribución de calificaciones con
                 grupos recientes y catálogo operativo de exámenes (MPW-V2 §4.1.2,
                 §5.2.2, §7.2, §7.3, §7.8.2, §7.9, §8, Tabla 20).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Panel Docente')

@section('content')
<div class="space-y-8">

    {{-- Nivel 1: Hero Institucional + Barra de Acciones Rápidas (Above the Fold) --}}
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-5 rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 shadow-sm">
        <div class="max-w-2xl">
            <h1 class="text-2xl sm:text-3xl font-bold text-acode-primary tracking-tight">
                ¡Hola, {{ auth()->user()->first_name }}!
            </h1>
            <p class="mt-2 text-sm text-slate-500 leading-relaxed">
                Administra tus grupos escolares, alimenta tu banco de reactivos, configura exámenes y da seguimiento académico en tiempo real.
            </p>
        </div>

        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5 shrink-0">
            <x-button :href="route('groups.create')" variant="neutral" size="md">
                <x-icon name="add" class="w-4 h-4" />
                Crear grupo
            </x-button>

            <x-button :href="route('questions.create')" variant="neutral" size="md">
                <x-icon name="add" class="w-4 h-4" />
                Crear pregunta
            </x-button>

            <x-button :href="route('exams.create')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4" />
                Crear examen
            </x-button>
        </div>
    </div>

    {{-- Nivel 2: Grid de 4 Tarjetas KPI Interactivas --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">

        {{-- KPI 1: Mis grupos --}}
        <a href="{{ route('groups.index') }}"
           class="group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-acode-tech hover:shadow-md">
            <div class="flex items-center justify-between gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-acode-surface text-acode-tech transition-transform duration-200 group-hover:scale-105">
                    <x-nav-icon name="groups" class="h-6 w-6" />
                </div>
                <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-600 transition-colors duration-200 group-hover:text-acode-tech">
                    <span>Mis grupos</span>
                    <svg class="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-3xl lg:text-4xl font-extrabold text-acode-primary tracking-tight">
                    {{ $groupsCount }}
                </p>
            </div>
        </a>

        {{-- KPI 2: Banco de preguntas --}}
        <a href="{{ route('questions.index') }}"
           class="group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-acode-tech hover:shadow-md">
            <div class="flex items-center justify-between gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-acode-surface text-acode-tech transition-transform duration-200 group-hover:scale-105">
                    <x-nav-icon name="questions" class="h-6 w-6" />
                </div>
                <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-600 transition-colors duration-200 group-hover:text-acode-tech">
                    <span>Banco de preguntas</span>
                    <svg class="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-3xl lg:text-4xl font-extrabold text-acode-primary tracking-tight">
                    {{ $questionsCount }}
                </p>
            </div>
        </a>

        {{-- KPI 3: Exámenes creados --}}
        <a href="{{ route('exams.index') }}"
           class="group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md">
            <div class="flex items-center justify-between gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition-transform duration-200 group-hover:scale-105">
                    <x-nav-icon name="exams" class="h-6 w-6" />
                </div>
                <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-600 transition-colors duration-200 group-hover:text-amber-600">
                    <span>Exámenes creados</span>
                    <svg class="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-3xl lg:text-4xl font-extrabold text-acode-primary tracking-tight">
                    {{ $examsCount }}
                </p>
            </div>
        </a>

        {{-- KPI 4: Promedio general --}}
        <a href="{{ route('teacher.gradebook.hub') }}"
           class="group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
            <div class="flex items-center justify-between gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition-transform duration-200 group-hover:scale-105">
                    <x-nav-icon name="grades" class="h-6 w-6" />
                </div>
                <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-600 transition-colors duration-200 group-hover:text-emerald-600">
                    <span>Promedio general</span>
                    <svg class="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                <p class="font-mono text-3xl lg:text-4xl font-extrabold text-acode-primary tracking-tight">
                    {{ number_format((float) ($averageScore ?? 0), 1) }}
                </p>
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200/60">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ (int) ($passedCount ?? 0) }} aprobados
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 border border-rose-200/60">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                        {{ (int) ($failedCount ?? 0) }} reprobados
                    </span>
                </div>
            </div>
        </a>

    </div>

    {{-- Nivel 3: Bloque Operativo de 2 Columnas --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

        {{-- Columna Izquierda (7 cols): Rendimiento Académico y Accesos de Evaluación --}}
        <div class="lg:col-span-7 space-y-4">
            <x-analytics.group-chart
                :passed-count="$passedCount ?? 0"
                :failed-count="$failedCount ?? 0"
                title="Distribución de Aprobados y Calificaciones"
            />

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600">
                    <div>
                        <span class="font-semibold text-acode-primary">Total evaluados:</span>
                        <span class="font-mono font-bold text-slate-800">{{ (int) ($passedCount ?? 0) + (int) ($failedCount ?? 0) }}</span>
                    </div>
                    <span class="hidden sm:inline text-slate-300">|</span>
                    <div>
                        <span class="font-semibold text-acode-primary">Índice de aprobación:</span>
                        <span class="font-mono font-bold text-acode-accent">
                            {{ ((int) ($passedCount ?? 0) + (int) ($failedCount ?? 0)) > 0 ? round(((int) ($passedCount ?? 0) / ((int) ($passedCount ?? 0) + (int) ($failedCount ?? 0))) * 100) : 0 }}%
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-button :href="route('teacher.reviews.index')" variant="neutral" size="sm">
                        <x-icon name="view" class="w-4 h-4" />
                        Centro de revisión
                    </x-button>

                    <x-button :href="route('teacher.gradebook.hub')" variant="secondary" size="sm">
                        <x-icon name="reportBar" class="w-4 h-4" />
                        Control de calificaciones
                    </x-button>
                </div>
            </div>
        </div>

        {{-- Columna Derecha (5 cols): Mis Grupos Recientes --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-acode-primary tracking-tight">
                    Grupos Recientes
                </h2>
                <a href="{{ route('groups.index') }}"
                   class="text-xs font-semibold text-acode-tech hover:underline">
                    Ver todos los grupos →
                </a>
            </div>

            <div class="space-y-3">
                @forelse ($recentGroups ?? collect() as $group)
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-acode-tech hover:shadow-md">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-acode-tech uppercase tracking-wider">
                                {{ $group->subject ?? 'Materia General' }}
                            </span>
                            <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                                {{ $group->is_closed ? 'Cerrado' : 'Activo' }}
                            </x-badge>
                        </div>

                        <h3 class="mt-1.5 text-base font-bold text-acode-primary">
                            {{ $group->name }}
                        </h3>

                        <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div class="flex items-center gap-3 text-slate-500">
                                <span class="font-mono font-semibold text-slate-700">
                                    Folio: {{ $group->access_folio }}
                                </span>
                                <span>•</span>
                                <span>
                                    {{ $group->students_count }} {{ $group->students_count === 1 ? 'estudiante' : 'estudiantes' }}
                                </span>
                            </div>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('groups.show', $group) }}"
                                   class="font-semibold text-acode-tech hover:underline">
                                    Ver grupo
                                </a>
                                <a href="{{ route('groups.periods.index', $group) }}"
                                   class="font-semibold text-acode-primary hover:underline">
                                    Periodos
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-empty-state
                        icon="userGroup"
                        title="Todavía no tienes grupos"
                        message="Crea tu primer grupo escolar para inscribir estudiantes mediante código QR o folio."
                    />
                @endforelse
            </div>
        </div>

    </div>

    {{-- Nivel 4: Catálogo Operativo de Exámenes Recientes --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-acode-primary tracking-tight">
                Exámenes Recientes
            </h2>
            <a href="{{ route('exams.index') }}"
               class="text-xs font-semibold text-acode-tech hover:underline">
                Ver todos los exámenes →
            </a>
        </div>

        <x-table>
            <x-slot:header>
                <x-table.th>Examen</x-table.th>
                <x-table.th>Grupos</x-table.th>
                <x-table.th align="center">Reactivos</x-table.th>
                <x-table.th align="center">Entregas</x-table.th>
                <x-table.th align="center">Estado</x-table.th>
                <x-table.th align="right">Acciones</x-table.th>
            </x-slot:header>

            @forelse ($recentExams ?? collect() as $exam)
                <tr>
                    <x-table.td>
                        <p class="font-semibold text-slate-900 leading-snug">
                            {{ $exam->title }}
                        </p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Escala {{ $exam->grading_scale }} • {{ $exam->starts_at?->format('d/m/Y H:i') ?? 'Sin fecha programada' }}
                        </p>
                    </x-table.td>

                    <x-table.td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @forelse ($exam->groups as $group)
                                <x-badge variant="tech">{{ $group->name }}</x-badge>
                            @empty
                                <span class="text-xs text-slate-400">Sin grupo asignado</span>
                            @endforelse
                        </div>
                    </x-table.td>

                    <x-table.td align="center">
                        <x-badge variant="neutral">{{ $exam->exam_questions_count ?? 0 }} reactivos</x-badge>
                    </x-table.td>

                    <x-table.td align="center">
                        <span class="font-mono text-xs font-semibold text-slate-700">
                            {{ $exam->student_attempts_count ?? 0 }}
                        </span>
                    </x-table.td>

                    <x-table.td align="center">
                        <x-badge :variant="$exam->is_published ? 'accent' : 'warning'">
                            {{ $exam->is_published ? 'Publicado' : 'Borrador' }}
                        </x-badge>
                    </x-table.td>

                    <x-table.td align="right">
                        <div class="inline-flex flex-wrap items-center justify-end gap-2">
                            <a href="{{ route('exams.assemble', $exam) }}"
                               class="text-xs font-semibold text-acode-tech hover:underline">
                                Armar
                            </a>

                            @if ($exam->is_published)
                                <a href="{{ route('exams.monitor', $exam) }}"
                                   class="text-xs font-semibold text-acode-primary hover:underline">
                                    Monitoreo
                                </a>
                            @endif

                            @if (($exam->student_attempts_count ?? 0) > 0)
                                <a href="{{ route('exams.revision.index', $exam) }}"
                                   class="text-xs font-semibold text-acode-accent hover:underline">
                                    Revisar
                                </a>
                            @endif
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-0">
                        <x-empty-state
                            class="border-0 shadow-none rounded-none py-8"
                            icon="document"
                            title="Todavía no has creado exámenes"
                            message="Configura tu primer examen y asígnalo a tus grupos activos."
                        />
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>

</div>
@endsection