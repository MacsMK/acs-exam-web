{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/exams/instructions.blade.php
    Descripción: Pantalla de instrucciones previas y confirmación de examen.
                 Cumple RF-EXA-028 (P-83), RF-EXA-029 (P-84) y RW-25.
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Instrucciones del Examen · ' . $exam->title)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    {{-- Navegación superior --}}
    <div>
        <a href="{{ route('student.exams.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-acode-tech hover:underline">
            <span>←</span>
            <span>Volver al catálogo de exámenes</span>
        </a>
    </div>

    {{-- Tarjeta principal de instrucciones --}}
    <div class="rounded-xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6">
        {{-- Encabezado del examen --}}
        <div class="border-b border-slate-100 pb-5">
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center rounded-full bg-acode-surface px-3 py-1 text-xs font-semibold text-acode-tech">
                    Evaluación Contrarreloj
                </span>
                <span class="text-xs text-slate-400">·</span>
                <span class="text-xs font-medium text-slate-600">
                    Escala: {{ $exam->grading_scale === '0-100' ? '0 a 100 puntos' : '0 a 10 con decimales' }}
                </span>
            </div>

            <h1 class="text-2xl font-bold text-acode-primary sm:text-3xl tracking-tight">
                {{ $exam->title }}
            </h1>

            <p class="mt-2 text-sm text-slate-600">
                {{ $exam->description ?? 'Esta evaluación pondrá a prueba los conocimientos adquiridos en la asignatura.' }}
            </p>

            <div class="mt-4 flex flex-wrap gap-4 text-xs text-slate-500">
                <div>
                    <span class="text-slate-400">Profesor titular:</span>
                    <strong class="text-slate-700 ml-1">{{ $exam->teacher?->full_name ?? 'Docente titular' }}</strong>
                </div>
                <div>
                    <span class="text-slate-400">Asignaturas:</span>
                    <strong class="text-slate-700 ml-1">{{ $exam->groups->pluck('name')->join(', ') ?: 'General' }}</strong>
                </div>
            </div>
        </div>

        {{-- Especificaciones operativas --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-lg bg-acode-surface/60 border border-slate-200/60 p-4 text-center">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Reactivos</p>
                <p class="text-2xl font-extrabold text-acode-primary mt-1">{{ $questionsCount }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Preguntas a responder</p>
            </div>

            <div class="rounded-lg bg-acode-surface/60 border border-slate-200/60 p-4 text-center">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Duración Total</p>
                <p class="text-2xl font-extrabold text-acode-primary mt-1">
                    {{ $exam->total_duration_minutes ?? $exam->duration_minutes ?? 60 }} min
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">Control por temporizador</p>
            </div>

            <div class="rounded-lg bg-acode-surface/60 border border-slate-200/60 p-4 text-center">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Modalidad</p>
                <p class="text-lg font-bold text-acode-primary mt-1">
                    {{ $exam->randomize_question_order ? 'Aleatoria' : 'Secuencial' }}
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">1 intento por alumno</p>
            </div>
        </div>

        {{-- Reglas y advertencias de supervisión (RW-25 / MPW-V2 §7.7) --}}
        <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-5 space-y-3">
            <div class="flex items-center gap-2 text-amber-900 font-semibold text-sm">
                <x-icon name="warning" class="h-5 w-5 text-amber-600 shrink-0" />
                <span>Condiciones obligatorias para presentar esta evaluación</span>
            </div>

            <ul class="text-xs text-amber-900/90 space-y-2 list-disc list-inside">
                <li><strong>El tiempo corre en el servidor:</strong> Una vez confirmado el inicio, el cronómetro no se detiene ni se puede pausar.</li>
                <li><strong>Secuencia irreversible:</strong> Cada reactivo debe responderse dentro de su límite temporal. Al agotarse el tiempo o enviar la respuesta, el sistema avanza automáticamente sin posibilidad de retroceso.</li>
                <li><strong>Supervisión de foco (Anti-fraude):</strong> Cambiar de pestaña, abrir otras aplicaciones o minimizar la ventana será registrado como una incidencia de seguridad.</li>
                @if ($exam->require_webcam)
                    <li><strong>Supervisión por cámara web:</strong> Este examen requiere mantener la cámara activa durante toda la sesión.</li>
                @endif
                <li><strong>Auto-guardado permanente:</strong> Tus respuestas se registran de inmediato en la base de datos de manera inviolable.</li>
            </ul>
        </div>

        {{-- Formulario de confirmación expresa (P-84) --}}
        <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
            <x-button :href="route('student.exams.index')" variant="neutral" size="md" class="w-full sm:w-auto">
                Cancelar y volver
            </x-button>

            <form method="POST" action="{{ route('attempts.start', $exam) }}" class="w-full sm:w-auto">
                @csrf
                <x-button type="submit" variant="primary" size="lg" class="w-full sm:w-auto">
                    <x-icon name="clock" class="h-5 w-5 mr-2" />
                    Confirmar e iniciar examen
                </x-button>
            </form>
        </div>
    </div>
</div>
@endsection
