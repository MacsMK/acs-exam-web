{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/dashboard/student.blade.php
    Descripción: Panel de control del estudiante con hero institucional, tarjetas
                 métricas elevadas, asignaturas inscritas, exámenes disponibles
                 e historial de intentos (MPW-V2 §7.3, §7.8.2, §8.3, Tabla 20).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Panel del Estudiante')

@section('content')
<div class="space-y-8">
    {{-- Hero H1 institucional --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">
                Hola, {{ auth()->user()->first_name ?? auth()->user()->full_name }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Consulta tus asignaturas matriculadas, exámenes vigentes y calificaciones registradas.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <x-button :href="route('student.groups.join.form')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4 mr-1.5" />
                Unirme a un grupo
            </x-button>
        </div>
    </div>

    {{-- Tarjetas métricas interactivas homogéneas al panel docente --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        {{-- Tarjeta 1: Grupos --}}
        <a href="{{ route('student.groups.index') }}"
           class="group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-acode-tech hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech transition-transform duration-200 group-hover:scale-105">
                    <x-nav-icon name="groups" class="h-6 w-6" />
                </div>
                <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-600 transition-colors duration-200 group-hover:text-acode-tech">
                    <span>Grupos Inscritos</span>
                    <svg class="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-3xl lg:text-4xl font-extrabold text-acode-primary tracking-tight">
                    {{ $groupsCount }}
                </p>
                <p class="text-xs text-slate-500">
                    Materias matriculadas
                </p>
            </div>
        </a>

        {{-- Tarjeta 2: Exámenes Pendientes --}}
        <a href="{{ route('student.exams.index') }}"
           class="group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition-transform duration-200 group-hover:scale-105">
                    <x-nav-icon name="exams" class="h-6 w-6" />
                </div>
                <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-600 transition-colors duration-200 group-hover:text-amber-600">
                    <span>Exámenes Pendientes</span>
                    <svg class="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-3xl lg:text-4xl font-extrabold text-acode-primary tracking-tight">
                    {{ $pendingExams->count() }}
                </p>
                <p class="text-xs text-slate-500">
                    Evaluaciones activas
                </p>
            </div>
        </a>

        {{-- Tarjeta 3: Evaluaciones Presentadas --}}
        <a href="{{ route('student.grades.index') }}"
           class="group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition-transform duration-200 group-hover:scale-105">
                    <x-nav-icon name="grades" class="h-6 w-6" />
                </div>
                <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-600 transition-colors duration-200 group-hover:text-emerald-600">
                    <span>Evaluaciones Presentadas</span>
                    <svg class="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-3xl lg:text-4xl font-extrabold text-acode-primary tracking-tight">
                    {{ $recentAttempts->count() }}
                </p>
                <p class="text-xs text-slate-500">
                    Intentos calificados
                </p>
            </div>
        </a>
    </div>

    {{-- Asignaturas y Grupos Matriculados --}}
    @if (isset($enrolledGroups) && $enrolledGroups->isNotEmpty())
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-acode-primary tracking-tight">
                    Mis Asignaturas Matriculadas
                </h2>
                <a href="{{ Route::has('student.groups.index') ? route('student.groups.index') : '#' }}"
                   class="text-xs font-semibold text-acode-tech hover:underline">
                    Ver todos los grupos →
                </a>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($enrolledGroups->take(3) as $group)
                    <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:shadow transition">
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold text-acode-tech uppercase tracking-wider">
                                    {{ $group->subject ?? 'Materia General' }}
                                </span>
                                <x-badge variant="accent">Activo</x-badge>
                            </div>
                            <h3 class="mt-2 text-base font-bold text-acode-primary">
                                {{ $group->name }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-500">
                                Profesor: {{ $group->teacher?->full_name ?? 'Docente titular' }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tabla de Exámenes Disponibles --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-acode-primary tracking-tight">
                Exámenes Disponibles
            </h2>
            <a href="{{ route('student.exams.index') }}" class="text-xs font-semibold text-acode-tech hover:underline">
                Ver catálogo completo
            </a>
        </div>

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
                        <p class="text-xs text-slate-400 mt-0.5">{{ $exam->description ?? 'Sin descripción adicional' }}</p>
                    </x-table.td>
                    <x-table.td>
                        <span class="text-xs text-slate-600">{{ $exam->teacher?->full_name ?? 'Docente titular' }}</span>
                    </x-table.td>
                    <x-table.td align="center">
                        <x-badge variant="neutral">{{ $exam->exam_questions_count }} reactivos</x-badge>
                    </x-table.td>
                    <x-table.td align="center">
                        <span class="text-xs font-mono text-slate-600">{{ $exam->total_duration_minutes ?? $exam->duration_minutes ?? 60 }} min</span>
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

    {{-- Historial de Intentos Recientes --}}
    <div class="space-y-3">
        <h2 class="text-base font-bold text-acode-primary tracking-tight">
            Historial de Intentos Recientes
        </h2>

        <x-table>
            <x-slot:header>
                <x-table.th>Evaluación</x-table.th>
                <x-table.th>Grupo</x-table.th>
                <x-table.th align="center">Calificación</x-table.th>
                <x-table.th align="center">Estado</x-table.th>
                <x-table.th align="right">Detalle</x-table.th>
            </x-slot:header>

            @forelse ($recentAttempts as $attempt)
                <tr>
                    <x-table.td>
                        <p class="font-medium text-slate-900 leading-snug">{{ $attempt->exam->title }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $attempt->created_at->format('d/m/Y H:i') }}</p>
                    </x-table.td>
                    <x-table.td>
                        <span class="text-xs text-slate-600">{{ $attempt->group?->name ?? 'Sin grupo' }}</span>
                    </x-table.td>
                    <x-table.td align="center">
                        @if ($attempt->display_score !== null)
                            <span class="font-bold font-mono text-sm {{ $attempt->display_score >= 6.0 ? 'text-teal-600' : 'text-acode-danger' }}">
                                {{ number_format((float) $attempt->display_score, 1) }}
                            </span>
                        @else
                            <span class="text-xs text-slate-400">Pendiente</span>
                        @endif
                    </x-table.td>
                    <x-table.td align="center">
                        @if ($attempt->status === 'submitted')
                            <x-badge variant="success">Finalizado</x-badge>
                        @elseif ($attempt->status === 'in_progress')
                            <x-badge variant="warning">En progreso</x-badge>
                        @else
                            <x-badge variant="neutral">{{ $attempt->status }}</x-badge>
                        @endif
                    </x-table.td>
                    <x-table.td align="right">
                        @if ($attempt->status === 'submitted')
                            <a href="{{ route('attempts.result', $attempt) }}" class="text-xs font-semibold text-acode-tech hover:underline">
                                Ver resultado
                            </a>
                        @else
                            <a href="{{ route('attempts.show', $attempt) }}" class="text-xs font-semibold text-acode-tech hover:underline">
                                Continuar
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
