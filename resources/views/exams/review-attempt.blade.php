{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/review-attempt.blade.php (MPW-V2 §4.1.2, §7, §8, RF-IAR-002, RW-11) --}}
@extends('layouts.app')

@section('title', 'Recoteje de Intento: ' . ($attempt->student?->full_name ?? 'Estudiante'))

@section('content')
    @php
        $canSubmitRecoteje = $attempt->status === 'submitted';
        $questionTypeLabels = [
            'multiple_choice' => 'Opción múltiple',
            'matching' => 'Relación de columnas',
            'open' => 'Pregunta abierta',
            'code' => 'Código fuente',
        ];
    @endphp

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
                <h1 class="text-2xl sm:text-3xl font-bold text-acode-primary tracking-tight">Recoteje de Evaluación Docente</h1>
                <p class="text-sm text-slate-600 mt-1">
                    Revisa las respuestas del estudiante, la evaluación asistida por IA y ajusta la calificación definitiva.
                </p>
            </div>

            <x-button :href="route('exams.revision.index', $exam)" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4" />
                Volver a Lista de Intentos
            </x-button>
        </div>

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-acode-danger/10 border border-acode-danger/30 text-acode-danger text-sm" role="alert">
                <p class="font-bold mb-1">Por favor corrige los siguientes errores:</p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $canSubmitRecoteje)
            <div class="rounded-xl border border-acode-warning/50 bg-acode-warning/15 p-4 text-sm text-acode-primary flex items-start gap-2.5">
                <x-icon name="lock" class="w-5 h-5 shrink-0 text-acode-warning-dark mt-0.5" />
                <div>
                    <p class="font-bold">Intento en modo solo lectura ({{ $attempt->status }})</p>
                    <p class="text-xs text-slate-700 mt-0.5">
                        Solo los intentos en estado <strong>entregado</strong> admiten recoteje docente.
                    </p>
                </div>
            </div>
        @endif

        {{-- Tarjeta de Datos del Alumno y Resumen --}}
        <x-card padding="p-6" class="border-l-4 border-l-acode-primary">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Estudiante</span>
                    <p class="text-base font-bold text-acode-primary mt-1">{{ $attempt->student?->full_name ?? 'Estudiante' }}</p>
                    <p class="text-xs text-slate-500">{{ $attempt->student?->email ?? '—' }}</p>
                </div>
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Fecha de Envío</span>
                    <p class="text-sm font-semibold text-slate-700 mt-1">
                        {{ $attempt->submitted_at ? $attempt->submitted_at->format('d/m/Y H:i:s') : 'Sin envío formal' }}
                    </p>
                    <p class="text-xs text-slate-500">Incidentes reportados: {{ (int) $attempt->incidents_count }}</p>
                </div>
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Puntaje Automático</span>
                    <p class="text-base font-mono font-bold text-slate-800 mt-1">
                        {{ number_format((float) $attempt->objective_score, 2) }} pts objetivos
                    </p>
                    <p class="text-xs text-slate-500">+ {{ number_format((float) $attempt->ai_estimated_score, 2) }} pts IA</p>
                </div>
                <div class="bg-acode-surface p-3 rounded-xl border border-slate-200 text-center">
                    <span class="text-xs font-bold uppercase tracking-wider text-acode-primary">Calificación Final</span>
                    <p class="text-2xl font-mono font-bold text-acode-primary mt-0.5 tabular-nums">
                        {{ $attempt->final_score !== null ? number_format((float) $attempt->final_score, 2) : 'Pendiente' }}
                    </p>
                    <span class="text-xs font-medium text-slate-500">Escala: {{ $exam->grading_scale }}</span>
                </div>
            </div>
        </x-card>

        {{-- Formulario de Recoteje --}}
        <form action="{{ route('exams.revision.update', [$exam, $attempt]) }}" method="POST" class="space-y-6">
            @csrf

            @php
                $reviewIndex = 0;
            @endphp

            @forelse ($attempt->answers as $index => $answer)
                @php
                    $examQuestion = $answer->examQuestion;
                    $question = $examQuestion?->question;
                    $questionType = (string) ($question?->question_type ?? 'open');
                    $isAiQuestion = in_array($questionType, ['open', 'code'], true);
                    $maxPoints = round((float) ($examQuestion?->points_weight ?? 0), 2);
                    $payload = $answer->answer_payload;
                    $questionable = $question?->questionable;
                @endphp

                <x-card padding="p-6" class="space-y-4 {{ $isAiQuestion ? 'border-acode-tech/30' : 'border-slate-200 bg-slate-50/40' }}">
                    {{-- Encabezado del Reactivo --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3 gap-2">
                        <div class="flex items-center space-x-3">
                            <span class="w-7 h-7 rounded-full bg-acode-primary text-white text-xs font-bold flex items-center justify-center shrink-0">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <h2 class="font-bold text-acode-primary text-base">{{ $question?->title ?? 'Reactivo #' . $answer->exam_question_id }}</h2>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    {{ $questionTypeLabels[$questionType] ?? $questionType }} · Valor máximo: {{ number_format($maxPoints, 2) }} pts
                                </span>
                            </div>
                        </div>

                        @if ($isAiQuestion)
                            <x-badge variant="warning">Evaluado por IA · Recotejo manual habilitado</x-badge>
                        @else
                            <x-badge variant="neutral">Reactivo Objetivo (Solo lectura)</x-badge>
                        @endif
                    </div>

                    {{-- Enunciado servido al alumno --}}
                    <div class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Enunciado:</span>
                            @if ($answer->question_paraphrase_id !== null)
                                <x-badge variant="info">Enunciado con paráfrasis anti-copia</x-badge>
                            @endif
                        </div>
                        <p class="whitespace-pre-wrap break-words leading-relaxed">{{ $answer->served_stem_snapshot ?: ($question?->stem ?? 'Sin enunciado') }}</p>
                    </div>

                    {{-- Respuesta del Alumno (Renderizado seguro ante arrays o escalares) --}}
                    <div class="space-y-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Respuesta enviada por el alumno:</span>

                        @if ($questionType === 'multiple_choice')
                            @php
                                $selectedIdx = is_array($payload)
                                    ? ($payload['selected_option_index'] ?? null)
                                    : (is_numeric($payload) ? (int) $payload : null);
                                $optionsList = collect($questionable?->options_payload ?? []);
                                $selectedOption = $selectedIdx !== null
                                    ? $optionsList->firstWhere('index', (int) $selectedIdx)
                                    : null;
                                $correctIdx = $questionable?->correct_option_index;
                                $correctOption = $correctIdx !== null
                                    ? $optionsList->firstWhere('index', (int) $correctIdx)
                                    : null;
                            @endphp
                            <div class="p-3.5 bg-white border border-slate-200 rounded-xl text-sm space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <span class="font-semibold text-slate-700">Opción seleccionada:</span>
                                        <span class="ml-1.5 font-bold text-acode-primary">
                                            @if ($selectedOption)
                                                [{{ $selectedIdx }}] {{ $selectedOption['text'] ?? '' }}
                                            @elseif ($selectedIdx !== null)
                                                Índice #{{ $selectedIdx }}
                                            @else
                                                Sin respuesta
                                            @endif
                                        </span>
                                    </div>
                                    <x-badge :variant="$answer->is_correct ? 'accent' : 'danger'">
                                        {{ $answer->is_correct ? 'Correcta' : 'Incorrecta' }}
                                    </x-badge>
                                </div>
                                @if ($correctOption)
                                    <p class="text-xs text-slate-500">
                                        <strong>Respuesta correcta:</strong> [{{ $correctIdx }}] {{ $correctOption['text'] ?? '' }}
                                    </p>
                                @endif
                            </div>
                        @elseif ($questionType === 'matching')
                            @php
                                $leftMap = collect($questionable?->left_column_payload ?? [])->pluck('text', 'id');
                                $rightMap = collect($questionable?->right_column_payload ?? [])->pluck('text', 'id');
                                $resolvedPairs = [];

                                if (is_array($payload)) {
                                    foreach ($payload as $k => $v) {
                                        if (is_array($v) && isset($v['left_id'])) {
                                            $lId = (string) $v['left_id'];
                                            $rId = (string) ($v['right_id'] ?? '');
                                            $resolvedPairs[] = [
                                                'left' => (string) ($leftMap->get($lId) ?? $lId),
                                                'right' => $rId !== '' ? (string) ($rightMap->get($rId) ?? $rId) : 'Sin emparejar',
                                            ];
                                        } elseif (! is_array($v) && is_string($k)) {
                                            $resolvedPairs[] = [
                                                'left' => (string) ($leftMap->get($k) ?? $k),
                                                'right' => (string) ($rightMap->get((string) $v) ?? (string) $v),
                                            ];
                                        }
                                    }
                                }
                            @endphp
                            <div class="p-3.5 bg-white border border-slate-200 rounded-xl text-xs space-y-1.5">
                                @forelse ($resolvedPairs as $pair)
                                    <div class="flex items-center gap-2 text-slate-700">
                                        <span class="font-semibold text-acode-primary">{{ $pair['left'] }}</span>
                                        <span class="text-slate-400">→</span>
                                        <span class="font-medium">{{ $pair['right'] }}</span>
                                    </div>
                                @empty
                                    <p class="text-slate-500 italic">
                                        {{ is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : ($payload ?: 'Sin pares enviados') }}
                                    </p>
                                @endforelse
                            </div>
                        @elseif ($questionType === 'open')
                            @php
                                $openText = match (true) {
                                    is_array($payload) => (string) ($payload['text'] ?? $payload['answer'] ?? json_encode($payload, JSON_UNESCAPED_UNICODE)),
                                    is_scalar($payload) && trim((string) $payload) !== '' => (string) $payload,
                                    default => 'Sin respuesta enviada',
                                };
                            @endphp
                            <div class="p-4 bg-white border border-slate-200 rounded-xl text-sm leading-relaxed whitespace-pre-line text-slate-800">{{ $openText }}</div>
                        @elseif ($questionType === 'code')
                            @php
                                $codeText = match (true) {
                                    is_array($payload) => (string) ($payload['code'] ?? $payload['text'] ?? json_encode($payload, JSON_UNESCAPED_UNICODE)),
                                    is_scalar($payload) && trim((string) $payload) !== '' => (string) $payload,
                                    default => '// Sin código enviado',
                                };
                            @endphp
                            <div class="border border-slate-300 border-l-4 border-l-acode-primary bg-slate-100 rounded-lg p-4 font-mono text-[13px] text-acode-ink overflow-x-auto leading-relaxed">
                                <pre class="whitespace-pre-wrap">{{ $codeText }}</pre>
                            </div>
                        @endif
                    </div>

                    @if ($isAiQuestion)
                        <div class="bg-acode-surface/80 border border-acode-tech/25 rounded-xl p-4 space-y-2.5">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-bold text-acode-primary">
                                <span class="flex items-center gap-1.5">
                                    <x-icon name="code" class="w-4 h-4 text-acode-tech" />
                                    Evaluación Asistida por IA
                                </span>
                                <span>
                                    Similitud: {{ number_format((float) $answer->ai_similarity_score, 1) }}% ·
                                    {{ $answer->ai_meets_goal ? 'Cumple Objetivo' : 'No Cumple Objetivo' }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 italic">
                                "{{ $answer->ai_feedback ?? 'Sin retroalimentación de IA registrada.' }}"
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 pt-2 bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <input type="hidden" name="reviews[{{ $reviewIndex }}][answer_id]" value="{{ $answer->id }}">

                            <div class="sm:col-span-4">
                                <label class="block text-[13px] font-medium text-slate-800 mb-1">
                                    Calificación Docente (Máx. {{ number_format($maxPoints, 2) }} pts)
                                </label>
                                <x-form.input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="{{ $maxPoints }}"
                                    name="reviews[{{ $reviewIndex }}][score]"
                                    :value="old('reviews.' . $reviewIndex . '.score', $answer->teacher_recoteje_score ?? $answer->points_awarded)"
                                    required
                                    :disabled="! $canSubmitRecoteje"
                                    class="font-bold font-mono"
                                />
                            </div>

                            <div class="sm:col-span-8">
                                <label class="block text-[13px] font-medium text-slate-800 mb-1">
                                    Notas de Retroalimentación del Maestro (Opcional)
                                </label>
                                <x-form.input
                                    type="text"
                                    name="reviews[{{ $reviewIndex }}][notes]"
                                    :value="old('reviews.' . $reviewIndex . '.notes', $answer->teacher_recoteje_notes)"
                                    maxlength="1000"
                                    :disabled="! $canSubmitRecoteje"
                                    placeholder="Ej. Argumentación clara, pero faltó precisar el concepto clave..."
                                />
                            </div>
                        </div>

                        @php
                            $reviewIndex++;
                        @endphp
                    @else
                        <div class="flex items-center justify-between text-xs text-slate-600 pt-1 border-t border-slate-100">
                            <span>Puntaje asignado automáticamente (solo lectura):</span>
                            <span class="font-bold font-mono text-acode-primary">
                                {{ number_format((float) $answer->points_awarded, 2) }} / {{ number_format($maxPoints, 2) }} pts
                            </span>
                        </div>
                    @endif
                </x-card>
            @empty
                <x-empty-state
                    icon="document"
                    title="Sin respuestas registradas"
                    message="Este intento no contiene respuestas registradas para recotejar."
                />
            @endforelse

            @if ($reviewIndex > 0 && $canSubmitRecoteje)
                <div class="sticky bottom-4 bg-white/95 backdrop-blur-md p-4 rounded-xl border border-slate-200 shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <span class="text-sm font-semibold text-slate-700">
                        Se actualizarán <strong>{{ $reviewIndex }}</strong> reactivo(s) cualitativo(s) en este intento.
                    </span>
                    <x-button type="submit" variant="primary" size="lg">
                        <x-icon name="save" class="w-4 h-4" />
                        Guardar Recoteje Docente
                    </x-button>
                </div>
            @endif
        </form>
    </div>
@endsection
