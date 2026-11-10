{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: evaluation-periods/gradebook.blade.php (RF-CAL-009, RF-CAL-012, RF-CAL-017, RF-CAL-021, RF-CAL-027, RW-28..31) --}}
@extends('layouts.app')

@section('title', 'Control de Calificaciones - ' . $period->name . ' - ' . $group->name)

@section('content')
<div class="space-y-6">

    {{-- Encabezado de Página --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
                <a href="{{ route('groups.index') }}" class="hover:text-acode-tech">Grupos</a>
                <span>/</span>
                <a href="{{ route('groups.show', $group) }}" class="hover:text-acode-tech">{{ $group->name }}</a>
                <span>/</span>
                <a href="{{ route('groups.periods.index', $group) }}" class="hover:text-acode-tech">Periodos</a>
                <span>/</span>
                <span class="text-acode-primary">Control: {{ $period->name }}</span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">Control de Calificaciones</h1>
            <p class="text-sm text-slate-600 mt-1">
                Periodo: <strong>{{ $period->name }}</strong> &bull; Grupo: <strong>{{ $group->name }}</strong>
                ({{ $period->starts_at->format('d/m/Y') }} &mdash; {{ $period->ends_at->format('d/m/Y') }})
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-button :href="route('groups.periods.index', $group)" variant="neutral" size="sm">
                <x-icon name="chevronDown" class="w-3.5 h-3.5 rotate-90 mr-1" />
                Volver
            </x-button>
            <x-button :href="route('groups.periods.gradebook.export', [$group, $period])" variant="secondary" size="sm">
                <x-icon name="documentText" class="w-4 h-4 mr-1.5 text-emerald-600" />
                Descargar reporte Excel
            </x-button>
            <x-button type="submit" form="gradebook-form" variant="primary" size="sm">
                <x-icon name="check" class="w-4 h-4 mr-1.5" />
                Guardar Calificaciones
            </x-button>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm font-medium">
            <p class="font-bold mb-1">Por favor corrige los siguientes errores:</p>
            <ul class="list-disc list-inside text-xs space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Tarjeta de Ponderaciones y Reglas del Periodo (RF-CAL-004, RF-CAL-021) --}}
    <x-card class="bg-gradient-to-r from-slate-50 to-white">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Fórmula Activa del Periodo</p>
                <code class="px-2.5 py-1 bg-white border border-slate-300 rounded font-mono text-xs text-acode-tech font-bold select-all">
                    {{ $period->formula }}
                </code>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="px-3 py-1.5 bg-blue-50 border border-blue-200 rounded text-center">
                    <span class="block text-[10px] font-semibold uppercase text-blue-700">Exámenes</span>
                    <span class="text-sm font-bold text-blue-900 font-mono">{{ $weights['exams'] }} %</span>
                </div>
                <div class="px-3 py-1.5 bg-indigo-50 border border-indigo-200 rounded text-center">
                    <span class="block text-[10px] font-semibold uppercase text-indigo-700">Tareas</span>
                    <span class="text-sm font-bold text-indigo-900 font-mono">{{ $weights['homework'] }} %</span>
                </div>
                <div class="px-3 py-1.5 bg-purple-50 border border-purple-200 rounded text-center">
                    <span class="block text-[10px] font-semibold uppercase text-purple-700">Proyectos</span>
                    <span class="text-sm font-bold text-purple-900 font-mono">{{ $weights['project'] }} %</span>
                </div>
                <div class="px-3 py-1.5 bg-slate-100 border border-slate-300 rounded text-center">
                    <span class="block text-[10px] font-semibold uppercase text-slate-700">Otros</span>
                    <span class="text-sm font-bold text-slate-900 font-mono">{{ $weights['other'] }} %</span>
                </div>
            </div>
        </div>
    </x-card>

    {{-- Formulario de Captura de Control de Calificaciones (RF-CAL-009, RF-CAL-027) --}}
    <form id="gradebook-form" method="POST" action="{{ route('groups.periods.gradebook.save', [$group, $period]) }}">
        @csrf

        <x-card>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-acode-primary">Listado de Alumnos ({{ $students->count() }})</h2>
                <span class="text-xs text-slate-500">Escala de 0 a 100 &bull; Vacío = «Pendiente» (RF-CAL-021)</span>
            </div>

            <x-table>
                <x-slot:header>
                    <x-table.th class="sticky left-0 bg-acode-primary z-20">Alumno</x-table.th>
                    <x-table.th align="center">Exámenes ({{ $weights['exams'] }}%)</x-table.th>
                    <x-table.th align="center">Tareas ({{ $weights['homework'] }}%)</x-table.th>
                    <x-table.th align="center">Proyectos ({{ $weights['project'] }}%)</x-table.th>
                    <x-table.th align="center">Otros ({{ $weights['other'] }}%)</x-table.th>
                    <x-table.th align="right">Promedio Final</x-table.th>
                </x-slot:header>

                @forelse ($students as $index => $student)
                    @php
                        $grade = $savedGrades->get($student->id);
                        $examAvg = $examAverages->get($student->id);
                    @endphp
                    <tr>
                        <x-table.td class="sticky left-0 bg-white z-10 border-r border-slate-200 shadow-sm">
                            <input type="hidden" name="grades[{{ $index }}][student_id]" value="{{ $student->id }}" />
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-acode-surface flex items-center justify-center font-bold text-xs text-acode-tech shrink-0">
                                    {{ strtoupper(substr($student->first_name ?? $student->full_name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 text-sm">{{ $student->full_name }}</div>
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
                                    class="w-20 px-2 py-1 text-center font-mono text-sm border border-slate-300 rounded focus:ring-1 focus:ring-acode-tech focus:border-acode-tech"
                                />
                                @if ($examAvg !== null && $grade?->exams_component === null)
                                    <button
                                        type="button"
                                        onclick="document.getElementById('exams_{{ $student->id }}').value = '{{ $examAvg }}'"
                                        class="text-[10px] text-acode-tech hover:underline cursor-pointer flex items-center gap-0.5"
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
                                class="w-20 px-2 py-1 text-center font-mono text-sm border border-slate-300 rounded focus:ring-1 focus:ring-acode-tech focus:border-acode-tech"
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
                                class="w-20 px-2 py-1 text-center font-mono text-sm border border-slate-300 rounded focus:ring-1 focus:ring-acode-tech focus:border-acode-tech"
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
                                class="w-20 px-2 py-1 text-center font-mono text-sm border border-slate-300 rounded focus:ring-1 focus:ring-acode-tech focus:border-acode-tech"
                            />
                        </x-table.td>

                        {{-- Calificación Final Calculada --}}
                        <x-table.td align="right">
                            @if ($grade?->final_grade !== null)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-mono font-bold {{ (float) $grade->final_grade >= 60 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ number_format((float) $grade->final_grade, 2) }}
                                </span>
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
                <div class="flex items-center justify-between pt-4 mt-4 border-t border-slate-100">
                    <p class="text-xs text-slate-500">
                        Los promedios se calculan automáticamente con el motor aritmético seguro al presionar Guardar.
                    </p>
                    <x-button type="submit" variant="primary" size="md">
                        <x-icon name="check" class="w-4 h-4 mr-1.5" />
                        Guardar Calificaciones
                    </x-button>
                </div>
            @endif
        </x-card>
    </form>
</div>
@endsection
