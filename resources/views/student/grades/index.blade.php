{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/grades/index.blade.php
    Ubicación:   resources/views/student/grades/index.blade.php
    Descripción: Vista de Mis Calificaciones e Historial Académico del estudiante
                 homologada al UI Kit A-Code (RF-REP-003, RF-REP-007, RF-REP-012;
                 MPW-V3 §7.3, §7.4.2, §7.8.2, §8.1–§8.4; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Mis Calificaciones')

@section('content')
@php
    $attemptStatusLabels = [
        'submitted' => ['label' => 'Finalizado', 'variant' => 'accent'],
        'in_progress' => ['label' => 'En progreso', 'variant' => 'warning'],
        'expired' => ['label' => 'Expirado', 'variant' => 'neutral'],
        'cancelled_by_incident' => ['label' => 'Cancelado', 'variant' => 'danger'],
        'graded' => ['label' => 'Calificado', 'variant' => 'accent'],
    ];
@endphp

<div class="space-y-6">
    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        title="Mis Calificaciones e Historial Académico"
        subtitle="Consulta tu desempeño en evaluaciones, desglose ponderado por periodos y retroalimentación docente."
    >
        <x-slot:actions>
            <x-button :href="route('student.exams.index')" variant="primary" size="md">
                <x-nav-icon name="exams" class="w-4 h-4" />
                Abrir exámenes
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Tarjetas KPI de Desempeño --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-kpi-card
            label="Promedio general"
            :value="number_format((float) ($averageScore ?? 0.0), 1)"
            meta="Escala 0–10 en exámenes calificados"
            nav-icon="grades"
            tone="tech"
            :mono="true"
        />

        <x-kpi-card
            label="Exámenes aprobados"
            :value="$passedCount ?? 0"
            meta="Calificación ≥ 6.0"
            icon="success"
            tone="accent"
        />

        <x-kpi-card
            label="Evaluaciones presentadas"
            :value="$totalEvaluations ?? $recentAttempts->count() ?? 0"
            meta="Intentos registrados"
            icon="reportBar"
            tone="warning"
        />
    </div>

    {{-- Nivel 3: Calificaciones por Periodo Escolar --}}
    @if (isset($groups) && $groups->isNotEmpty())
        <div class="space-y-4">
            <x-section-header
                title="Evaluación Continua por Asignatura y Periodos"
                subtitle="Desglose de rubros ponderados (exámenes, tareas, proyectos) y promedio por periodo."
            />

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                @foreach ($groups as $group)
                    <x-card padding="p-5" class="space-y-4">
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="text-base font-bold text-acode-primary">{{ $group->name }}</h3>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $group->subject ?? 'Materia general' }} · {{ $group->teacher?->full_name ?? 'Docente titular' }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <x-badge variant="tech">{{ $group->evaluationPeriods->count() }} periodos</x-badge>
                                <a href="{{ route('student.grades.show', $group) }}" class="text-xs font-semibold text-acode-tech hover:underline">
                                    Ver detalles →
                                </a>
                            </div>
                        </div>

                        @if ($group->evaluationPeriods->isEmpty())
                            <p class="py-2 text-center text-xs italic text-slate-500">
                                El profesor aún no ha configurado periodos de evaluación para este grupo.
                            </p>
                        @else
                            <div class="space-y-2.5">
                                @foreach ($group->evaluationPeriods as $period)
                                    @php
                                        $grade = $period->studentGrades->first();
                                    @endphp
                                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/70 p-3">
                                        <div>
                                            <p class="text-xs font-bold text-slate-800">{{ $period->name }}</p>
                                            <p class="text-xs text-slate-500">Fórmula: {{ $period->formula ?? 'Estándar' }}</p>
                                            @if ($grade)
                                                <div class="mt-1 flex flex-wrap items-center gap-2.5 text-xs text-slate-500">
                                                    @if ($grade->exams_component !== null)
                                                        <span>Examen: <strong class="font-mono text-slate-700">{{ $grade->exams_component }}</strong></span>
                                                    @endif
                                                    @if ($grade->homework_component !== null)
                                                        <span>Tareas: <strong class="font-mono text-slate-700">{{ $grade->homework_component }}</strong></span>
                                                    @endif
                                                    @if ($grade->project_component !== null)
                                                        <span>Proyecto: <strong class="font-mono text-slate-700">{{ $grade->project_component }}</strong></span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        <div class="text-right shrink-0">
                                            @if ($grade && $grade->final_grade !== null)
                                                <span class="font-mono text-base font-bold {{ (float) $grade->final_grade >= 6.0 ? 'text-acode-accent-dark' : 'text-acode-danger' }}">
                                                    {{ number_format((float) $grade->final_grade, 1) }}
                                                </span>
                                                <p class="text-xs text-slate-500">Nota periodo</p>
                                            @else
                                                <span class="text-xs italic text-slate-400">Pendiente</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-card>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Nivel 4: Tabla de calificaciones detalladas --}}
    <div class="space-y-4">
        <x-section-header
            title="Desglose de Calificaciones e Intentos"
            subtitle="Información estrictamente privada y visible solo para tu cuenta."
        />

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

            @forelse ($attempts ?? $recentAttempts ?? collect() as $attempt)
                @php
                    $isHiddenMode = ($attempt->exam?->result_visibility_mode ?? 'hidden') === 'hidden';
                    $isHundredScale = ($attempt->exam?->grading_scale ?? '0-10') === '0-100';
                    $passingMin = $isHundredScale ? 60.0 : 6.0;
                    $statusMeta = $attemptStatusLabels[$attempt->status] ?? ['label' => 'Registrado', 'variant' => 'neutral'];
                @endphp
                <tr>
                    <x-table.td>
                        <p class="font-semibold text-slate-900 leading-snug">
                            {{ $attempt->exam->title ?? 'Evaluación institucional' }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Escala oficial: {{ $attempt->exam->grading_scale ?? '0-10' }}
                        </p>
                    </x-table.td>

                    <x-table.td>
                        <p class="text-xs font-medium text-slate-700">
                            {{ $attempt->group?->name ?? 'Sin grupo asignado' }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ $attempt->group?->subject ?? 'Materia general' }}
                        </p>
                    </x-table.td>

                    <x-table.td>
                        <span class="text-xs text-slate-600">
                            {{ $attempt->group?->teacher?->full_name ?? 'Docente titular' }}
                        </span>
                    </x-table.td>

                    <x-table.td align="center">
                        <span class="font-mono text-xs text-slate-600">
                            {{ $attempt->created_at?->format('d/m/Y H:i') ?? '—' }}
                        </span>
                    </x-table.td>

                    <x-table.td align="center">
                        @if ($isHiddenMode)
                            <x-badge variant="neutral">Reservado</x-badge>
                        @elseif ($attempt->final_score !== null)
                            <div class="flex flex-col items-center">
                                <span class="font-mono text-base font-bold {{ (float) $attempt->final_score >= $passingMin ? 'text-acode-accent-dark' : 'text-acode-danger' }}">
                                    {{ number_format((float) $attempt->final_score, 1) }}
                                </span>
                                <span class="font-mono text-xs text-slate-500">
                                    / {{ $isHundredScale ? '100' : '10.0' }}
                                </span>
                            </div>
                        @else
                            <x-badge variant="warning">En revisión</x-badge>
                        @endif
                    </x-table.td>

                    <x-table.td align="center">
                        <x-badge :variant="$statusMeta['variant']">{{ $statusMeta['label'] }}</x-badge>
                    </x-table.td>

                    <x-table.td align="right">
                        @if ($attempt->status === 'submitted')
                            <a href="{{ route('attempts.result', $attempt) }}"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-acode-tech hover:text-acode-tech-dark hover:underline">
                                <span>Ver detalles</span>
                                <span aria-hidden="true">→</span>
                            </a>
                        @else
                            <a href="{{ route('attempts.show', $attempt) }}"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-acode-warning-dark hover:underline">
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
                                <x-nav-icon name="exams" class="w-4 h-4" />
                                Abrir exámenes
                            </x-button>
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>
</div>
@endsection
