{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/grades/show.blade.php
    Descripción: Vista detallada de calificaciones por asignatura y periodos.
                 Cumple RF-REP-007, RF-REP-008, RF-REP-012 y MPW-V2 §7 y §8.
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Detalle de Calificaciones · ' . $group->name)

@section('content')
<div class="space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('student.grades.index') }}" class="text-xs font-semibold text-acode-tech hover:underline">
                    ← Volver a Mis Calificaciones
                </a>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">
                {{ $group->name }}
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $group->subject ?? 'Asignatura escolar' }} · Docente titular: {{ $group->teacher?->full_name ?? 'Docente' }}
            </p>
        </div>
    </div>

    {{-- Periodos de Evaluación --}}
    <div class="space-y-4">
        <h2 class="text-lg font-bold text-acode-primary">Periodos de Evaluación Oficiales</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($group->evaluationPeriods as $period)
                @php
                    $grade = $period->studentGrades->first();
                @endphp
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <h3 class="font-bold text-base text-acode-primary">{{ $period->name }}</h3>
                        @if ($grade && $grade->final_grade !== null)
                            <span class="font-bold font-mono text-lg {{ (float) $grade->final_grade >= 6.0 ? 'text-teal-600' : 'text-acode-danger' }}">
                                {{ number_format((float) $grade->final_grade, 1) }}
                            </span>
                        @else
                            <span class="text-xs text-slate-400">Sin calificación</span>
                        @endif
                    </div>

                    <p class="text-xs text-slate-500">Fórmula de ponderación: <code class="font-mono text-slate-700">{{ $period->formula ?? 'Estándar' }}</code></p>

                    @if ($grade)
                        <div class="space-y-1.5 pt-2 border-t border-slate-50 text-xs text-slate-600">
                            @if ($grade->exams_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Examen:</span>
                                    <span class="font-mono font-semibold">{{ $grade->exams_component }}</span>
                                </div>
                            @endif
                            @if ($grade->homework_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Tareas:</span>
                                    <span class="font-mono font-semibold">{{ $grade->homework_component }}</span>
                                </div>
                            @endif
                            @if ($grade->project_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Proyecto:</span>
                                    <span class="font-mono font-semibold">{{ $grade->project_component }}</span>
                                </div>
                            @endif
                            @if ($grade->other_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Otros rubros:</span>
                                    <span class="font-mono font-semibold">{{ $grade->other_component }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
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
