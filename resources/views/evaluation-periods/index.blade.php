{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/evaluation-periods/index.blade.php
    Ubicación:   resources/views/evaluation-periods/index.blade.php
    Descripción: Gestión operativa y configuración de periodos de evaluación
                 (parciales) y ponderaciones porcentuales por grupo escolar con
                 validación aritmética al 100% en tiempo real y tokens acode-*
                 (RF-CAL-001..005, RW-28..31, MPW-V2 §7.3, §7.4.2, §8.1–§8.12).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Periodos de Evaluación - ' . $group->name)

@section('content')
@php
    $activePeriod = $periods->first(fn ($p) => $p->is_active_today);
    $evaluatedStudentsTotal = (int) $periods->sum('student_grades_count');
@endphp

<div
    x-data="{
        openCreate: {{ $errors->any() ? 'true' : 'false' }},
        exams: {{ old('exams_weight', 50) }},
        homework: {{ old('homework_weight', 25) }},
        project: {{ old('project_weight', 15) }},
        other: {{ old('other_weight', 10) }},
        get total() {
            return (parseFloat(this.exams) || 0) + (parseFloat(this.homework) || 0) + (parseFloat(this.project) || 0) + (parseFloat(this.other) || 0);
        },
        get isValid() {
            return Math.abs(this.total - 100) < 0.01;
        }
    }"
    class="space-y-6"
