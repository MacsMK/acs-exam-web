{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/result-summary.blade.php (MPW-V2 §5.2.2, §5.5, RF-EVA-009) --}}
@extends('layouts.app')

@section('title', 'Resultado')

@section('content')
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-acode-primary mb-1">{{ $attempt->exam->title }}</h1>
        <p class="text-sm text-slate-500 mb-6">
            Escala {{ $attempt->exam->grading_scale }} ·
            Entregado {{ $attempt->submitted_at?->format('d/m/Y H:i') }}
        </p>

        <x-card class="mb-6">
            @if ($attempt->exam->result_visibility_mode === 'hidden')
                <p class="text-sm text-slate-600">Tu docente ocultó el resultado de este examen por ahora.</p>
            @elseif ($attempt->pending_ai_count > 0)
                <p class="text-sm text-slate-600 mb-2">
                    Objetivo obtenido hasta ahora:
                    <span class="font-bold text-acode-primary text-xl">{{ number_format((float) $attempt->objective_score, 2) }}</span>
                </p>
                <x-badge variant="warning">
                    {{ $attempt->pending_ai_count }} reactivo(s) abiertos/código pendientes de evaluación docente.
                </x-badge>
            @else
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Calificación final</p>
                <p class="font-bold text-acode-primary text-4xl">{{ number_format((float) $attempt->final_score, 2) }}</p>
            @endif
        </x-card>

        @if ($attempt->exam->result_visibility_mode !== 'hidden')
            <h2 class="text-lg font-semibold text-acode-primary mb-3">Detalle por reactivo</h2>

            <div class="space-y-3">
                @forelse ($attempt->answers as $answer)
                    <x-card class="p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-800">{{ $answer->examQuestion->question->title }}</p>
                            @if (! is_null($answer->is_correct))
                                @if ($answer->is_correct)
                                    <x-badge variant="accent">Correcto</x-badge>
                                @else
                                    <x-badge variant="danger">Incorrecto</x-badge>
                                @endif
                            @elseif ($answer->teacher_recoteje_score === null)
                                <x-badge variant="warning">Pendiente de evaluación docente</x-badge>
                            @elseif ($answer->ai_evaluated_at)
                                <x-badge :variant="$answer->ai_meets_goal ? 'accent' : 'danger'">
                                    IA: {{ $answer->ai_meets_goal ? 'cumple objetivo' : 'no cumple objetivo' }}
                                </x-badge>
                            @else
                                <x-badge variant="warning">Pendiente de evaluación docente</x-badge>
                            @endif
                        </div>
                        @if (! is_null($answer->is_correct) || $answer->teacher_recoteje_score !== null)
                            <p class="text-xs text-slate-500 mt-1">Puntos: {{ number_format((float) $answer->points_awarded, 2) }}</p>
                        @endif
                        @if ($answer->teacher_recoteje_score !== null && $answer->ai_feedback)
                            <p class="text-xs text-slate-500 mt-1 italic">{{ $answer->ai_feedback }}</p>
                        @endif
                    </x-card>
                @empty
                    <x-empty-state message="No se registraron respuestas en este intento." />
                @endforelse
            </div>
        @endif

        <a href="{{ route('student.exams.index') }}" class="inline-block mt-6 text-acode-tech text-sm font-medium hover:underline">Ver detalle de mis exámenes</a>
    </div>
@endsection
