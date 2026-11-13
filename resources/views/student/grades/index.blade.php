{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/grades/index.blade.php
    Descripción: Vista de Mis Calificaciones e Historial Académico del estudiante.
                 Consulta de calificaciones, escala, ponderaciones y retroalimentación
                 docente sin exponer datos de otros alumnos (RF-REP-003, RF-REP-007, RF-REP-012).
    Estándares:  MPW-V2 §7.3, §7.8, §8.3, §8.10.4, Tabla 20 (Verbos oficiales).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Mis Calificaciones')

@section('content')
<div class="space-y-6">
    {{-- Encabezado de la página --}}
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">
                Mis Calificaciones e Historial Académico
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Consulta tu desempeño en evaluaciones, desglose de reactivos y retroalimentación docente (RF-REP-003, RF-REP-007, RF-REP-012).
            </p>
        </div>

        <div class="flex items-center gap-3">
            <x-button :href="route('student.exams.index')" variant="primary" size="md">
                <x-nav-icon name="exams" class="h-4 w-4 mr-1.5" />
                Mis exámenes
            </x-button>
        </div>
    </div>

    {{-- Tarjetas métricas de desempeño --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {{-- Tarjeta 1: Promedio General --}}
        <div class="group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:border-acode-tech hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-nav-icon name="grades" class="h-6 w-6" />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-slate-700">Promedio general</span>
                    <span class="rounded-full bg-acode-tech/10 px-2 py-0.5 text-[11px] font-semibold text-acode-tech">Escala 0-10</span>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-3xl font-extrabold text-acode-primary tracking-tight">
                    {{ number_format((float) ($averageScore ?? 0.0), 1) }}
                </p>
                <p class="text-xs text-slate-500">
                    En exámenes calificados
                </p>
            </div>
        </div>

        {{-- Tarjeta 2: Evaluaciones Aprobadas --}}
        <div class="group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:border-acode-accent hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <x-icon name="success" class="h-6 w-6 text-emerald-600" />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-slate-700">Exámenes aprobados</span>
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Aprobados</span>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-3xl font-extrabold text-acode-primary tracking-tight">
                    {{ $passedCount ?? 0 }}
                </p>
                <p class="text-xs text-slate-500">
                    Calificación &ge; 6.0
                </p>
            </div>
        </div>

        {{-- Tarjeta 3: Evaluaciones Presentadas --}}
        <div class="group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:border-acode-warning hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <x-icon name="reportBar" class="h-6 w-6 text-amber-600" />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-slate-700">Evaluaciones presentadas</span>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Historial</span>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-3xl font-extrabold text-acode-primary tracking-tight">
                    {{ $totalEvaluations ?? $recentAttempts->count() ?? 0 }}
                </p>
                <p class="text-xs text-slate-500">
                    Intentos registrados
                </p>
            </div>
        </div>
    </div>

    {{-- Calificaciones por Periodo Escolar (RF-REP-007 / P-174) --}}
    @if (isset($groups) && $groups->isNotEmpty())
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-acode-primary tracking-tight">
                        Evaluación Continua por Asignatura y Periodos
                    </h2>
                    <p class="text-xs text-slate-500">
                        Desglose de rubros ponderados (exámenes, tareas, proyectos) y promedio por periodo (RF-REP-007).
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                @foreach ($groups as $group)
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                        <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="font-bold text-base text-acode-primary">{{ $group->name }}</h3>
                                <p class="text-xs text-slate-500">{{ $group->subject ?? 'Materia general' }} · {{ $group->teacher?->full_name ?? 'Docente' }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-acode-surface px-2.5 py-0.5 text-xs font-semibold text-acode-tech">
                                {{ $group->evaluationPeriods->count() }} periodos
                            </span>
                        </div>

                        @if ($group->evaluationPeriods->isEmpty())
                            <p class="text-xs text-slate-400 py-2 italic text-center">El profesor aún no ha configurado periodos de evaluación para este grupo.</p>
                        @else
                            <div class="space-y-2.5">
                                @foreach ($group->evaluationPeriods as $period)
                                    @php
                                        $grade = $period->studentGrades->first();
                                    @endphp
                                    <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-3 flex items-center justify-between">
                                        <div>
                                            <p class="text-xs font-bold text-slate-800">{{ $period->name }}</p>
                                            <p class="text-[11px] text-slate-500">Fórmula: {{ $period->formula ?? 'Estándar' }}</p>
                                            @if ($grade)
                                                <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500">
                                                    @if ($grade->exams_component !== null) <span>Examen: <strong class="text-slate-700">{{ $grade->exams_component }}</strong></span> @endif
                                                    @if ($grade->homework_component !== null) <span>Tareas: <strong class="text-slate-700">{{ $grade->homework_component }}</strong></span> @endif
                                                    @if ($grade->project_component !== null) <span>Proyecto: <strong class="text-slate-700">{{ $grade->project_component }}</strong></span> @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            @if ($grade && $grade->final_grade !== null)
                                                <span class="font-bold font-mono text-base {{ (float) $grade->final_grade >= 6.0 ? 'text-teal-600' : 'text-acode-danger' }}">
                                                    {{ number_format((float) $grade->final_grade, 1) }}
                                                </span>
                                                <p class="text-[10px] text-slate-400">Nota periodo</p>
                                            @else
                                                <span class="text-xs text-slate-400 italic">Pendiente</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tabla de calificaciones detalladas --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-acode-primary tracking-tight">
                Desglose de Calificaciones e Intentos
            </h2>
            <span class="text-xs text-slate-500">
                Datos de privacidad individual garantizados (RF-REP-012)
            </span>
        </div>

        <x-table>
            <x-slot:header>
                <x-table.th>Evaluación / Examen</x-table.th>
                <x-table.th>Materia / Grupo</x-table.th>
                <x-table.th>Profesor</x-table.th>
                <x-table.th align="center">Fecha</x-table.th>
                <x-table.th align="center">Calificación</x-table.th>
                <x-table.th align="center">Estado</x-table.th>
                <x-table.th align="right">Detalles</x-table.th>
            </x-slot:header>

            @forelse ($attempts ?? auth()->user()->studentAttempts()->with(['exam.teacher', 'group'])->latest()->get() as $attempt)
                <tr>
                    {{-- Examen --}}
                    <x-table.td>
                        <p class="font-semibold text-slate-900 leading-snug">
                            {{ $attempt->exam->title ?? 'Evaluación institucional' }}
                        </p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Escala oficial: {{ $attempt->exam->grading_scale ?? '0-10' }} (RF-REP-003)
                        </p>
                    </x-table.td>

                    {{-- Materia / Grupo --}}
                    <x-table.td>
                        <p class="text-xs font-medium text-slate-700">
                            {{ $attempt->group?->name ?? 'Sin grupo asignado' }}
                        </p>
                        <p class="text-[11px] text-slate-400">
                            {{ $attempt->group?->subject ?? 'Materia general' }}
                        </p>
                    </x-table.td>

                    {{-- Profesor --}}
                    <x-table.td>
                        <span class="text-xs text-slate-600">
                            {{ $attempt->exam?->teacher?->full_name ?? 'Docente titular' }}
                        </span>
                    </x-table.td>

                    {{-- Fecha --}}
                    <x-table.td align="center">
                        <span class="text-xs font-mono text-slate-600">
                            {{ $attempt->created_at?->format('d/m/Y H:i') ?? '-' }}
                        </span>
                    </x-table.td>

                    {{-- Calificación --}}
                    <x-table.td align="center">
                        @if (($attempt->final_score ?? $attempt->objective_score) !== null)
                            <div class="flex flex-col items-center">
                                <span class="font-bold font-mono text-base {{ ($attempt->final_score ?? $attempt->objective_score) >= 6.0 ? 'text-teal-600' : 'text-acode-danger' }}">
                                    {{ number_format((float) ($attempt->final_score ?? $attempt->objective_score), 1) }}
                                </span>
                                <span class="text-[10px] text-slate-400 font-mono">
                                    / {{ $attempt->exam?->grading_scale === '0-100' ? '100' : '10.0' }}
                                </span>
                            </div>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">
                                En revisión
                            </span>
                        @endif
                    </x-table.td>

                    {{-- Estado --}}
                    <x-table.td align="center">
                        @if ($attempt->status === 'submitted')
                            <x-badge variant="success">Finalizado</x-badge>
                        @elseif ($attempt->status === 'in_progress')
                            <x-badge variant="warning">En progreso</x-badge>
                        @else
                            <x-badge variant="neutral">{{ ucfirst($attempt->status) }}</x-badge>
                        @endif
                    </x-table.td>

                    {{-- Acción --}}
                    <x-table.td align="right">
                        @if ($attempt->status === 'submitted')
                            <a href="{{ route('attempts.result', $attempt) }}"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-acode-tech hover:text-acode-tech-dark hover:underline">
                                <span>Ver resultado</span>
                                <span aria-hidden="true">→</span>
                            </a>
                        @else
                            <a href="{{ route('attempts.show', $attempt) }}"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 hover:text-amber-700 hover:underline">
                                <span>Continuar examen</span>
                                <span aria-hidden="true">→</span>
                            </a>
                        @endif
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-0">
                        <x-empty-state
                            class="border-0 shadow-none rounded-none py-10"
                            icon="reportBar"
                            title="No tienes calificaciones registradas"
                            message="Tus exámenes calificados, desgloses y retroalimentaciones docentes aparecerán en este catálogo."
                        >
                            <x-button :href="route('student.exams.index')" variant="primary" size="md">
                                <x-nav-icon name="exams" class="h-4 w-4 mr-1.5" />
                                Ver exámenes disponibles
                            </x-button>
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>
</div>
@endsection
