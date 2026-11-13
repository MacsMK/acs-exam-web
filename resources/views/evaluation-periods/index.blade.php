{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/evaluation-periods/index.blade.php
    Ubicación:   resources/views/evaluation-periods/index.blade.php
    Descripción: Gestión operativa y configuración de periodos de evaluación (parciales)
                 y ponderaciones porcentuales por grupo escolar con cálculo matemático
                 al 100% en tiempo real (RF-CAL-001..005, RW-28..31, MPW-V2 §7, §8).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Periodos de Evaluación - ' . $group->name)

@section('content')
<div x-data="{
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
}" class="space-y-6">

    {{-- Nivel 1: Hero Institucional con Breadcrumb y Acciones Oficiales --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
            <div>
                {{-- Migas de pan institucionales --}}
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">
                    <a href="{{ route('groups.index') }}" class="hover:text-acode-tech transition">Grupos</a>
                    <span>/</span>
                    <a href="{{ route('groups.show', $group) }}" class="hover:text-acode-tech transition">{{ $group->name }}</a>
                    <span>/</span>
                    <span class="text-acode-primary">Periodos de Evaluación</span>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl sm:text-3xl font-bold text-acode-primary tracking-tight">
                        Periodos de Evaluación
                    </h1>
                    <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                        {{ $group->is_closed ? 'Grupo Cerrado' : 'Grupo Activo' }}
                    </x-badge>
                </div>

                <p class="mt-2 text-sm text-slate-500 leading-relaxed max-w-3xl">
                    Configuración de parciales, ponderaciones porcentuales y control de notas del grupo
                    <strong class="text-acode-primary">{{ $group->name }}</strong>
                    @if ($group->subject)
                        ({{ $group->subject }})
                    @endif.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <x-button :href="route('groups.show', $group)" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4 mr-1.5" />
                    Volver al Grupo
                </x-button>

                @if (!$group->is_closed)
                    <x-button type="button" @click="openCreate = !openCreate" variant="primary" size="md">
                        <x-icon name="add" class="w-4 h-4 mr-1.5" />
                        Crear periodo
                    </x-button>
                @else
                    <span class="inline-flex items-center rounded-lg bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-600 border border-slate-200">
                        <x-icon name="lock" class="w-4 h-4 mr-1.5 text-slate-500" />
                        Ciclo escolar cerrado
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Nivel 2: Barra de 3 Tarjetas KPI Contextuales --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        {{-- KPI 1: Periodos configurados --}}
        <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-icon name="clock" class="w-5 h-5 text-acode-tech" />
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Periodos registrados
                </span>
            </div>
            <div class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-2xl lg:text-3xl font-extrabold text-acode-primary tracking-tight">
                    {{ $periods->count() }}
                </p>
                <p class="text-xs text-slate-500">
                    Parciales configurados
                </p>
            </div>
        </div>

        {{-- KPI 2: Periodo en curso / vigencia --}}
        @php
            $activePeriod = $periods->first(fn ($p) => $p->is_active_today);
        @endphp
        <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $activePeriod ? 'bg-acode-accent/15 text-acode-accent' : 'bg-slate-100 text-slate-500' }}">
                    <x-icon name="check" class="w-5 h-5" />
                </div>
                <x-badge :variant="$activePeriod ? 'accent' : 'neutral'">
                    {{ $activePeriod ? 'En curso hoy' : 'Sin periodo activo' }}
                </x-badge>
            </div>
            <div class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-lg lg:text-xl font-bold text-acode-primary truncate">
                    {{ $activePeriod ? $activePeriod->name : 'Fuera de ventana' }}
                </p>
                <p class="text-xs text-slate-500">
                    {{ $activePeriod ? $activePeriod->starts_at->format('d/m/Y') . ' al ' . $activePeriod->ends_at->format('d/m/Y') : 'Verifica las fechas del ciclo' }}
                </p>
            </div>
        </div>

        {{-- KPI 3: Alumnos evaluados en el grupo --}}
        <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-icon name="userGroup" class="w-5 h-5 text-acode-tech" />
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Matrícula evaluada
                </span>
            </div>
            <div class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="text-2xl lg:text-3xl font-extrabold text-acode-primary tracking-tight">
                    {{ $periods->sum('student_grades_count') }}
                </p>
                <p class="text-xs text-slate-500">
                    Calificaciones consolidadas
                </p>
            </div>
        </div>
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
            <x-card class="border-2 border-acode-tech/30 shadow-md">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                    <h2 class="text-base font-bold text-acode-primary flex items-center gap-2">
                        <x-icon name="clock" class="w-5 h-5 text-acode-tech" />
                        Registrar Nuevo Periodo de Evaluación
                    </h2>
                    <button type="button" @click="openCreate = false" class="text-slate-400 hover:text-slate-600 rounded-lg p-1 transition" aria-label="Cerrar formulario">
                        <x-icon name="xMark" class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" action="{{ route('groups.periods.store', $group) }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="name" class="block text-xs font-semibold text-slate-700 mb-1">Nombre del Periodo <span class="text-rose-600">*</span></label>
                            <x-form.input id="name" name="name" type="text" placeholder="Ej: Primer Parcial" value="{{ old('name') }}" required />
                            @error('name') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="starts_at" class="block text-xs font-semibold text-slate-700 mb-1">Fecha de Inicio <span class="text-rose-600">*</span></label>
                            <x-form.input id="starts_at" name="starts_at" type="date" value="{{ old('starts_at', now()->format('Y-m-d')) }}" required />
                            @error('starts_at') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="ends_at" class="block text-xs font-semibold text-slate-700 mb-1">Fecha de Cierre <span class="text-rose-600">*</span></label>
                            <x-form.input id="ends_at" name="ends_at" type="date" value="{{ old('ends_at', now()->addMonth()->format('Y-m-d')) }}" required />
                            @error('ends_at') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Configuración de Ponderaciones (RF-CAL-004, RF-CAL-005) --}}
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 mt-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                            <div>
                                <p class="text-sm font-bold text-slate-800">Ponderaciones de Rubros del Periodo (%)</p>
                                <p class="text-xs text-slate-500">La suma de los cuatro rubros debe ser exactamente 100 % (RF-CAL-005).</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold uppercase text-slate-500">Suma total:</span>
                                <span
                                    class="px-3 py-1 text-xs font-mono font-bold rounded-full"
                                    :class="isValid ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300'"
                                    x-text="total.toFixed(1) + ' %'"
                                ></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label for="exams_weight" class="block text-xs font-semibold text-slate-700 mb-1">Exámenes (%) <span class="text-rose-600">*</span></label>
                                <x-form.input id="exams_weight" name="exams_weight" type="number" step="0.5" min="0" max="100" x-model.number="exams" required />
                                @error('exams_weight') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="homework_weight" class="block text-xs font-semibold text-slate-700 mb-1">Tareas (%) <span class="text-rose-600">*</span></label>
                                <x-form.input id="homework_weight" name="homework_weight" type="number" step="0.5" min="0" max="100" x-model.number="homework" required />
                                @error('homework_weight') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="project_weight" class="block text-xs font-semibold text-slate-700 mb-1">Proyectos (%) <span class="text-rose-600">*</span></label>
                                <x-form.input id="project_weight" name="project_weight" type="number" step="0.5" min="0" max="100" x-model.number="project" required />
                                @error('project_weight') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="other_weight" class="block text-xs font-semibold text-slate-700 mb-1">Otros (%) <span class="text-rose-600">*</span></label>
                                <x-form.input id="other_weight" name="other_weight" type="number" step="0.5" min="0" max="100" x-model.number="other" required />
                                @error('other_weight') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <p x-show="!isValid" class="text-xs font-medium text-rose-600 mt-3 flex items-center gap-1.5">
                            <x-icon name="warning" class="w-4 h-4 shrink-0 text-rose-600" />
                            <span>Atención: Ajusta los valores para que la suma resulte exactamente 100.0 %.</span>
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" @click="openCreate = false" variant="neutral" size="sm">
                            Cancelar
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm" ::disabled="!isValid">
                            Guardar Periodo
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    @endif

    {{-- Nivel 4: Listado de Periodos Configurados (RF-CAL-002) --}}
    <x-card>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-acode-primary">Periodos Registrados</h2>
                <p class="text-xs text-slate-500 mt-0.5">Consulta los parciales del ciclo lectivo y captura calificaciones por periodo.</p>
            </div>
            <span class="text-xs font-medium text-slate-500">{{ $periods->count() }} periodo(s) activo(s)</span>
        </div>

        <x-table>
            <x-slot:header>
                <x-table.th>Periodo</x-table.th>
                <x-table.th>Vigencia</x-table.th>
                <x-table.th>Ponderaciones por Rubro</x-table.th>
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
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-acode-accent mt-0.5">
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
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-acode-tech border border-blue-200/60">
                                        Exámenes: {{ number_format($weights['exams'], 0) }}%
                                    </span>
                                @endif
                                @if (isset($weights['homework']))
                                    <span class="inline-flex items-center rounded-md bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700 border border-teal-200/60">
                                        Tareas: {{ number_format($weights['homework'], 0) }}%
                                    </span>
                                @endif
                                @if (isset($weights['project']))
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 border border-amber-200/60">
                                        Proyectos: {{ number_format($weights['project'], 0) }}%
                                    </span>
                                @endif
                                @if (isset($weights['other']))
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 border border-slate-200">
                                        Otros: {{ number_format($weights['other'], 0) }}%
                                    </span>
                                @endif
                            </div>
                        @else
                            <code class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded text-xs text-slate-700 font-mono select-all">
                                {{ $period->formula }}
                            </code>
                        @endif
                    </x-table.td>

                    <x-table.td align="center">
                        <x-badge :variant="$period->student_grades_count > 0 ? 'accent' : 'neutral'">
                            {{ $period->student_grades_count }} alumno(s)
                        </x-badge>
                    </x-table.td>

                    <x-table.td align="right">
                        <x-button :href="route('groups.periods.gradebook', [$group, $period])" variant="primary" size="sm">
                            <x-icon name="document" class="w-4 h-4 mr-1.5" />
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
                                <x-button type="button" @click="openCreate = true" variant="primary" size="sm">
                                    <x-icon name="add" class="w-4 h-4 mr-1.5" />
                                    Crear periodo
                                </x-button>
                            @endif
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
</div>
@endsection
