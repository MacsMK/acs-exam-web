{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/evaluation-periods/gradebook.blade.php
    Ubicación:   resources/views/evaluation-periods/gradebook.blade.php
    Descripción: Sábana interactiva de captura y cálculo ponderado de
                 calificaciones por periodo escolar con exportación Excel (.xlsx),
                 protección ante cambios sin guardar y tokens oficiales acode-*
                 (RF-CAL-009, RF-CAL-012, RF-CAL-017, RF-CAL-021, RF-CAL-027,
                 RW-28..31, MPW-V3 §7.3, §7.4.2, §8.1–§8.12).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Control de Calificaciones - ' . $period->name . ' - ' . $group->name)

@section('content')
@php
    $weightsTotal = round((float) array_sum($weights), 1);
@endphp

<div class="space-y-6">

    {{-- Nivel 1: Encabezado Institucional con Breadcrumbs y Acciones --}}
    <x-page-header
        title="Control de Calificaciones"
        :subtitle="'Periodo: ' . $period->name . ' · Grupo: ' . $group->name . ' (' . $period->starts_at->format('d/m/Y') . ' — ' . $period->ends_at->format('d/m/Y') . ').'"
        :breadcrumbs="[
            ['label' => 'Grupos', 'href' => route('groups.index')],
            ['label' => $group->name, 'href' => route('groups.show', $group)],
            ['label' => 'Periodos', 'href' => route('groups.periods.index', $group)],
            ['label' => $period->name],
        ]"
    >
        <x-slot:actions>
            <x-button :href="route('groups.periods.index', $group)" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>

            <x-button :href="route('groups.periods.gradebook.export', [$group, $period])" variant="secondary" size="md">
                <x-icon name="downloadReport" class="w-4 h-4" />
                Descargar reporte Excel
            </x-button>

            <x-button type="submit" form="gradebook-form" variant="primary" size="md">
                <x-icon name="save" class="w-4 h-4" />
                Guardar calificaciones
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-4 text-sm font-medium text-acode-danger">
            <div class="flex items-start gap-2.5">
                <x-icon name="error" class="w-5 h-5 shrink-0 mt-0.5" />
                <div>
                    <p class="font-bold mb-1">Por favor corrige los siguientes errores:</p>
                    <ul class="list-disc list-inside text-xs space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Nivel 2: Tarjeta de Fórmula y Ponderaciones Oficiales en Tokens acode-* --}}
    <x-card>
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold text-slate-500 mb-1.5">Fórmula activa del periodo</p>
                <code class="inline-block px-3 py-1.5 bg-acode-surface border border-slate-200 rounded-lg font-mono text-xs text-acode-primary font-bold select-all">
                    {{ $period->formula }}
                </code>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                <div class="px-3.5 py-2 rounded-xl border border-acode-tech/25 bg-acode-tech/10 text-center">
                    <span class="block text-xs font-semibold text-acode-tech">Exámenes</span>
                    <span class="text-sm font-extrabold text-acode-primary font-mono">{{ $weights['exams'] }} %</span>
                </div>

                <div class="px-3.5 py-2 rounded-xl border border-acode-accent/30 bg-acode-accent/10 text-center">
                    <span class="block text-xs font-semibold text-acode-accent-dark">Tareas</span>
                    <span class="text-sm font-extrabold text-acode-primary font-mono">{{ $weights['homework'] }} %</span>
                </div>

                <div class="px-3.5 py-2 rounded-xl border border-acode-warning/40 bg-acode-warning/15 text-center">
                    <span class="block text-xs font-semibold text-acode-warning-dark">Proyectos</span>
                    <span class="text-sm font-extrabold text-acode-primary font-mono">{{ $weights['project'] }} %</span>
                </div>

                <div class="px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-100 text-center">
                    <span class="block text-xs font-semibold text-slate-600">Otros</span>
                    <span class="text-sm font-extrabold text-acode-primary font-mono">{{ $weights['other'] }} %</span>
                </div>

                <div class="col-span-2 sm:col-span-1 px-3.5 py-2 rounded-xl border border-acode-primary/25 bg-acode-primary/10 text-center">
                    <span class="block text-xs font-semibold text-acode-primary">Total</span>
                    <span class="text-sm font-extrabold text-acode-primary font-mono">{{ $weightsTotal }} %</span>
                </div>
            </div>
        </div>
    </x-card>

    {{-- Nivel 3: Formulario de Captura de Calificaciones (sin doble borde de x-card) --}}
    <form id="gradebook-form" method="POST" action="{{ route('groups.periods.gradebook.save', [$group, $period]) }}" class="space-y-4">
        @csrf

        <x-section-header
            :title="'Listado de alumnos (' . $students->count() . ')'"
            subtitle="Escala de 0 a 100 · Dejar vacío un campo registra el rubro como «Pendiente»."
        />

        <x-table>
            <x-slot:header>
                <x-table.th class="sticky left-0 bg-acode-primary z-20">Alumno</x-table.th>
                <x-table.th align="center">Exámenes ({{ $weights['exams'] }} %)</x-table.th>
                <x-table.th align="center">Tareas ({{ $weights['homework'] }} %)</x-table.th>
                <x-table.th align="center">Proyectos ({{ $weights['project'] }} %)</x-table.th>
                <x-table.th align="center">Otros ({{ $weights['other'] }} %)</x-table.th>
                <x-table.th align="right">Promedio final</x-table.th>
            </x-slot:header>

            @forelse ($students as $index => $student)
                @php
                    $grade = $savedGrades->get($student->id);
                    $examAvg = $examAverages->get($student->id);
                @endphp
                <tr class="group">
                    <x-table.td class="sticky left-0 z-10 border-r border-slate-200 shadow-sm {{ $loop->even ? 'bg-acode-surface' : 'bg-white' }} group-hover:bg-acode-surface">
                        <input type="hidden" name="grades[{{ $index }}][student_id]" value="{{ $student->id }}" />
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-acode-primary flex items-center justify-center font-bold text-xs text-white shrink-0">
                                {{ strtoupper(substr($student->first_name ?? $student->full_name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="font-bold text-acode-primary text-sm">{{ $student->full_name }}</div>
                                <div class="text-xs text-slate-500">{{ $student->email }}</div>
                            </div>
                        </div>
                    </x-table.td>

                    {{-- Componente Exámenes --}}
                    <x-table.td align="center">
                        <div class="flex flex-col items-center gap-1">
                            <input
                                id="exams_{{ $student->id }}"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                name="grades[{{ $index }}][exams_component]"
                                value="{{ old("grades.{$index}.exams_component", $grade?->exams_component) }}"
                                placeholder="&mdash;"
                                class="w-24 h-8 px-2.5 text-center font-mono text-sm rounded-lg border border-slate-300 bg-white text-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech"
                            />
                            @if ($examAvg !== null && $grade?->exams_component === null)
                                <button
                                    type="button"
                                    onclick="const input = document.getElementById('exams_{{ $student->id }}'); input.value = '{{ $examAvg }}'; input.dispatchEvent(new Event('input', { bubbles: true }));"
                                    class="text-xs font-medium text-acode-tech hover:underline cursor-pointer"
                                    title="Auto-llenar con el promedio de intentos reales del alumno en el grupo"
                                >
                                    Sugerido: {{ $examAvg }}
                                </button>
                            @endif
                        </div>
                    </x-table.td>

                    {{-- Componente Tareas --}}
                    <x-table.td align="center">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            name="grades[{{ $index }}][homework_component]"
                            value="{{ old("grades.{$index}.homework_component", $grade?->homework_component) }}"
                            placeholder="&mdash;"
                            class="w-24 h-8 px-2.5 text-center font-mono text-sm rounded-lg border border-slate-300 bg-white text-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech"
                        />
                    </x-table.td>

                    {{-- Componente Proyectos --}}
                    <x-table.td align="center">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            name="grades[{{ $index }}][project_component]"
                            value="{{ old("grades.{$index}.project_component", $grade?->project_component) }}"
                            placeholder="&mdash;"
                            class="w-24 h-8 px-2.5 text-center font-mono text-sm rounded-lg border border-slate-300 bg-white text-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech"
                        />
                    </x-table.td>

                    {{-- Componente Otros --}}
                    <x-table.td align="center">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            name="grades[{{ $index }}][other_component]"
                            value="{{ old("grades.{$index}.other_component", $grade?->other_component) }}"
                            placeholder="&mdash;"
                            class="w-24 h-8 px-2.5 text-center font-mono text-sm rounded-lg border border-slate-300 bg-white text-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech"
                        />
                    </x-table.td>

                    {{-- Calificación Final Calculada --}}
                    <x-table.td align="right">
                        @if ($grade?->final_grade !== null)
                            <x-badge :variant="(float) $grade->final_grade >= 60 ? 'accent' : 'danger'" class="font-mono font-bold">
                                {{ number_format((float) $grade->final_grade, 2) }}
                            </x-badge>
                        @else
                            <span class="text-xs text-slate-400 italic font-mono">Pendiente</span>
                        @endif
                    </x-table.td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-0">
                        <x-empty-state
                            class="border-0 shadow-none rounded-none py-10"
                            icon="userGroup"
                            title="No hay alumnos matriculados en este grupo"
                            message="Comparte el folio de acceso con tus alumnos para que puedan unirse y calificar sus actividades."
                        />
                    </td>
                </tr>
            @endforelse
        </x-table>

        @if ($students->isNotEmpty())
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs text-slate-500">
                    Los promedios se calculan automáticamente con el motor aritmético seguro al presionar Guardar calificaciones.
                </p>

                <div class="flex flex-wrap items-center gap-3">
                    <x-unsaved-changes
                        form-id="gradebook-form"
                        :cancel-url="route('groups.periods.index', $group)"
                    />

                    <x-button type="submit" variant="primary" size="md">
                        <x-icon name="save" class="w-4 h-4" />
                        Guardar calificaciones
                    </x-button>
                </div>
            </div>
        @endif
    </form>

</div>
@endsection
