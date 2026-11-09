{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/exams/review.blade.php
    Descripción: Vista de revisión post-examen con soluciones de referencia y
                 retroalimentación docente autorizada (RF-REP-005 / P-172).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Revisión y Soluciones · ' . ($reviewData['exam_name'] ?? $attempt->exam->title))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Navegación superior --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('attempts.result', $attempt) }}" class="text-xs font-semibold text-acode-tech hover:underline">
                    ← Volver al resumen del intento
                </a>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-acode-primary tracking-tight">
                Revisión de Soluciones y Respuestas
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $reviewData['exam_name'] ?? $attempt->exam->title }} · Calificación definitiva:
                <strong class="font-mono text-slate-700">{{ number_format((float) ($attempt->final_score ?? $attempt->objective_score ?? 0.0), 1) }}</strong>
            </p>
        </div>

        <div>
            <x-button :href="route('student.exams.index')" variant="neutral" size="sm">
                Mis exámenes
            </x-button>
        </div>
    </div>

    {{-- Lista de reactivos con respuestas y soluciones --}}
    <div class="space-y-4">
        @foreach ($reviewData['questions'] ?? [] as $index => $q)
            @php
                $isFullCredit = (float) $q['points_earned'] >= (float) $q['points_possible'] && (float) $q['points_possible'] > 0;
                $isPartial = (float) $q['points_earned'] > 0 && (float) $q['points_earned'] < (float) $q['points_possible'];
            @endphp
            <div class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                {{-- Encabezado de la pregunta --}}
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
                    <div class="flex items-start gap-3">
                        <span class="w-7 h-7 rounded-full bg-acode-surface flex items-center justify-center font-bold text-xs text-acode-tech shrink-0 mt-0.5">
                            {{ $index + 1 }}
                        </span>
                        <div>
                            <h2 class="font-bold text-base text-acode-primary leading-snug">{{ $q['title'] }}</h2>
                            <p class="text-xs text-slate-400 mt-0.5">Tipo: <span class="capitalize">{{ $q['question_type'] }}</span></p>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        @if ($isFullCredit)
                            <x-badge variant="accent">Acierto completo</x-badge>
                        @elseif ($isPartial)
                            <x-badge variant="warning">Puntaje parcial</x-badge>
                        @else
                            <x-badge variant="danger">Sin acierto</x-badge>
                        @endif
                        <p class="text-xs font-mono font-bold text-slate-700 mt-1">
                            {{ number_format((float) $q['points_earned'], 2) }} / {{ number_format((float) $q['points_possible'], 2) }} pts
                        </p>
                    </div>
                </div>

                {{-- Enunciado / Reactivo --}}
                <div class="rounded-lg bg-slate-50 p-4 border border-slate-100">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Enunciado</p>
                    <div class="text-sm text-slate-800 leading-relaxed font-sans">
                        {!! nl2br(e($q['stem'])) !!}
                    </div>
                </div>

                {{-- Respuesta del estudiante vs Solución de referencia --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Respuesta del alumno --}}
                    <div class="rounded-lg border border-slate-200 p-4 bg-white">
                        <p class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tu respuesta</p>
                        <div class="text-xs font-mono text-slate-800 whitespace-pre-wrap bg-slate-50 p-3 rounded border border-slate-100 min-h-16">
                            {{ is_array($q['student_answer']) ? json_encode($q['student_answer'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($q['student_answer'] ?? 'Sin respuesta registrada') }}
                        </div>
                    </div>

                    {{-- Solución canónica --}}
                    <div class="rounded-lg border border-emerald-200 p-4 bg-emerald-50/40">
                        <p class="text-xs font-bold text-emerald-800 uppercase tracking-wider mb-2">Solución de referencia autorizada</p>
                        <div class="text-xs font-mono text-emerald-950 whitespace-pre-wrap bg-white/90 p-3 rounded border border-emerald-200/60 min-h-16">
                            {{ is_array($q['reference_solution']) ? json_encode($q['reference_solution'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($q['reference_solution'] ?? 'Consulte al docente') }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
