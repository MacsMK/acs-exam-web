{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/result-summary.blade.php (MPW-V2 §5.2.2, §5.5, §7.3, §7.8.2, §8.3, RF-EVA-009, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Resultado de Evaluación · ' . $attempt->exam->title)

@section('content')
    <div class="max-w-3xl mx-auto">
        {{-- Encabezado con Título y Estado General --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-acode-primary tracking-tight">
                    {{ $attempt->exam->title }}
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Escala de evaluación: <strong>{{ $attempt->exam->grading_scale }}</strong> ·
                    Entregado el {{ $attempt->submitted_at?->format('d/m/Y \a \l\a\s H:i') ?? now()->format('d/m/Y H:i') }}
                </p>
            </div>

            <div>
                <x-button :href="route('student.exams.index')" variant="neutral" size="sm">
                    <x-icon name="chevronDown" class="w-3.5 h-3.5 rotate-90 mr-1" />
                    Volver al listado
                </x-button>
            </div>
        </div>

        {{-- Tarjeta de Calificación y Dictamen Normativo --}}
        <x-card class="mb-8 border-slate-200">
            @if ($attempt->exam->result_visibility_mode === 'hidden')
                <div class="py-4 text-center">
                    <x-icon name="lock" class="w-8 h-8 text-slate-400 mx-auto mb-2" />
                    <p class="text-sm font-semibold text-slate-700">Resultado reservado temporalmente</p>
                    <p class="text-xs text-slate-500 mt-1">El docente titular ha configurado la visualización de calificaciones de forma posterior.</p>
                </div>
            @elseif ($attempt->pending_ai_count > 0)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Puntaje Objetivo Registrado</p>
                        <p class="font-mono text-3xl font-extrabold text-acode-primary">
                            {{ number_format((float) $attempt->objective_score, 2) }}
                        </p>
                    </div>

                    <div class="sm:text-right">
                        <x-badge variant="warning" class="text-xs px-3 py-1 font-semibold">
                            Pendiente de evaluación docente / IA
                        </x-badge>
                        <p class="text-xs text-slate-500 mt-1.5">
                            {{ $attempt->pending_ai_count }} reactivo(s) cualitativo(s) en proceso de revisión.
                        </p>
                    </div>
                </div>
            @else
                @php
                    $isHundredScale = $attempt->exam->grading_scale === '0-100';
                    $minPassing = $isHundredScale ? 60.0 : 6.0;
                    $scoreVal = (float) ($attempt->final_score ?? $attempt->objective_score ?? 0.0);
                    $isPassed = $scoreVal >= $minPassing;
                @endphp

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Calificación Definitiva</p>
                        <p class="font-mono text-4xl font-extrabold {{ $isPassed ? 'text-acode-accent' : 'text-acode-danger' }}">
                            {{ number_format($scoreVal, 2) }}
                        </p>
                    </div>

                    <div class="flex flex-col sm:items-end gap-1.5">
                        <span class="text-xs text-slate-500 font-medium">Dictamen de acreditación:</span>
                        @if ($isPassed)
                            <x-badge variant="accent" class="text-sm px-3.5 py-1 font-bold">
                                ✓ Aprobado
                            </x-badge>
                        @else
                            <x-badge variant="danger" class="text-sm px-3.5 py-1 font-bold">
                                ✗ Reprobado
                            </x-badge>
                        @endif
                    </div>
                </div>
            @endif
        </x-card>

        {{-- Desglose Detallado por Reactivo --}}
        @if ($attempt->exam->result_visibility_mode !== 'hidden')
            <div class="space-y-4 mb-8">
                <h2 class="text-lg font-bold text-acode-primary tracking-tight">Desglose de Reactivos</h2>

                <div class="space-y-3">
                    @forelse ($attempt->answers as $index => $answer)
                        <x-card class="p-4 border-slate-200">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div class="flex items-start gap-2.5">
                                    <span class="w-6 h-6 rounded-full bg-acode-surface flex items-center justify-center font-bold text-xs text-acode-tech shrink-0 mt-0.5">
                                        {{ $index + 1 }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 leading-snug">
                                            {{ $answer->examQuestion->question->title }}
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            Tipo: <span class="capitalize">{{ $answer->examQuestion->question->question_type }}</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    @if (!is_null($answer->is_correct))
                                        @if ($answer->is_correct)
                                            <x-badge variant="accent">Correcto</x-badge>
                                        @else
                                            <x-badge variant="danger">Incorrecto</x-badge>
                                        @endif
                                    @elseif ($answer->teacher_recoteje_score === null && !$answer->ai_evaluated_at)
                                        <x-badge variant="warning">Pendiente de evaluación</x-badge>
                                    @elseif ($answer->ai_evaluated_at)
                                        <x-badge :variant="$answer->ai_meets_goal ? 'accent' : 'danger'">
                                            IA: {{ $answer->ai_meets_goal ? 'Cumple objetivo' : 'No cumple' }}
                                        </x-badge>
                                    @else
                                        <x-badge variant="warning">Pendiente</x-badge>
                                    @endif
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                                <span>Puntos asignados: <strong class="font-mono text-slate-800">{{ number_format((float) $answer->points_awarded, 2) }}</strong></span>
                                @if ($answer->ai_feedback)
                                    <span class="text-slate-500 italic max-w-xs truncate" title="{{ $answer->ai_feedback }}">
                                        Feedback: "{{ $answer->ai_feedback }}"
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

        {{-- Botón de Regreso Normativo --}}
        <div class="pt-2 text-center sm:text-left">
            <x-button :href="route('student.exams.index')" variant="neutral" size="md">
                <x-icon name="chevronDown" class="w-4 h-4 rotate-90 mr-1.5" />
                Volver al listado
            </x-button>
        </div>
    </div>
@endsection
