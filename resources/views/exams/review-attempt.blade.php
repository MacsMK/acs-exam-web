{{-- ============================================================================
A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
============================================================================
Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
Archivo:        review-attempt.blade.php
Ubicación:      resources/views/exams/review-attempt.blade.php
Requerimiento:  RF-IAR-002, RF-EVA-009, RW-11 (Recoteje y Ajuste Docente de IA)
Autor:          Equipo 4 (A-Code Solutions)
Versión:        1.0.0
Fecha Creación: 08/11/2026
============================================================================ --}}
@extends('layouts.app')

@section('title', 'Recoteje de Intento: ' . $attempt->student->name)

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    {{-- Breadcrumbs y Encabezado --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center space-x-2 text-xs font-semibold text-slate-500 mb-2">
                <a href="{{ route('exams.index') }}" class="hover:text-acode-primary transition">Exámenes</a>
                <span>/</span>
                <a href="{{ route('exams.revision.index', $exam) }}" class="hover:text-acode-primary transition">{{ $exam->title }}</a>
                <span>/</span>
                <span class="text-acode-primary font-bold">Intento #{{ $attempt->id }}</span>
            </nav>
            <h1 class="text-2xl font-bold text-acode-primary">Recoteje de Evaluación Docente</h1>
            <p class="text-sm text-slate-600 mt-1">Revisa las respuestas del estudiante, la retroalimentación calculada por IA y ajusta la calificación definitiva.</p>
        </div>

        <a href="{{ route('exams.revision.index', $exam) }}" class="inline-flex items-center text-sm font-semibold text-acode-tech hover:text-acode-tech-dark">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Volver a Lista de Intentos
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <p class="font-bold mb-1">Por favor corrige los siguientes errores:</p>
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Tarjeta de Datos del Alumno y Resumen --}}
    <x-card padding="p-6" class="border-l-4 border-l-acode-navy">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Estudiante</span>
                <p class="text-base font-bold text-acode-navy mt-1">{{ $attempt->student->name }}</p>
                <p class="text-xs text-slate-500">{{ $attempt->student->email }}</p>
            </div>
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Fecha de Envío</span>
                <p class="text-sm font-semibold text-slate-700 mt-1">
                    {{ $attempt->submitted_at ? $attempt->submitted_at->format('d/m/Y H:i:s') : 'Sin envío formal' }}
                </p>
                <p class="text-xs text-slate-500">Incidentes reportados: {{ $attempt->incidents_count }}</p>
            </div>
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Puntaje Automático</span>
                <p class="text-base font-mono font-bold text-slate-800 mt-1">
                    {{ number_format((float)$attempt->objective_score, 1) }} pts objetivos
                </p>
                <p class="text-xs text-slate-500">+ {{ number_format((float)$attempt->ai_estimated_score, 1) }} pts IA</p>
            </div>
            <div class="bg-acode-surface p-3 rounded-xl border border-slate-200 text-center">
                <span class="text-xs font-bold uppercase tracking-wider text-acode-navy">Calificación Final</span>
                <p class="text-2xl font-mono font-extrabold text-acode-navy mt-0.5">
                    {{ $attempt->final_score !== null ? number_format((float)$attempt->final_score, 1) : 'Pendiente' }}
                </p>
                <span class="text-[10px] font-semibold text-slate-500">Escala: {{ $exam->grading_scale }}</span>
            </div>
        </div>
    </x-card>

    {{-- Formulario de Recoteje --}}
    <form action="{{ route('exams.revision.update', [$exam, $attempt]) }}" method="POST" class="space-y-6">
        @csrf

        @php
            $reviewIndex = 0;
        @endphp

        @foreach ($attempt->answers as $index => $answer)
            @php
                $question = $answer->examQuestion->question;
                $isAiQuestion = in_array($question->question_type, ['open', 'code'], true);
                $maxPoints = (float) $answer->examQuestion->points_weight;
            @endphp

            <x-card padding="p-6" class="space-y-4">
                {{-- Encabezado del Reactivo --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3 gap-2">
                    <div class="flex items-center space-x-3">
                        <span class="w-7 h-7 rounded-full bg-acode-navy text-white text-xs font-bold flex items-center justify-center">
                            {{ $index + 1 }}
                        </span>
                        <div>
                            <h3 class="font-bold text-acode-navy text-base">{{ $question->title }}</h3>
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                Tipo: {{ $question->question_type }} · Valor máximo: {{ $maxPoints }} pts
                            </span>
                        </div>
                    </div>

                    @if ($isAiQuestion)
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                            Evaluado por IA · Recotejo manual habilitado
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-bold bg-slate-100 text-slate-700">
                            Reactivo Objetivo Autocalificado
                        </span>
                    @endif
                </div>

                {{-- Enunciado --}}
                <div class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block mb-1">Enunciado:</span>
                    <p class="whitespace-pre-line leading-relaxed">{{ $question->stem }}</p>
                </div>

                {{-- Respuesta del Alumno --}}
                <div class="space-y-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Respuesta enviada por el alumno:</span>

                    @if ($question->question_type === 'multiple_choice')
                        <div class="p-3 bg-white border border-slate-200 rounded-xl text-sm">
                            <span class="font-semibold text-slate-700">Opción seleccionada:</span>
                            <span class="font-mono ml-2 font-bold">{{ $answer->answer_payload['selected_option_index'] ?? 'Sin respuesta' }}</span>
                        </div>
                    @elseif ($question->question_type === 'matching')
                        <div class="p-3 bg-white border border-slate-200 rounded-xl text-sm font-mono text-xs">
                            <pre>{{ json_encode($answer->answer_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @elseif ($question->question_type === 'open')
                        <div class="p-4 bg-white border border-slate-200 rounded-xl text-sm leading-relaxed whitespace-pre-line">
                            {{ $answer->answer_payload['text'] ?? ($answer->answer_payload ?? 'Sin respuesta enviada') }}
                        </div>
                    @elseif ($question->question_type === 'code')
                        <div class="bg-slate-900 rounded-xl p-4 font-mono text-xs text-emerald-400 overflow-x-auto">
                            <code>{{ $answer->answer_payload['code'] ?? ($answer->answer_payload ?? '// Sin código enviado') }}</code>
                        </div>
                    @endif
                </div>

                {{-- Si es pregunta de IA (Abierta o Código): Mostrar evaluación IA y formulario de recoteje --}}
                @if ($isAiQuestion)
                    <div class="bg-blue-50/60 border border-blue-200 rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between text-xs font-bold text-acode-navy">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-acode-tech" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Evaluación Asistida por IA (MockAiEvaluator / Gemini)
                            </span>
                            <span>Similitud: {{ number_format((float)$answer->ai_similarity_score, 1) }}% · {{ $answer->ai_meets_goal ? 'Cumple Objetivo' : 'No Cumple Objetivo' }}</span>
                        </div>
                        <p class="text-xs text-slate-600 italic">
                            "{{ $answer->ai_feedback ?? 'Sin retroalimentación de IA registrada.' }}"
                        </p>
                    </div>

                    {{-- Formulario de Ajuste de Calificación --}}
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 pt-2 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <input type="hidden" name="reviews[{{ $reviewIndex }}][answer_id]" value="{{ $answer->id }}">

                        <div class="sm:col-span-4">
                            <label class="block text-xs font-bold uppercase tracking-wider text-acode-navy mb-1">
                                Calificación Docente Definitiva (Máx. {{ $maxPoints }} pts)
                            </label>
                            <input type="number"
                                   step="0.1"
                                   min="0"
                                   max="{{ $maxPoints }}"
                                   name="reviews[{{ $reviewIndex }}][score]"
                                   value="{{ old('reviews.'.$reviewIndex.'.score', $answer->teacher_recoteje_score ?? $answer->points_awarded) }}"
                                   required
                                   class="w-full h-10 px-3 text-sm font-bold font-mono rounded-lg border border-slate-300 focus:ring-2 focus:ring-acode-primary focus:border-acode-primary">
                        </div>

                        <div class="sm:col-span-8">
                            <label class="block text-xs font-bold uppercase tracking-wider text-acode-navy mb-1">
                                Notas de Retroalimentación del Maestro (Opcional)
                            </label>
                            <input type="text"
                                   name="reviews[{{ $reviewIndex }}][notes]"
                                   value="{{ old('reviews.'.$reviewIndex.'.notes', $answer->teacher_recoteje_notes) }}"
                                   placeholder="Ej. Argumentación clara pero faltó citar el concepto clave..."
                                   class="w-full h-10 px-3 text-sm rounded-lg border border-slate-300 focus:ring-2 focus:ring-acode-primary focus:border-acode-primary">
                        </div>
                    </div>

                    @php
                        $reviewIndex++;
                    @endphp
                @else
                    <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                        <span>Puntaje asignado por motor automático:</span>
                        <span class="font-bold font-mono text-slate-800">{{ number_format((float)$answer->points_awarded, 1) }} / {{ $maxPoints }} pts</span>
                    </div>
                @endif
            </x-card>
        @endforeach

        @if ($reviewIndex > 0)
            <div class="sticky bottom-4 bg-white/95 backdrop-blur-md p-4 rounded-2xl border border-slate-200 shadow-xl flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-700">
                    Se actualizarán <strong>{{ $reviewIndex }}</strong> reactivos en este intento.
                </span>
                <button type="submit" class="bg-acode-navy hover:bg-acode-primary-dark text-white text-sm font-bold px-6 py-2.5 rounded-xl shadow-md transition">
                    Guardar Recotejo Docente
                </button>
            </div>
        @endif
    </form>
</div>
@endsection
