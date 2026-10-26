@extends('layouts.app')

@section('title', 'Resultado')

@section('content')
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-acode-primary mb-1">{{ $attempt->exam->title }}</h1>
        <p class="text-sm text-slate-500 mb-6">
            Escala {{ $attempt->exam->grading_scale }} ·
            Entregado {{ $attempt->submitted_at?->format('d/m/Y H:i') }}
        </p>

        <div class="bg-white rounded-lg border border-slate-200 p-6 mb-6">
            @if ($attempt->exam->result_visibility_mode === 'hidden')
                <p class="text-sm text-slate-600">Tu docente ocultó el resultado de este examen por ahora.</p>
            @elseif ($attempt->pending_ai_count > 0)
                <p class="text-sm text-slate-600 mb-2">
                    Objetivo obtenido hasta ahora:
                    <span class="font-bold text-acode-primary text-xl">{{ number_format((float) $attempt->objective_score, 2) }}</span>
                </p>
                <p class="text-xs text-acode-warning bg-acode-warning/10 inline-block px-3 py-1 rounded-full">
                    {{ $attempt->pending_ai_count }} reactivo(s) abiertos/código pendientes de evaluación por IA.
                </p>
            @else
                <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Calificación final</p>
                <p class="font-bold text-acode-primary text-4xl">{{ number_format((float) $attempt->final_score, 2) }}</p>
            @endif
        </div>

        <h2 class="text-lg font-semibold text-acode-primary mb-3">Detalle por reactivo</h2>

        <div class="space-y-3">
            @foreach ($attempt->answers as $answer)
                @php $question = $answer->examQuestion->question; @endphp
                <div class="bg-white rounded-lg border border-slate-200 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-800">{{ $question->title }}</p>
                        @if (! is_null($answer->is_correct))
                            @if ($answer->is_correct)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-acode-accent/15 text-acode-accent">Correcto</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-acode-danger/10 text-acode-danger">Incorrecto</span>
                            @endif
                        @elseif ($answer->ai_evaluated_at)
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $answer->ai_meets_goal ? 'bg-acode-accent/15 text-acode-accent' : 'bg-acode-danger/10 text-acode-danger' }}">
                                IA: {{ $answer->ai_meets_goal ? 'cumple objetivo' : 'no cumple objetivo' }}
                            </span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded-full bg-acode-warning/20 text-acode-primary">Pendiente de IA</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Puntos: {{ number_format((float) $answer->points_awarded, 2) }}</p>
                    @if ($answer->ai_feedback)
                        <p class="text-xs text-slate-500 mt-1 italic">{{ $answer->ai_feedback }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <a href="{{ route('student.exams.index') }}" class="inline-block mt-6 text-acode-tech text-sm font-medium">← Volver a mis exámenes</a>
    </div>
@endsection
