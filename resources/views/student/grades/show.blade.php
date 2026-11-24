{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/grades/show.blade.php
    Ubicación:   resources/views/student/grades/show.blade.php
    Descripción: Vista detallada de calificaciones por asignatura y periodos,
                 homologada al UI Kit A-Code (RF-REP-007, RF-REP-008, RF-REP-012;
                 MPW-V3 §7 y §8; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Detalle de Calificaciones · ' . $group->name)

@section('content')
<div class="space-y-6">
    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        :title="$group->name"
        :subtitle="($group->subject ?? 'Asignatura escolar') . ' · Docente titular: ' . ($group->teacher?->full_name ?? 'Docente')"
        :breadcrumbs="[
            ['label' => 'Mis Calificaciones', 'href' => route('student.grades.index')],
            ['label' => $group->name],
        ]"
    >
        <x-slot:badge>
            <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                {{ $group->is_closed ? 'Cerrado' : 'Activo' }}
            </x-badge>
        </x-slot:badge>

        <x-slot:actions>
            <x-button :href="route('student.grades.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Periodos de Evaluación --}}
    <div class="space-y-4">
        <x-section-header
            title="Periodos de Evaluación Oficiales"
            subtitle="Desglose ponderado de cada parcial registrado en la asignatura."
        />

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($group->evaluationPeriods as $period)
                @php
                    $grade = $period->studentGrades->first();
                @endphp
                <x-card padding="p-5" class="space-y-3">
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-acode-primary">{{ $period->name }}</h3>
                        @if ($grade && $grade->final_grade !== null)
                            <span class="font-mono text-lg font-bold {{ (float) $grade->final_grade >= 6.0 ? 'text-acode-accent-dark' : 'text-acode-danger' }}">
                                {{ number_format((float) $grade->final_grade, 1) }}
                            </span>
                        @else
                            <x-badge variant="neutral">Pendiente</x-badge>
                        @endif
                    </div>

                    <p class="text-xs text-slate-500">
                        Fórmula de ponderación: <code class="font-mono text-slate-700">{{ $period->formula ?? 'Estándar' }}</code>
                    </p>

                    @if ($grade)
                        <div class="space-y-1.5 border-t border-slate-100 pt-3 text-xs text-slate-600">
                            @if ($grade->exams_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Examen:</span>
                                    <span class="font-mono font-semibold text-slate-800">{{ $grade->exams_component }}</span>
                                </div>
                            @endif
                            @if ($grade->homework_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Tareas:</span>
                                    <span class="font-mono font-semibold text-slate-800">{{ $grade->homework_component }}</span>
                                </div>
                            @endif
                            @if ($grade->project_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Proyecto:</span>
                                    <span class="font-mono font-semibold text-slate-800">{{ $grade->project_component }}</span>
                                </div>
                            @endif
                            @if ($grade->other_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Otros rubros:</span>
                                    <span class="font-mono font-semibold text-slate-800">{{ $grade->other_component }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </x-card>
            @empty
                <div class="col-span-full">
                    <x-empty-state
                        icon="reportBar"
                        title="Sin periodos de evaluación"
                        message="El profesor no ha definido periodos ni ponderaciones para este grupo."
                    />
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
