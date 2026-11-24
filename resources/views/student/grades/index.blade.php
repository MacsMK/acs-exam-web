{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/grades/index.blade.php
    Ubicación:   resources/views/student/grades/index.blade.php
    Descripción: Vista de Mis Calificaciones e Historial Académico del estudiante
                 homologada al UI Kit A-Code (RF-REP-003, RF-REP-007, RF-REP-012;
                 MPW-V3 §7.3, §7.4.2, §7.8.2, §8.1–§8.4; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Mis Calificaciones')

@section('content')
<div class="space-y-6">
    <x-page-header
        title="Mis Calificaciones e Historial Académico"
        :institutional="true"
    >
        <x-slot:actions>
            <x-button :href="route('student.exams.index')" variant="secondary" size="md">
                <x-nav-icon name="exams" class="h-4 w-4" />
                Abrir exámenes
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @include('student.grades.partials._attempts', ['attempts' => $recentAttempts, 'showGroup' => true])

    @if ($notices->isNotEmpty())
        <section class="space-y-3">
            <x-section-header title="Avisos de calificación" :institutional="true" />
            <x-card>
                <ul class="space-y-2 text-sm text-slate-700">
                    @foreach ($notices as $notice)
                        <li>Nueva calificación · {{ $notice->new_grade }}</li>
                    @endforeach
                </ul>
                @if ($notices->hasPages())
                    <div class="mt-4">{{ $notices->links() }}</div>
                @endif
            </x-card>
        </section>
    @endif

    @if ($totalEvaluations > 0)
        <x-summary-list>
            <x-summary-item label="Calificaciones publicadas" :value="$totalEvaluations" compact />
            <x-summary-item label="Exámenes aprobados" :value="$passedCount" note="Según el mínimo aplicable a cada examen." compact />
            @if ($averageScore !== null)
                <x-summary-item
                    label="Promedio general"
                    :value="number_format($averageScore, 1) . ' / 10'"
                    note="Calificaciones publicadas convertidas a escala 0–10."
                    compact
                />
            @endif
            @if ($outOfScaleCount > 0)
                <x-summary-item
                    label="Calificaciones fuera de escala"
                    :value="$outOfScaleCount"
                    note="El promedio y el dictamen usan calificaciones dentro de escala."
                    compact
                />
            @endif
        </x-summary-list>
    @endif

    <section class="space-y-3">
        <x-section-header title="Calificaciones por grupo y periodo" :institutional="true" />

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            @forelse ($groups as $group)
                @php($periodNameCounts = $group->evaluationPeriods->countBy('name'))
                <x-card padding="p-5" class="space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
                        <div class="min-w-0">
                            <h3 class="break-words text-base font-semibold text-acode-primary">{{ $group->name }}</h3>
                            <p class="mt-0.5 text-xs text-slate-600">
                                {{ $group->subject ?? 'Materia general' }} · {{ $group->teacher?->full_name ?? 'Docente titular' }}
                            </p>
                            @if ($group->grading_scale)
                                <p class="mt-1 text-xs text-slate-700">Escala de evaluación: {{ $group->grading_scale }}</p>
                            @endif
                        </div>

                        <x-button :href="route('student.grades.show', $group)" variant="secondary" size="sm"
                            :aria-label="'Abrir calificaciones de ' . $group->name">
                            Abrir calificaciones
                        </x-button>
                    </div>

                    @forelse ($group->evaluationPeriods as $period)
                        @php($grade = $period->studentGrades->first())
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3">
                            <div class="min-w-0">
                                <h4 class="break-words text-sm font-semibold text-acode-primary">{{ $period->name }}</h4>
                                @if ($periodNameCounts->get($period->name) > 1)
                                    <p class="mt-1 text-xs text-slate-600">
                                        {{ $period->starts_at?->format('d/m/Y') }} – {{ $period->ends_at?->format('d/m/Y') }}
                                    </p>
                                @endif
                            </div>

                            @if ($grade?->final_grade !== null)
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-sm font-semibold text-acode-primary">{{ $group->formatGrade($grade->final_grade, 1) }}</span>
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
                    @empty
                        <p class="text-sm text-slate-600">El profesor aún no ha configurado periodos de evaluación para este grupo.</p>
                    @endforelse
                </x-card>
            @empty
                <x-empty-state
                    class="col-span-full"
                    icon="userGroup"
                    title="Aún no tienes grupos inscritos"
                    message="Solicita el folio de tu clase a tu profesor para unirte."
                >
                    <x-button :href="route('student.groups.join.form')" variant="primary" size="md">
                        Unirme a un grupo
                    </x-button>
                </x-empty-state>
            @endforelse
        </div>
    </section>
</div>
@endsection
