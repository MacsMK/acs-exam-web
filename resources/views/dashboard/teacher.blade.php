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
                 §5.2.2, §7.2, §7.3, §7.8.2, §7.9, §8, Tabla 20; MC-V2 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Panel Docente')

@section('content')
<div
    @if (! empty($showPlanOnboardingOverlay))
        inert
        aria-hidden="true"
        class="space-y-6 pointer-events-none select-none opacity-55"
    @else
        class="space-y-6"
    @endif
>

    {{-- Nivel 1: Hero Institucional + Barra de Acciones Rápidas (Above the Fold) --}}
    <x-page-header
        :title="'¡Hola, ' . auth()->user()->first_name . '!'"
        subtitle="Administra tus grupos escolares, alimenta tu banco de reactivos, configura exámenes y da seguimiento académico en tiempo real."
    >
        <x-slot:actions>
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
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Grid de 4 Tarjetas KPI Interactivas --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">

        {{-- KPI 1: Mis grupos --}}
        <x-kpi-card
            label="Mis grupos"
            :value="$groupsCount"
            meta="Aulas escolares"
            nav-icon="groups"
            tone="tech"
            :href="route('groups.index')"
        />

        {{-- KPI 2: Banco de preguntas --}}
        <x-kpi-card
            label="Banco de preguntas"
            :value="$questionsCount"
            meta="Reactivos registrados"
            nav-icon="questions"
            tone="tech"
            :href="route('questions.index')"
        />

        {{-- KPI 3: Exámenes creados --}}
        <x-kpi-card
            label="Exámenes creados"
            :value="$examsCount"
            meta="Evaluaciones activas"
            nav-icon="exams"
            tone="warning"
            :href="route('exams.index')"
        />

        {{-- KPI 4: Promedio general --}}
        <x-kpi-card
            label="Promedio general"
            :value="number_format((float) ($averageScore ?? 0), 1)"
            nav-icon="grades"
            tone="accent"
            :href="route('teacher.gradebook.hub')"
            :mono="true"
        >
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-acode-accent/15 px-2.5 py-1 text-xs font-semibold text-acode-primary border border-acode-accent/40">
                    <span class="h-1.5 w-1.5 rounded-full bg-acode-accent"></span>
                    {{ (int) ($passedCount ?? 0) }} aprobados
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-acode-danger/10 px-2.5 py-1 text-xs font-semibold text-acode-danger border border-acode-danger/30">
                    <span class="h-1.5 w-1.5 rounded-full bg-acode-danger"></span>
                    {{ (int) ($failedCount ?? 0) }} reprobados
                </span>
            </div>
        </x-kpi-card>

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
            <x-section-header
                title="Grupos Recientes"
                action-label="Ver todos los grupos →"
                :action-href="route('groups.index')"
            />

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
        <x-section-header
            title="Exámenes Recientes"
            action-label="Ver todos los exámenes →"
            :action-href="route('exams.index')"
        />

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
                            {{ $exam->starts_at?->format('d/m/Y H:i') ?? 'Sin fecha programada' }}
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

@if (! empty($showPlanOnboardingOverlay) && ! empty($onboardingOverlay))
    @include('dashboard.partials._plan-onboarding-overlay', ['onboardingOverlay' => $onboardingOverlay])
@endif
@endsection
