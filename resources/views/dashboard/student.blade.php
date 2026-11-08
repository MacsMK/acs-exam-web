{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: dashboard/student.blade.php (MPW-V2 §7.3, §7.8.2, §8.3, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Panel del Estudiante')

@section('content')
    {{-- Hero H1 institucional --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
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

    {{-- Tarjetas métricas elevadas --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <x-card class="border-slate-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Grupos Inscritos</p>
                    <p class="text-3xl font-extrabold text-acode-primary mt-1">{{ $groupsCount }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-acode-surface flex items-center justify-center text-acode-tech">
                    <x-icon name="userGroup" class="w-6 h-6" />
                </div>
            </div>
        </x-card>

        <x-card class="border-slate-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Exámenes Pendientes</p>
                    <p class="text-3xl font-extrabold text-acode-primary mt-1">{{ $pendingExams->count() }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-acode-surface flex items-center justify-center text-acode-warning">
                    <x-icon name="clock" class="w-6 h-6" />
                </div>
            </div>
        </x-card>

        <x-card class="border-slate-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Evaluaciones Presentadas</p>
                    <p class="text-3xl font-extrabold text-acode-primary mt-1">{{ $recentAttempts->count() }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-acode-surface flex items-center justify-center text-acode-accent">
                    <x-icon name="reportBar" class="w-6 h-6" />
                </div>
            </div>
        </x-card>
    </div>

    {{-- Tabla de Exámenes Disponibles --}}
    <div class="space-y-3 mb-8">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-acode-primary tracking-tight">Exámenes Disponibles</h2>
            <a href="{{ route('student.exams.index') }}" class="text-xs font-semibold text-acode-tech hover:underline">Ver catálogo completo</a>
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
                        <form method="POST" action="{{ route('attempts.start', $exam) }}" class="inline">
                            @csrf
                            <x-button type="submit" variant="primary" size="sm">
                                Abrir examen
                            </x-button>
                        </form>
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
        <h2 class="text-base font-bold text-acode-primary tracking-tight">Historial de Intentos Recientes</h2>

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
                        @php
                            $score = $attempt->final_score ?? $attempt->objective_score;
                        @endphp
                        @if ($score !== null)
                            <span class="font-bold font-mono text-sm {{ $score >= 6.0 ? 'text-acode-accent' : 'text-acode-danger' }}">
                                {{ number_format((float) $score, 1) }}
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
@endsection
