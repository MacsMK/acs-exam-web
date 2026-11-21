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
    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        :title="$attempt->exam->title"
        :breadcrumbs="[
            ['label' => 'Mis Calificaciones', 'href' => route('student.grades.index')],
            ['label' => 'Resultado de Evaluación'],
        ]"
    >
        <x-slot:meta>
            <span>
                Escala de evaluación: <strong class="text-slate-700">{{ $attempt->exam->grading_scale }}</strong>
            </span>
            <span>·</span>
            <span>
                Entregado el <strong class="font-mono text-slate-700">{{ $attempt->submitted_at?->format('d/m/Y \a \l\a\s H:i') ?? now()->format('d/m/Y H:i') }}</strong>
            </span>
        </x-slot:meta>

        <x-slot:actions>
            <x-button :href="route('student.exams.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver al listado
            </x-button>

            @if (! empty($reviewAvailable) && ! $summary['isHidden'])
                <x-button :href="route('attempts.review', $attempt)" variant="primary" size="md">
                    <x-icon name="eye" class="w-4 h-4" />
                    Ver detalles y soluciones
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Tarjeta de Calificación y Dictamen Normativo ($summary desde StudentResultService) --}}
    <x-card padding="p-6 sm:p-7">
        @if ($summary['isHidden'])
            <div class="py-4 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-acode-surface text-slate-500">
                    <x-icon name="lock" class="h-6 w-6" />
                </div>
                <p class="text-base font-bold text-acode-primary">Resultado reservado temporalmente</p>
                <p class="mt-1 text-sm text-slate-500">
                    El docente titular ha configurado la visualización de calificaciones de forma posterior.
                </p>
            </div>
        @elseif ($summary['isPendingReview'] && $summary['visibilityMode'] === 'estimated_score')
            {{-- Modo estimated_score: muestra puntaje preliminar sin exponer ai_feedback (ADR-050) --}}
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Calificación preliminar estimada
                    </p>
                    <p class="font-mono text-4xl font-extrabold text-acode-tech tabular-nums">
                        {{ number_format((float) $summary['preliminaryScore'], 2) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Puntaje objetivo: <strong class="font-mono text-slate-700">{{ number_format((float) $attempt->objective_score, 2) }}</strong>
                    </p>
                </div>

                <div class="sm:text-right">
                    <x-badge variant="warning" class="px-3 py-1 text-xs font-semibold">
                        Pendiente de evaluación docente
                    </x-badge>
                    <p class="mt-2 max-w-sm text-xs text-slate-500">
                        Este puntaje preliminar está sujeto a validación definitiva del docente ({{ (int) $summary['pendingAiCount'] }} reactivo(s) cualitativo(s)).
                    </p>
                </div>
            </div>
        @elseif ($summary['isPendingReview'])
            {{-- Modo objective_only_pending o final_score con recoteje pendiente --}}
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Puntaje Objetivo Registrado
                    </p>
                    <p class="font-mono text-4xl font-extrabold text-acode-primary tabular-nums">
                        {{ number_format((float) $attempt->objective_score, 2) }}
                    </p>
                </div>

                <div class="sm:text-right">
                    <x-badge variant="warning" class="px-3 py-1 text-xs font-semibold">
                        Pendiente de evaluación docente
                    </x-badge>
                    <p class="mt-2 max-w-sm text-xs text-slate-500">
                        {{ (int) $summary['pendingAiCount'] }} reactivo(s) cualitativo(s) en proceso de revisión docente.
                    </p>
                </div>
            </div>
        @else
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Calificación Definitiva</p>
                    <p class="font-mono text-4xl font-extrabold tabular-nums {{ $summary['isPassed'] ? 'text-acode-accent-dark' : 'text-acode-danger' }}">
                        {{ number_format((float) $summary['displayScore'], 2) }}
                    </p>
                </div>

                <div class="flex flex-col gap-1.5 sm:items-end">
                    <span class="text-xs font-medium text-slate-500">Dictamen de acreditación:</span>
                    @if ($summary['isPassed'])
                        <x-badge variant="accent" class="px-3.5 py-1 text-sm font-bold">
                            Aprobado
                        </x-badge>
                    @else
                        <x-badge variant="danger" class="px-3.5 py-1 text-sm font-bold">
                            Reprobado
                        </x-badge>
                    @endif
                </div>
            </div>
        @endif
    </x-card>

    {{-- Nivel 3: Desglose Detallado por Reactivo (si no está oculto) --}}
    @if (! $summary['isHidden'])
        <div class="space-y-4">
            <x-section-header
                title="Desglose de Reactivos"
                subtitle="Estado individual y puntaje asignado a cada respuesta registrada."
            />

            <div class="space-y-3">
                @forelse ($attempt->answers as $index => $answer)
                    <x-card padding="p-5">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-acode-surface text-xs font-bold text-acode-tech">
                                    {{ $index + 1 }}
                                </span>
                                <div>
                                    <p class="text-sm font-semibold leading-snug text-acode-primary">
                                        {{ $answer->examQuestion?->question?->title ?? 'Reactivo #' . $answer->exam_question_id }}
                                    </p>
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
                                        <x-badge variant="danger">Incorrecto</x-badge>
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
                                <span>Puntos asignados: <strong class="font-mono text-slate-800">{{ number_format((float) $answer->points_awarded, 2) }}</strong></span>
                            @else
                                <span class="italic text-slate-400">Puntos: Pendiente de validación</span>
                            @endif

                            @if ($answer->teacher_recoteje_notes)
                                <span class="max-w-md italic text-slate-700" title="{{ $answer->teacher_recoteje_notes }}">
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