>

    {{-- Nivel 1: Encabezado Institucional con Breadcrumbs y Acciones --}}
    <x-page-header
        title="Periodos de Evaluación"
        :subtitle="'Configura parciales, ponderaciones porcentuales y control de notas del grupo ' . $group->name . ($group->subject ? ' (' . $group->subject . ')' : '') . '.'"
        :breadcrumbs="[
            ['label' => 'Grupos', 'href' => route('groups.index')],
            ['label' => $group->name, 'href' => route('groups.show', $group)],
            ['label' => 'Periodos'],
        ]"
    >
        <x-slot:badge>
            <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                {{ $group->is_closed ? 'Grupo cerrado' : 'Grupo activo' }}
            </x-badge>
        </x-slot:badge>

        <x-slot:actions>
            <x-button :href="route('groups.show', $group)" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>

            @if (!$group->is_closed)
                <x-button type="button" @click="openCreate = !openCreate" variant="primary" size="md">
                    <x-icon name="add" class="w-4 h-4" />
                    Crear periodo
                </x-button>
            @else
                <x-badge variant="neutral" class="px-3 py-1.5 gap-1.5">
                    <x-icon name="lock" class="w-4 h-4" />
                    Ciclo escolar cerrado
                </x-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Barra de 3 Tarjetas KPI Canónicas --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-kpi-card
            label="Periodos registrados"
            :value="$periods->count()"
            meta="Parciales configurados"
            icon="clock"
            tone="tech"
        />

        <x-kpi-card
            label="Periodo vigente"
            icon="success"
            :tone="$activePeriod ? 'accent' : 'neutral'"
            :meta="$activePeriod
                ? $activePeriod->starts_at->format('d/m/Y') . ' al ' . $activePeriod->ends_at->format('d/m/Y')
                : 'Verifica las fechas del ciclo'"
        >
            <x-slot:valueSlot>
                <span class="block text-xl lg:text-2xl font-bold leading-snug tracking-normal">
                    {{ $activePeriod ? $activePeriod->name : 'Sin periodo activo' }}
                </span>
            </x-slot:valueSlot>
        </x-kpi-card>

        <x-kpi-card
            label="Matrícula evaluada"
            :value="$evaluatedStudentsTotal"
            meta="Calificaciones consolidadas"
            nav-icon="groups"
            tone="primary"
        />
    </div>

    {{-- Nivel 3: Formulario Desplegable de Creación de Periodo (RF-CAL-001, RF-CAL-004, RF-CAL-005) --}}
    @if (!$group->is_closed)
        <div
            x-show="openCreate"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
        >
            <x-card
                title="Registrar nuevo periodo de evaluación"
                subtitle="Define la vigencia del parcial y distribuye el 100 % de la ponderación entre los rubros evaluativos."
                class="border-2 border-acode-tech/30 shadow-md"
            >
                <x-slot:actions>
                    <x-button
                        type="button"
                        variant="neutral"
                        size="sm"
                        :icon-only="true"
                        x-on:click="openCreate = false"
                        title="Cerrar formulario"
                        aria-label="Cerrar formulario"
                    >
                        <x-icon name="xMark" class="w-4 h-4" />
                    </x-button>
                </x-slot:actions>

                <form method="POST" action="{{ route('groups.periods.store', $group) }}" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-form.group name="name" label="Nombre del periodo" required>
                            <x-form.input
                                id="name"
                                name="name"
                                type="text"
                                placeholder="Ej. Primer Parcial"
                                value="{{ old('name') }}"
                                required
                            />
                        </x-form.group>

                        <x-form.group name="starts_at" label="Fecha de inicio" required>
                            <x-form.input
                                id="starts_at"
                                name="starts_at"
                                type="date"
                                value="{{ old('starts_at', now()->format('Y-m-d')) }}"
                                required
                            />
                        </x-form.group>

                        <x-form.group name="ends_at" label="Fecha de cierre" required>
                            <x-form.input
                                id="ends_at"
                                name="ends_at"
                                type="date"
                                value="{{ old('ends_at', now()->addMonth()->format('Y-m-d')) }}"
                                required
                            />
                        </x-form.group>
                    </div>

                    {{-- Configuración de Ponderaciones (RF-CAL-004, RF-CAL-005) --}}
                    <div class="bg-acode-surface/60 p-4 rounded-xl border border-slate-200 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-acode-primary">Ponderaciones de rubros del periodo (%)</p>
                                <p class="text-xs text-slate-500">La suma de los cuatro rubros debe ser exactamente 100 %.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-slate-500">Suma total:</span>
                                <span
                                    class="px-3 py-1 text-xs font-mono font-bold rounded-full border"
                                    :class="isValid
                                        ? 'bg-acode-accent/15 text-acode-primary border-acode-accent/40'
                                        : 'bg-acode-danger/10 text-acode-danger border-acode-danger/30'"
                                    x-text="total.toFixed(1) + ' %'"
                                ></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <x-form.group name="exams_weight" label="Exámenes (%)" required>
                                <x-form.input
                                    id="exams_weight"
                                    name="exams_weight"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="100"
                                    x-model.number="exams"
                                    required
                                />
                            </x-form.group>

                            <x-form.group name="homework_weight" label="Tareas (%)" required>
                                <x-form.input
                                    id="homework_weight"
                                    name="homework_weight"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="100"
                                    x-model.number="homework"
                                    required
                                />
                            </x-form.group>

                            <x-form.group name="project_weight" label="Proyectos (%)" required>
                                <x-form.input
                                    id="project_weight"
                                    name="project_weight"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="100"
                                    x-model.number="project"
                                    required
                                />
                            </x-form.group>

                            <x-form.group name="other_weight" label="Otros (%)" required>
                                <x-form.input
                                    id="other_weight"
                                    name="other_weight"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="100"
                                    x-model.number="other"
                                    required
                                />
                            </x-form.group>
                        </div>

                        <p x-show="!isValid" x-cloak class="text-xs font-medium text-acode-danger flex items-center gap-1.5">
                            <x-icon name="warning" class="w-4 h-4 shrink-0 text-acode-danger" />
                            <span>Atención: ajusta los valores para que la suma resulte exactamente 100.0 %.</span>
                        </p>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2.5 pt-2 border-t border-slate-100">
                        <x-button type="button" @click="openCreate = false" variant="neutral" size="md">
                            Cancelar
                        </x-button>
                        <x-button type="submit" variant="primary" size="md" ::disabled="!isValid">
                            <x-icon name="save" class="w-4 h-4" />
                            Guardar periodo
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    @endif

    {{-- Nivel 4: Listado de Periodos Configurados (sin doble borde de x-card) --}}
    <div class="space-y-3">
        <x-section-header
            title="Periodos registrados"
            subtitle="Consulta los parciales del ciclo lectivo y captura calificaciones por periodo."
        >
            <x-slot:actions>
                <span class="text-xs font-medium text-slate-500">
                    {{ $periods->count() }} {{ $periods->count() === 1 ? 'periodo registrado' : 'periodos registrados' }}
                </span>
            </x-slot:actions>
        </x-section-header>

        <x-table>
            <x-slot:header>
                <x-table.th>Periodo</x-table.th>
                <x-table.th>Vigencia</x-table.th>
                <x-table.th>Ponderaciones por rubro</x-table.th>
                <x-table.th align="center">Calificados</x-table.th>
                <x-table.th align="right">Acciones</x-table.th>
            </x-slot:header>

            @forelse ($periods as $period)
                @php
                    $weights = $period->parsed_weights;
                @endphp
                <tr>
                    <x-table.td>
                        <div class="font-bold text-acode-primary">{{ $period->name }}</div>
                        @if ($period->is_active_today)
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-acode-accent mt-0.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-acode-accent"></span>
                                Activo actualmente
                            </span>
                        @endif
                    </x-table.td>

                    <x-table.td>
                        <div class="text-xs font-medium text-slate-700">
                            {{ $period->starts_at?->format('d/m/Y') ?? 'Sin inicio' }} &mdash; {{ $period->ends_at?->format('d/m/Y') ?? 'Sin cierre' }}
                        </div>
                    </x-table.td>

                    <x-table.td>
                        @if (!empty($weights))
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if (isset($weights['exams']))
                                    <span class="inline-flex items-center rounded-md border border-acode-tech/25 bg-acode-tech/10 px-2 py-0.5 text-xs font-semibold text-acode-tech">
                                        Exámenes: {{ number_format($weights['exams'], 0) }} %
                                    </span>
                                @endif
                                @if (isset($weights['homework']))
                                    <span class="inline-flex items-center rounded-md border border-acode-accent/30 bg-acode-accent/10 px-2 py-0.5 text-xs font-semibold text-acode-accent-dark">
                                        Tareas: {{ number_format($weights['homework'], 0) }} %
                                    </span>
                                @endif
                                @if (isset($weights['project']))
                                    <span class="inline-flex items-center rounded-md border border-acode-warning/40 bg-acode-warning/15 px-2 py-0.5 text-xs font-semibold text-acode-warning-dark">
                                        Proyectos: {{ number_format($weights['project'], 0) }} %
                                    </span>
                                @endif
                                @if (isset($weights['other']))
                                    <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                        Otros: {{ number_format($weights['other'], 0) }} %
                                    </span>
                                @endif
                            </div>
                        @else
                            <code class="px-2 py-0.5 bg-acode-surface border border-slate-200 rounded text-xs text-acode-primary font-mono select-all">
                                {{ $period->formula }}
                            </code>
                        @endif
                    </x-table.td>

                    <x-table.td align="center">
                        <x-badge :variant="$period->student_grades_count > 0 ? 'accent' : 'neutral'">
                            {{ $period->student_grades_count }} {{ $period->student_grades_count === 1 ? 'alumno' : 'alumnos' }}
                        </x-badge>
                    </x-table.td>

                    <x-table.td align="right">
                        <x-button :href="route('groups.periods.gradebook', [$group, $period])" variant="primary" size="sm">
                            <x-icon name="document" class="w-4 h-4" />
                            Abrir calificaciones
                        </x-button>
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-0">
                        <x-empty-state
                            class="border-0 shadow-none rounded-none py-10"
                            icon="clock"
                            title="No hay periodos de evaluación definidos"
                            message="Define el primer parcial o periodo escolar para este grupo y asigna las ponderaciones correspondientes."
                        >
                            @if (!$group->is_closed)
                                <x-button type="button" @click="openCreate = true" variant="primary" size="md">
                                    <x-icon name="add" class="w-4 h-4" />
                                    Crear periodo
                                </x-button>
                            @endif
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>

</div>
@endsection
