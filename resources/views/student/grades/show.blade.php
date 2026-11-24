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
        :institutional="true"
        :breadcrumbs="[
            ['label' => 'Mis Calificaciones', 'href' => route('student.grades.index')],
            ['label' => 'Detalle de Calificaciones'],
        ]"
    >
        <x-slot:actions>
            <x-button :href="route('student.grades.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($group->evaluationPeriods->contains(fn ($p) => $p->group_final_weight !== null))
        <section class="space-y-3">
            <x-section-header title="Calificación final del grupo" :institutional="true" />
            <x-card>
                @if (isset($final))
                    <p class="text-lg font-semibold text-acode-primary">Definitiva · {{ $group->formatGrade($final) }} · {{ $final >= $group->passing_score ? 'Aprobada' : 'No aprobada' }}</p>
                    <p class="mt-1 text-sm text-slate-700">Escala de evaluación: {{ $group->grading_scale }}</p>
                @else
                    <x-badge variant="neutral">Pendiente</x-badge>
                @endif
            </x-card>
        </section>
    @endif
    {{-- Nivel 2: Periodos de Evaluación --}}
    <div class="space-y-4">
        <x-section-header
            title="Periodos de Evaluación Oficiales"
            :subtitle="$group->grading_scale ? 'Escala de evaluación: ' . $group->grading_scale : null"
            :institutional="true"
        />

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($group->evaluationPeriods as $period)
                @php
                    $grade = $period->studentGrades->first();
                @endphp
                <x-card padding="p-5" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div class="min-w-0">
                            <h3 class="break-words text-base font-semibold text-acode-primary">{{ $period->name }}</h3>
                            @if ($period->starts_at && $period->ends_at)
                                <p class="mt-1 text-xs text-slate-600">{{ $period->starts_at->format('d/m/Y') }} – {{ $period->ends_at->format('d/m/Y') }}</p>
                            @endif
                        </div>
                        @if ($grade && $grade->final_grade !== null)
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-base font-semibold text-acode-primary">{{ $group->formatGrade($grade->final_grade, 1) }}</span>
                                @if ($group->passing_score !== null)
                                    @php($isPassing = (float) $grade->final_grade >= (float) $group->passing_score)
                                    <x-badge :variant="$isPassing ? 'accent' : 'danger'" :class="$isPassing ? '' : '!text-acode-danger-dark'">
                                        {{ $isPassing ? 'Aprobado' : 'Reprobado' }}
                                    </x-badge>
                                @endif
                            </div>
                        @else
                            <x-badge variant="neutral">Pendiente</x-badge>
                        @endif
                    </div>

                    <p class="break-words text-xs text-slate-700">
                        Fórmula de ponderación: <code class="bg-slate-100 font-mono text-[13px] leading-5 text-acode-ink">{{ $period->formula ?? 'Estándar' }}</code>
                    </p>

                    @if ($grade)
                        <div class="space-y-1.5 border-t border-slate-100 pt-3 text-xs text-slate-600">
                            @if ($grade->exams_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Examen:</span>
                                    <span class="font-mono font-semibold text-slate-800">{{ $group->formatGrade($grade->exams_component) }}</span>
                                </div>
                            @endif
                            @if ($grade->homework_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Tareas:</span>
                                    <span class="font-mono font-semibold text-slate-800">{{ $group->formatGrade($grade->homework_component) }}</span>
                                </div>
                            @endif
                            @if ($grade->project_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Proyecto:</span>
                                    <span class="font-mono font-semibold text-slate-800">{{ $group->formatGrade($grade->project_component) }}</span>
                                </div>
                            @endif
                            @if ($grade->other_component !== null)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Otros rubros:</span>
                                    <span class="font-mono font-semibold text-slate-800">{{ $group->formatGrade($grade->other_component) }}</span>
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

    @include('student.grades.partials._attempts', ['attempts' => $groupAttempts, 'showGroup' => false])
</div>
@endsection
