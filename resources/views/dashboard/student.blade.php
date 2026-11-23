{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/dashboard/student.blade.php
    Ubicación:   resources/views/dashboard/student.blade.php
    Descripción: Panel de control del estudiante homologado al UI Kit A-Code:
                 encabezado institucional, tarjetas KPI interactivas, asignaturas
                 matriculadas, exámenes disponibles e historial de intentos
                 (MPW-V3 §7.3, §7.4.2, §7.8.2, §8.1–§8.4; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Panel del Estudiante')

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
        :title="'¡Hola, ' . (auth()->user()->first_name ?? auth()->user()->full_name) . '!'"
        subtitle="Consulta tus asignaturas matriculadas, exámenes vigentes y calificaciones registradas."
    >
        <x-slot:actions>
            <x-button :href="route('student.exams.index')" variant="neutral" size="md">
                <x-nav-icon name="exams" class="w-4 h-4" />
                Abrir exámenes
            </x-button>

            <x-button :href="route('student.groups.join.form')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4" />
                Unirme a un grupo
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Tarjetas KPI Interactivas --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-kpi-card
            label="Grupos Inscritos"
            :value="$groupsCount"
            meta="Materias matriculadas"
            nav-icon="groups"
            tone="tech"
            :href="route('student.groups.index')"
        />

        <x-kpi-card
            label="Exámenes Pendientes"
            :value="$pendingExams->count()"
            meta="Evaluaciones activas"
            nav-icon="exams"
            tone="warning"
            :href="route('student.exams.index')"
        />

        <x-kpi-card
            label="Evaluaciones Presentadas"
            :value="$recentAttempts->count()"
            meta="Intentos calificados"
            nav-icon="grades"
            tone="accent"
            :href="route('student.grades.index')"
        />
    </div>

    {{-- Nivel 3: Asignaturas y Grupos Matriculados --}}
    @if (isset($enrolledGroups) && $enrolledGroups->isNotEmpty())
        <div class="space-y-4">
            <x-section-header
                title="Mis Asignaturas Matriculadas"
                action-label="Ver todos los grupos →"
                :action-href="route('student.groups.index')"
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($enrolledGroups->take(3) as $group)
                    <a href="{{ route('student.grades.show', $group) }}"
                       class="group flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-acode-tech hover:shadow-md">
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-acode-tech">
                                    {{ $group->subject ?? 'Materia general' }}
                                </span>
                                <x-badge variant="accent">Activo</x-badge>
                            </div>
                            <h3 class="mt-2 text-base font-bold text-acode-primary group-hover:text-acode-tech transition-colors">
                                {{ $group->name }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-500">
                                Profesor: {{ $group->teacher?->full_name ?? 'Docente titular' }}
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full">
                        <x-empty-state
                            icon="userGroup"
                            title="Aún no tienes grupos inscritos"
                            message="Solicita el folio de tu clase a tu profesor para unirte."
                        />
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Nivel 4: Tabla de Exámenes Disponibles --}}
    <div class="space-y-3">
        <x-section-header
            title="Exámenes Disponibles"
            action-label="Ver catálogo completo →"
            :action-href="route('student.exams.index')"
        />

        <x-table>
            <x-slot:header>
                <x-table.th>Examen</x-table.th>
                <x-table.th>Profesor</x-table.th>
                <x-table.th align="center">Reactivos</x-table.th>
                <x-table.th align="center">Tiempo</x-table.th>
                <x-table.th align="right">Acción</x-table.th>
            </x-slot:header>

            @forelse ($pendingExams as $exam)
                <tr>
                    <x-table.td>
                        <p class="font-semibold text-slate-900 leading-snug">{{ $exam->title }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $exam->description ?? 'Sin descripción adicional' }}</p>
                    </x-table.td>
                    <x-table.td>
                        <span class="text-xs text-slate-600">{{ $exam->teacher?->full_name ?? 'Docente titular' }}</span>
                    </x-table.td>
                    <x-table.td align="center">
                        <x-badge variant="neutral">{{ $exam->exam_questions_count }} reactivos</x-badge>
                    </x-table.td>
                    <x-table.td align="center">
                        <span class="text-xs font-mono text-slate-600">{{ $exam->maxDurationMinutes() }} min</span>
                    </x-table.td>
                    <x-table.td align="right">
                        <x-button :href="route('student.exams.instructions', $exam)" variant="primary" size="sm">
                            Abrir examen
                        </x-button>
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-0">
                        <x-empty-state
                            class="border-0 shadow-none rounded-none py-8"
                            icon="clock"
                            title="No tienes exámenes pendientes"
                            message="Todos tus exámenes asignados se encuentran al corriente."
                        />
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>

    {{-- Nivel 5: Historial de Intentos Recientes --}}
    <div class="space-y-3">
        <x-section-header
            title="Historial de Intentos Recientes"
            action-label="Ver todas las calificaciones →"
            :action-href="route('student.grades.index')"
        />

        <x-table>
            <x-slot:header>
                <x-table.th>Evaluación</x-table.th>
                <x-table.th>Grupo</x-table.th>
                <x-table.th align="center">Calificación</x-table.th>
                <x-table.th align="center">Estado</x-table.th>
                <x-table.th align="right">Detalle</x-table.th>
            </x-slot:header>

            @forelse ($recentAttempts as $attempt)
                @php
                    $statusMeta = $attemptStatusLabels[$attempt->status] ?? ['label' => 'Registrado', 'variant' => 'neutral'];
                @endphp
                <tr>
                    <x-table.td>
                        <p class="font-medium text-slate-900 leading-snug">{{ $attempt->exam->title }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $attempt->created_at->format('d/m/Y H:i') }}</p>
                    </x-table.td>
                    <x-table.td>
                        <span class="text-xs text-slate-600">{{ $attempt->group?->name ?? 'Sin grupo' }}</span>
                    </x-table.td>
                    <x-table.td align="center">
                        @if ($attempt->display_score !== null)
                            <span class="font-bold font-mono text-sm {{ $attempt->display_score >= 6.0 ? 'text-acode-accent-dark' : 'text-acode-danger' }}">
                                {{ number_format((float) $attempt->display_score, 1) }}
                            </span>
                        @else
                            <span class="text-xs text-slate-500">Pendiente</span>
                        @endif
                    </x-table.td>
                    <x-table.td align="center">
                        <x-badge :variant="$statusMeta['variant']">{{ $statusMeta['label'] }}</x-badge>
                    </x-table.td>
                    <x-table.td align="right">
                        @if ($attempt->status === 'submitted')
                            <a href="{{ route('attempts.result', $attempt) }}" class="text-xs font-semibold text-acode-tech hover:underline">
                                Ver detalles →
                            </a>
                        @else
                            <a href="{{ route('attempts.show', $attempt) }}" class="text-xs font-semibold text-acode-tech hover:underline">
                                Continuar →
                            </a>
                        @endif
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-0">
                        <x-empty-state
                            class="border-0 shadow-none rounded-none py-8"
                            icon="reportBar"
                            title="Aún no has presentado exámenes"
                            message="Tus calificaciones e historial de intentos se mostrarán aquí."
                        />
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>
</div>
@endsection
