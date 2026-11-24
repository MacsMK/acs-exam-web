{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/exams/result-summary.blade.php
    Ubicación:   resources/views/exams/result-summary.blade.php
    Descripción: Resumen y dictamen normativo del intento finalizado del alumno
                 homologado al UI Kit A-Code, manteniendo blindaje IA ADR-050 /
                 RF-EVA-009 (MPW-V3 §4.1.2, §5.2.2, §7.3, §8.3; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Resultado de Evaluación · ' . $attempt->exam->title)

@section('content')
<div class="space-y-6">
    @php($grade = $summary['grade'])
    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        :title="$attempt->exam->title"
        :institutional="true"
        :breadcrumbs="[
            ['label' => 'Mis Calificaciones', 'href' => route('student.grades.index')],
            ['label' => 'Resultado de Evaluación'],
        ]"
    >
        <x-slot:meta>
            @if ($grade['status'] === 'published')
                <span>Escala de evaluación: <strong class="text-slate-700">{{ $summary['gradingScale'] }}</strong></span>
            @endif
            @if ($attempt->status === 'submitted' && $attempt->submitted_at)
                <span>Entregado el <strong class="font-mono text-slate-700">{{ $attempt->submitted_at->format('d/m/Y \a \l\a\s H:i') }}</strong></span>
            @endif
            @if ($summary['wasStoppedByTeacher'])
                <span class="font-semibold text-acode-primary">Finalizado porque el docente detuvo el examen</span>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            <x-button :href="route('student.exams.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver al listado de exámenes
            </x-button>

            @if ($grade['status'] === 'in_progress')
                <x-button :href="route('attempts.show', $attempt)" variant="primary" size="md">Continuar examen</x-button>
            @elseif ($reviewAvailable && $grade['status'] === 'published')
                <x-button :href="route('attempts.review', $attempt)" variant="primary" size="md">
                    <x-icon name="eye" class="w-4 h-4" />
                    Ver detalles y soluciones
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Tarjeta de Calificación y Dictamen Normativo ($summary desde StudentResultService) --}}
    <x-card padding="p-6 sm:p-7">
        @if ($grade['status'] === 'reserved')
            <div class="py-4 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-acode-surface text-slate-500">
                    <x-icon name="lock" class="h-6 w-6" />
                </div>
                <p class="text-base font-bold text-acode-primary">Resultado reservado temporalmente</p>
                <p class="mt-1 text-sm text-slate-500">
                    El docente titular ha configurado la visualización de calificaciones de forma posterior.
                </p>
            </div>
        @elseif (in_array($grade['status'], ['in_progress', 'unsubmitted'], true))
            <x-summary-list>
                <x-summary-item label="Estado de la evaluación" compact>
                    <x-slot:valueSlot>
                        <x-student.attempt-grade :grade="$grade" />
                    </x-slot:valueSlot>
                    @if ($attempt->status === 'cancelled_by_incident')
                        Cancelado por incidencias.
                    @elseif (in_array($attempt->status, ['expired', 'in_progress'], true) && $grade['status'] === 'unsubmitted' && ! $summary['wasStoppedByTeacher'])
                        El tiempo límite del examen ha expirado.
                    @endif
                </x-summary-item>
            </x-summary-list>
        @elseif ($grade['status'] === 'pending' && $summary['visibilityMode'] === 'estimated_score')
            {{-- Modo estimated_score: muestra puntaje preliminar sin exponer ai_feedback (ADR-050) --}}
            <x-summary-list>
                <x-summary-item label="Calificación preliminar estimada" :value="number_format((float) $summary['preliminaryScore'], 2)" />
                <x-summary-item label="Puntaje objetivo" :value="number_format($summary['objectiveScoreRaw'], 2) . ' pts'" />
                <x-summary-item label="Estado de la evaluación" compact>
                    <x-slot:valueSlot><x-badge variant="warning">Pendiente de evaluación docente</x-badge></x-slot:valueSlot>
                    Esta calificación es preliminar y está sujeta a validación docente.
                </x-summary-item>
            </x-summary-list>
        @elseif ($grade['status'] === 'pending')
            {{-- Modo objective_only_pending o final_score con revisión pendiente --}}
            <x-summary-list>
                <x-summary-item label="Puntaje Objetivo Registrado" :value="number_format($summary['objectiveScoreRaw'], 2) . ' pts'" />
                <x-summary-item label="Estado de la evaluación" compact>
                    <x-slot:valueSlot><x-badge variant="warning">Pendiente de evaluación docente</x-badge></x-slot:valueSlot>
                    @if ($summary['pendingAiCount'] > 0)
                        {{ $summary['pendingAiCount'] }} {{ $summary['pendingAiCount'] === 1 ? 'reactivo en revisión' : 'reactivos en revisión' }}.
                    @endif
                </x-summary-item>
            </x-summary-list>
        @elseif ($grade['status'] === 'published')
            <x-summary-list>
                <x-summary-item label="Calificación Definitiva" :value="number_format($grade['score'], 2) . ' / ' . $grade['scaleMax']" />
                <x-summary-item label="Dictamen de acreditación" compact>
                    <x-slot:valueSlot>
                        <x-badge :variant="$grade['variant']" :class="$grade['variant'] === 'danger' ? '!text-acode-danger-dark' : ''">
                            {{ $grade['label'] }}
                        </x-badge>
                    </x-slot:valueSlot>
                </x-summary-item>
            </x-summary-list>
        @endif
    </x-card>

    {{-- Nivel 3: Desglose Detallado por Reactivo (si no está oculto) --}}
    @if (in_array($grade['status'], ['pending', 'published'], true))
        <div class="space-y-4">
            <x-section-header
                title="Desglose de Reactivos"
                subtitle="Estado individual y puntaje asignado a cada respuesta registrada."
                :institutional="true"
            />

            <div class="space-y-3">
                @forelse ($attempt->answers as $index => $answer)
                    <x-card padding="p-5">
                        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-acode-surface text-xs font-bold text-acode-tech">
                                    {{ $index + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <h3 class="break-words text-base font-semibold leading-6 text-acode-primary">
                                        {{ $answer->examQuestion?->question?->title ?? 'Reactivo #' . $answer->exam_question_id }}
                                    </h3>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        Tipo:
                                        <span>
                                            {{ match ($answer->examQuestion?->question?->question_type) {
                                                'multiple_choice' => 'Opción múltiple',
                                                'matching' => 'Relación de columnas',
                                                'open' => 'Pregunta abierta',
                                                'code' => 'Código fuente',
                                                default => '—',
                                            } }}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div class="shrink-0">
                                @if (in_array($answer->examQuestion?->question?->question_type, ['open', 'code'], true) && $answer->teacher_recoteje_score === null)
                                    {{-- Blindaje normativo ADR-050 / RF-EVA-009: nunca revelar ai_feedback preliminar --}}
                                    <x-badge variant="warning">Pendiente de evaluación docente</x-badge>
                                @elseif ($answer->teacher_recoteje_score !== null)
                                    <x-badge variant="accent">Revisado por docente</x-badge>
                                @elseif (! is_null($answer->is_correct))
                                    @if ($answer->is_correct)
                                        <x-badge variant="accent">Correcto</x-badge>
                                    @else
                                        <x-badge variant="danger" class="!text-acode-danger-dark">Incorrecto</x-badge>
                                    @endif
                                @else
                                    <x-badge variant="warning">Pendiente de evaluación docente</x-badge>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs text-slate-600">
                            @if (
                                (! in_array($answer->examQuestion?->question?->question_type, ['open', 'code'], true) && ! is_null($answer->is_correct))
                                || $answer->teacher_recoteje_score !== null
                                || (! $summary['isPendingReview'] && ! is_null($answer->is_correct))
                            )
                                <span>Puntos asignados: <strong class="font-mono text-slate-800">{{ number_format((float) $answer->points_awarded, 2) }} pts</strong></span>
                            @else
                                <span class="text-slate-600">Puntos: Pendiente de validación</span>
                            @endif

                            @if ($answer->teacher_recoteje_notes)
                                <span class="max-w-md break-words italic text-slate-700">
                                    Observación docente: "{{ $answer->teacher_recoteje_notes }}"
                                </span>
                            @endif
                        </div>
                    </x-card>
                @empty
                    <x-empty-state
                        icon="clock"
                        title="Sin respuestas registradas"
                        message="No se encontraron registros de respuestas enviadas en este intento."
                    />
                @endforelse
            </div>
        </div>
    @endif
</div>
@endsection
