{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/student/exams/review.blade.php
    Ubicación:   resources/views/student/exams/review.blade.php
    Descripción: Vista de revisión post-examen con soluciones de referencia y
                 retroalimentación docente autorizada, homologada al UI Kit A-Code
                 (RF-REP-005 / P-172; MPW-V3 §7.3, §7.4.2, §8.1–§8.3; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Revisión y Soluciones · ' . ($reviewData['exam_name'] ?? $attempt->exam->title))

@section('content')
<div class="space-y-6">
    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        title="Revisión de Soluciones y Respuestas"
        :subtitle="($reviewData['exam_name'] ?? $attempt->exam->title) . ' · Compara tus respuestas registradas con las soluciones de referencia autorizadas.'"
        :breadcrumbs="[
            ['label' => 'Mis Calificaciones', 'href' => route('student.grades.index')],
            ['label' => 'Resumen del intento', 'href' => route('attempts.result', $attempt)],
            ['label' => 'Revisión y soluciones'],
        ]"
    >
        <x-slot:meta>
            <span>
                Calificación definitiva:
                <strong class="font-mono text-acode-primary">
                    {{ number_format((float) ($attempt->final_score ?? $attempt->objective_score ?? 0.0), 1) }}
                </strong>
            </span>
        </x-slot:meta>

        <x-slot:actions>
            <x-button :href="route('attempts.result', $attempt)" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver al resumen
            </x-button>

            <x-button :href="route('student.exams.index')" variant="secondary" size="md">
                <x-nav-icon name="exams" class="w-4 h-4" />
                Abrir exámenes
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Lista de reactivos con respuestas y soluciones --}}
    <div class="space-y-4">
        @forelse ($reviewData['questions'] ?? [] as $index => $q)
            @php
                $isFullCredit = (float) $q['points_earned'] >= (float) $q['points_possible'] && (float) $q['points_possible'] > 0;
                $isPartial = (float) $q['points_earned'] > 0 && (float) $q['points_earned'] < (float) $q['points_possible'];
                $qTypeLabel = match ($q['question_type'] ?? null) {
                    'multiple_choice' => 'Opción múltiple',
                    'matching' => 'Relación de columnas',
                    'open' => 'Pregunta abierta',
                    'code' => 'Código fuente',
                    default => '—',
                };
            @endphp
            <x-card padding="p-5 sm:p-6" class="space-y-4">
                {{-- Encabezado de la pregunta --}}
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-acode-surface text-xs font-bold text-acode-tech">
                            {{ $index + 1 }}
                        </span>
                        <div>
                            <h2 class="text-base font-bold leading-6 text-acode-primary">{{ $q['title'] }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Tipo: {{ $qTypeLabel }}</p>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        @if ($isFullCredit)
                            <x-badge variant="accent">Acierto completo</x-badge>
                        @elseif ($isPartial)
                            <x-badge variant="warning">Puntaje parcial</x-badge>
                        @else
                            <x-badge variant="danger">Sin acierto</x-badge>
                        @endif
                        <p class="mt-1 font-mono text-xs font-bold text-slate-700">
                            {{ number_format((float) $q['points_earned'], 2) }} / {{ number_format((float) $q['points_possible'], 2) }} pts
                        </p>
                    </div>
                </div>

                {{-- Enunciado / Reactivo --}}
                <div class="rounded-lg border border-slate-200/70 bg-slate-50 p-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Enunciado</p>
                    <div class="font-sans text-sm leading-relaxed text-slate-800">
                        {!! nl2br(e($q['stem'])) !!}
                    </div>
                    <x-question.files class="mt-3" :question-id="$q['id']" :has-image="$q['has_image'] ?? false" :has-pdf="$q['has_pdf'] ?? false" />
                </div>

                {{-- Respuesta del estudiante vs Solución de referencia --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-600">Tu respuesta</p>
                        <div class="min-h-16 whitespace-pre-wrap rounded-lg border border-slate-200 bg-slate-100 p-3 font-mono text-xs leading-relaxed text-acode-ink">
                            {{ is_array($q['student_answer']) ? json_encode($q['student_answer'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($q['student_answer'] ?? 'Sin respuesta registrada') }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 border-l-4 border-l-acode-accent bg-acode-surface/40 p-4">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wider text-acode-primary">Solución de referencia autorizada</p>
                        <div class="min-h-16 whitespace-pre-wrap rounded-lg border border-slate-200 bg-white p-3 font-mono text-xs leading-relaxed text-acode-ink">
                            {{ is_array($q['reference_solution']) ? json_encode($q['reference_solution'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($q['reference_solution'] ?? 'Consulta al docente') }}
                        </div>
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state
                icon="document"
                title="Sin reactivos para mostrar"
                message="No se encontraron respuestas detalladas disponibles para este intento."
            />
        @endforelse
    </div>
</div>
@endsection
