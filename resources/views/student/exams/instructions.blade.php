{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/exams/instructions.blade.php
    Ubicación:   resources/views/student/exams/instructions.blade.php
    Descripción: Pantalla de instrucciones previas y confirmación expresa antes
                 de iniciar examen contrarreloj (RF-EXA-028, RF-EXA-029, RW-25;
                 MPW-V3 §7.3, §7.4.2, §7.7, §8.1–§8.3; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Instrucciones del Examen · ' . $exam->title)

@section('content')
<div class="space-y-6">
    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        :title="$exam->title"
        :subtitle="$exam->description ?? 'Esta evaluación pondrá a prueba los conocimientos adquiridos en la asignatura.'"
        :breadcrumbs="[
            ['label' => 'Mis Exámenes', 'href' => route('student.exams.index')],
            ['label' => 'Instrucciones del Examen'],
        ]"
    >
        <x-slot:badge>
            <x-badge variant="info">Evaluación contrarreloj</x-badge>
        </x-slot:badge>

        <x-slot:meta>
            <span>
                Profesor titular: <strong class="text-slate-700">{{ $exam->teacher?->full_name ?? 'Docente titular' }}</strong>
            </span>
            <span>·</span>
            <span>
                Asignaturas: <strong class="text-slate-700">{{ $exam->groups->pluck('name')->join(', ') ?: 'General' }}</strong>
            </span>
            <span>·</span>
            <span>
                Escala: <strong class="text-slate-700">{{ $exam->grading_scale === '0-100' ? '0 a 100 puntos' : '0 a 10 con decimales' }}</strong>
                ·
                Cierre: <strong class="text-slate-700">{{ $exam->ends_at?->format('d/m/Y H:i') ?? 'Sin vencimiento' }}</strong>
                ({{ \App\Models\Exam::timezoneLabel() }})
            </span>
        </x-slot:meta>

        <x-slot:actions>
            <x-button :href="route('student.exams.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Especificaciones operativas en Tarjetas KPI --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-kpi-card
            label="Reactivos"
            :value="$questionsCount"
            meta="Preguntas a responder"
            icon="document"
            tone="tech"
        />

        <x-kpi-card
            label="Tiempo máximo"
            :value="$exam->maxDurationMinutes() . ' min'"
            meta="Suma de los tiempos por pregunta"
            icon="clock"
            tone="warning"
            :mono="true"
        />

        <x-kpi-card
            label="Orden de preguntas"
            :value="$exam->randomize_question_order ? 'Aleatorio' : 'Fijo'"
            meta="1 intento por alumno"
            icon="shield"
            tone="primary"
        />
    </div>

    {{-- Nivel 3: Condiciones obligatorias y confirmación expresa --}}
    <x-card padding="p-6 sm:p-8" class="space-y-6">
        <div class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 p-5 space-y-3">
            <div class="flex items-center gap-2.5 text-sm font-bold text-acode-primary">
                <x-icon name="warning" class="h-5 w-5 text-acode-warning-dark shrink-0" />
                <span>Condiciones obligatorias para presentar esta evaluación</span>
            </div>

            <ul class="list-inside list-disc space-y-2 text-xs leading-relaxed text-slate-700">
                <li><strong>El tiempo corre en el servidor:</strong> Este examen es contrarreloj. Una vez iniciado, el cronómetro no se detendrá ni se puede pausar.</li>
                <li><strong>Secuencia irreversible:</strong> Cada reactivo debe responderse dentro de su límite temporal. Al agotarse el tiempo o enviar la respuesta, el sistema avanza automáticamente sin posibilidad de retroceso.</li>
                <li><strong>Supervisión de foco (anti-fraude):</strong> Cambiar de pestaña, abrir otras aplicaciones o minimizar la ventana será registrado como una incidencia de seguridad.</li>
                @if ($exam->require_webcam)
                    <li><strong>Supervisión por cámara web:</strong> Este examen requiere mantener la cámara activa durante toda la sesión.</li>
                @endif
                <li><strong>Guardado automático:</strong> Tu respuesta se guarda en el servidor mientras respondes y se registra al pulsar «Enviar respuesta» o al agotarse el tiempo de la pregunta.</li>
            </ul>
        </div>

        @if (($reapplicationNumber ?? null) !== null)
            <div class="rounded-lg border border-acode-tech/30 bg-acode-tech/10 px-4 py-3 text-sm text-slate-700" role="status">
                <strong>Reaplicación autorizada:</strong> tu docente te dio un intento adicional. Este será tu intento {{ $reapplicationNumber }}; el anterior se conserva y tu calificación será la de este nuevo intento.
            </div>
        @endif

        <div class="flex flex-col-reverse sm:flex-row items-center justify-between gap-4 border-t border-slate-100 pt-5">
            <x-button :href="route('student.exams.index')" variant="neutral" size="md" class="w-full sm:w-auto">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Cancelar y volver
            </x-button>

            <form method="POST" action="{{ route('attempts.start', $exam) }}" class="w-full sm:w-auto">
                @csrf
                <x-button type="submit" variant="primary" size="lg" class="w-full sm:w-auto">
                    <x-icon name="clock" class="h-5 w-5" />
                    Confirmar e iniciar examen
                </x-button>
            </form>
        </div>
    </x-card>
</div>
@endsection
