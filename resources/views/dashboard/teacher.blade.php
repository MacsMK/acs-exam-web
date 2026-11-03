{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: dashboard/teacher.blade.php (MPW-V2 §7.3, §7.8.2, §7.9, §8.3, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Panel del Docente')

@section('content')
    {{-- Hero H1 institucional --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">
                Hola, {{ auth()->user()->first_name ?? auth()->user()->full_name }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Panel central de administración académica y métricas de desempeño docente.
            </p>
        </div>

        {{-- Botones de acción rápida con verbos normativos (Tabla 20) --}}
        <div class="flex flex-wrap items-center gap-2.5">
            <x-button :href="route('groups.create')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4 mr-1.5" />
                Crear grupo
            </x-button>
            <x-button :href="route('questions.create')" variant="secondary" size="md">
                <x-icon name="edit" class="w-4 h-4 mr-1.5" />
                Crear reactivo
            </x-button>
            <x-button :href="route('exams.create')" variant="accent" size="md">
                <x-icon name="clock" class="w-4 h-4 mr-1.5" />
                Crear examen
            </x-button>
        </div>
    </div>

    {{-- Tarjetas métricas elevadas con iconos institucionales --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <a href="{{ route('groups.index') }}" class="group block">
            <x-card class="hover:shadow-md transition border-slate-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Grupos Activos</p>
                        <p class="text-3xl font-extrabold text-acode-primary mt-1">{{ $groupsCount }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-acode-surface flex items-center justify-center text-acode-tech group-hover:bg-acode-tech group-hover:text-white transition">
                        <x-icon name="userGroup" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>
        </a>

        <a href="{{ route('questions.index') }}" class="group block">
            <x-card class="hover:shadow-md transition border-slate-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Banco de Reactivos</p>
                        <p class="text-3xl font-extrabold text-acode-primary mt-1">{{ $questionsCount }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-acode-surface flex items-center justify-center text-acode-tech group-hover:bg-acode-tech group-hover:text-white transition">
                        <x-icon name="edit" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>
        </a>

        <a href="{{ route('exams.index') }}" class="group block">
            <x-card class="hover:shadow-md transition border-slate-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Exámenes Totales</p>
                        <p class="text-3xl font-extrabold text-acode-primary mt-1">{{ $examsCount }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-acode-surface flex items-center justify-center text-acode-tech group-hover:bg-acode-tech group-hover:text-white transition">
                        <x-icon name="clock" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>
        </a>

        <div class="block">
            <x-card class="hover:shadow-md transition border-slate-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Promedio General</p>
                        <p class="text-3xl font-extrabold text-acode-primary mt-1">{{ number_format($averageScore, 1) }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-acode-surface flex items-center justify-center text-acode-accent">
                        <x-icon name="reportBar" class="w-6 h-6" />
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Gráfica de Analítica de Calificaciones (Chart.js + Alpine.js, Tabla 23) --}}
    <div class="mb-8">
        <x-analytics.group-chart
            :passedCount="$passedCount"
            :failedCount="$failedCount"
            title="Distribución Institucional de Aprobados y Calificaciones"
        />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Tabla de Exámenes Recientes (2 columnas de 3) --}}
        <div class="lg:col-span-2 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-acode-primary tracking-tight">Exámenes Recientes</h2>
                <a href="{{ route('exams.index') }}" class="text-xs font-semibold text-acode-tech hover:underline">Ver todos</a>
            </div>

            <x-table>
                <x-slot:header>
                    <x-table.th>Examen</x-table.th>
                    <x-table.th>Estado</x-table.th>
                    <x-table.th align="center">Reactivos</x-table.th>
                    <x-table.th align="center">Intentos</x-table.th>
                    <x-table.th align="right">Acción</x-table.th>
                </x-slot:header>

                @forelse ($recentExams as $exam)
                    <tr>
                        <x-table.td>
                            <p class="font-medium text-slate-900 leading-snug">{{ $exam->title }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $exam->groups->pluck('name')->join(', ') ?: 'Sin grupo' }}</p>
                        </x-table.td>
                        <x-table.td>
                            @if ($exam->is_published)
                                <x-badge variant="success">Publicado</x-badge>
                            @else
                                <x-badge variant="neutral">Borrador</x-badge>
                            @endif
                        </x-table.td>
                        <x-table.td align="center">
                            {{ $exam->exam_questions_count }}
                        </x-table.td>
                        <x-table.td align="center">
                            {{ $exam->student_attempts_count }}
                        </x-table.td>
                        <x-table.td align="right">
                            <x-button :href="route('exams.assemble', $exam)" variant="secondary" size="sm">
                                Abrir
                            </x-button>
                        </x-table.td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-0">
                            <x-empty-state
                                class="border-0 shadow-none rounded-none py-8"
                                icon="clock"
                                title="Sin exámenes aún"
                                message="Crea tu primer examen para comenzar las evaluaciones."
                            >
                                <x-button :href="route('exams.create')" variant="primary" size="sm">
                                    Crear examen
                                </x-button>
                            </x-empty-state>
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </div>

        {{-- Grupos Recientes (1 columna de 3) --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-acode-primary tracking-tight">Grupos Recientes</h2>
                <a href="{{ route('groups.index') }}" class="text-xs font-semibold text-acode-tech hover:underline">Ver todos</a>
            </div>

            <div class="space-y-3">
                @forelse ($recentGroups as $group)
                    <x-card class="p-4 hover:shadow-md transition">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="font-bold text-sm text-acode-primary">{{ $group->name }}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $group->subject ?? 'Materia general' }}</p>
                            </div>
                            <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                                {{ $group->is_closed ? 'Cerrado' : 'Activo' }}
                            </x-badge>
                        </div>
                        <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span class="flex items-center gap-1 font-mono text-slate-700 font-medium">
                                <x-icon name="key" class="w-3.5 h-3.5 text-slate-400" />
                                {{ $group->access_folio }}
                            </span>
                            <a href="{{ route('groups.show', $group) }}" class="font-semibold text-acode-tech hover:underline">
                                Ver detalle
                            </a>
                        </div>
                    </x-card>
                @empty
                    <x-empty-state
                        icon="userGroup"
                        title="Sin grupos escolares"
                        message="Agrega un grupo para inscribir alumnos."
                    >
                        <x-button :href="route('groups.create')" variant="primary" size="sm">
                            Crear grupo
                        </x-button>
                    </x-empty-state>
                @endforelse
            </div>
        </div>
    </div>
@endsection
