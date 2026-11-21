{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/review-attempt.blade.php (MPW-V3 §4.1.2, §7, §8, RF-IAR-001..014, RF-EVA-004..009, RW-11, ADR-059) --}}
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
        $extractQualitativeText = static function (mixed $payload): string {
            if (is_string($payload)) {
                return trim($payload);
            }
            if (is_array($payload)) {
                foreach (['text', 'code', 'answer', 'given_answer'] as $key) {
                    if (isset($payload[$key]) && is_string($payload[$key])) {
                        return trim($payload[$key]);
                    }
                }
            }

            return '';
        };
        $qualitativeAnswers = $attempt->answers->filter(
            static fn ($ans): bool => in_array((string) ($ans->examQuestion?->question?->question_type ?? ''), ['open', 'code'], true)
        );
        $pendingUsableAiCount = $qualitativeAnswers->filter(
            static fn ($ans): bool => $extractQualitativeText($ans->answer_payload) !== ''
                && $ans->ai_model_used !== 'none'
                && ($ans->ai_evaluated_at === null || (bool) $ans->ai_is_fallback)
        )->count();
        $otherErrors = collect($errors->getMessages())
            ->except(['ai_credits'])
            ->flatten();
        $attemptStatusLabels = [
            'in_progress' => 'En progreso',
            'submitted' => 'Enviado',
            'expired' => 'Expirado',
            'cancelled_by_incident' => 'Cancelado por incidencia',
            'graded' => 'Calificado',
        ];
        $attemptStatusLabel = $attemptStatusLabels[$attempt->status] ?? 'En curso';
    @endphp

    <div class="space-y-6">
        {{-- Breadcrumbs y Encabezado --}}
        <x-page-header
            title="Recoteje de intento"
            subtitle="Revisa las respuestas del estudiante, solicita sugerencias de IA bajo demanda y ajusta la calificación definitiva."
            :breadcrumbs="[
                ['label' => 'Revisión', 'href' => route('teacher.reviews.index')],
                ['label' => $exam->title, 'href' => route('exams.revision.index', $exam)],
                ['label' => 'Recoteje de intento'],
            ]"
        >
            <x-slot:actions>
                <x-button :href="route('exams.revision.index', $exam)" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- Alerta específica de saldo de créditos IA (RF-EVA-004 / RF-EVA-005) --}}
        @if ($errors->has('ai_credits'))
            <div class="p-4 rounded-xl bg-acode-danger/10 border border-acode-danger/35 text-sm text-acode-primary flex items-start gap-3" role="alert">
                <x-icon name="warning" class="w-5 h-5 shrink-0 text-acode-danger mt-0.5" />
                <div class="space-y-1">
                    <p class="font-bold text-acode-danger">{{ $errors->first('ai_credits') }}</p>
                    <p class="text-xs text-slate-700">
                        Puedes calificar y guardar manualmente todas las respuestas cualitativas sin necesidad de créditos de IA, o revisar tu plan en
                        <a href="{{ route('teacher.subscription.index') }}" class="font-semibold text-acode-tech hover:underline">Suscripción</a>.
                    </p>
                </div>
            </div>
        @endif

        @if ($otherErrors->isNotEmpty())
            <div class="p-4 rounded-xl bg-acode-danger/10 border border-acode-danger/30 text-acode-danger text-sm" role="alert">
                <p class="font-bold mb-1">Por favor corrige los siguientes errores:</p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($otherErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $canSubmitRecoteje)
            <div class="rounded-xl border border-acode-warning/50 bg-acode-warning/15 p-4 text-sm text-acode-primary flex items-start gap-2.5">
                <x-icon name="lock" class="w-5 h-5 shrink-0 text-acode-warning-dark mt-0.5" />
                <div>
                    <p class="font-bold">Intento en modo solo lectura ({{ $attemptStatusLabel }})</p>
                    <p class="text-xs text-slate-700 mt-0.5">
                        Solo los intentos en estado <strong>entregado</strong> admiten recoteje docente y evaluación bajo demanda con IA.
                    </p>
                </div>
            </div>
        @endif

        {{-- Banner de saldo de créditos IA y evaluación en lote (FUERA del form de recoteje) --}}
        @if ($qualitativeAnswers->isNotEmpty())
            <x-card padding="p-4 sm:p-5" class="border-acode-tech/30 bg-gradient-to-r from-white via-white to-acode-surface/70">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-acode-tech/10 text-acode-tech">
                            <x-nav-icon name="ai-credits" class="w-5 h-5" />
                        </div>
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-acode-tech">Créditos de IA disponibles</span>
                                @if (! empty($aiCreditSummary['is_unlimited']))
                                    <x-badge variant="accent">Ilimitados</x-badge>
                                @elseif (! empty($aiCreditSummary['is_suspended']))
                                    <x-badge variant="danger">Suscripción suspendida (0 disponibles)</x-badge>
                                @else
                                    <x-badge :variant="$aiCreditSummary['variant'] ?? 'tech'">
                                        {{ number_format((int) ($aiCreditSummary['remaining'] ?? 0)) }} / {{ number_format((int) ($aiCreditSummary['limit'] ?? 0)) }} disponibles
                                    </x-badge>
                                @endif
                            </div>
                            <p class="text-xs text-slate-600">
                                Plan <strong>{{ $aiCreditSummary['package_name'] ?? 'Docente' }}</strong>
                                · {{ $aiCreditSummary['cycle_label'] ?? 'Ciclo vigente' }}.
                                La IA actúa como sugerencia bajo demanda; las respuestas en blanco o en respaldo local no descuentan créditos.
                            </p>
                        </div>
                    </div>

                    @if ($canSubmitRecoteje && $pendingUsableAiCount > 0)
                        <form
                            method="POST"
                            action="{{ route('exams.revision.evaluate-ai-batch', [$exam, $attempt]) }}"
                            class="shrink-0"
                        >
                            @csrf
                            <x-button type="submit" variant="secondary" size="md" class="w-full sm:w-auto">
                                <x-icon name="code" class="w-4 h-4" />
                                Evaluar pendientes con IA ({{ $pendingUsableAiCount }})
                            </x-button>
                        </form>
                    @endif
                </div>
            </x-card>
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

        {{-- Formulario principal de Recoteje Docente (SIN formularios anidados) --}}
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
                    $hasAnswerText = $isAiQuestion && $extractQualitativeText($payload) !== '';
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
                            @if ($answer->ai_evaluated_at === null)
                                <x-badge variant="warning">Pendiente de evaluación · Recotejo manual habilitado</x-badge>
                            @elseif ($answer->ai_model_used === 'none')
                                <x-badge variant="neutral">Respuesta en blanco (0 pts)</x-badge>
                            @elseif ($answer->ai_is_fallback)
                                <x-badge variant="warning">Respaldo local · Recotejo manual habilitado</x-badge>
                            @else
                                <x-badge variant="accent">Evaluado por IA · Recotejo manual habilitado</x-badge>
                            @endif
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
                                $selectedLetter = is_numeric($selectedIdx) ? chr(65 + (int) $selectedIdx) : '';
                                $correctIdx = $questionable?->correct_option_index;
                                $correctOption = $correctIdx !== null
                                    ? $optionsList->firstWhere('index', (int) $correctIdx)
                                    : null;
                                $correctLetter = is_numeric($correctIdx) ? chr(65 + (int) $correctIdx) : '';
                            @endphp
                            <div class="p-3.5 bg-white border border-slate-200 rounded-xl text-sm space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <span class="font-semibold text-slate-700">Opción seleccionada:</span>
                                        <span class="ml-1.5 font-bold text-acode-primary">
                                            @if ($selectedOption)
                                                {{ $selectedLetter }}. {{ $selectedOption['text'] ?? '' }}
                                            @elseif ($selectedIdx !== null)
                                                Opción {{ $selectedLetter }}
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
                                        <strong>Respuesta correcta:</strong> {{ $correctLetter }}. {{ $correctOption['text'] ?? '' }}
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
                            <div class="border border-slate-300 border-l-4 border-l-acode-primary bg-slate-100 rounded-lg p-4 font-mono text-xs text-acode-ink overflow-x-auto leading-relaxed">
                                <pre class="whitespace-pre-wrap">{{ $codeText }}</pre>
                            </div>
                        @endif
                    </div>

                    @if ($isAiQuestion)
                        {{-- Panel de Evaluación Asistida por IA bajo demanda (RF-IAR-001..014) --}}
                        @if ($answer->ai_evaluated_at === null)
                            <div class="bg-acode-surface/60 border border-slate-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-start gap-2.5">
                                    <x-icon name="clock" class="w-4 h-4 text-acode-tech shrink-0 mt-0.5" />
                                    <div class="text-xs text-slate-700 space-y-0.5">
                                        <p class="font-semibold text-acode-primary">
                                            Pendiente de evaluación (califica manualmente o solicita una sugerencia de IA bajo demanda)
                                        </p>
                                        @if (! $hasAnswerText)
                                            <p class="text-slate-500">
                                                Respuesta en blanco: no consume créditos de IA y corresponde asignar 0.00 pts.
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                @if ($canSubmitRecoteje && $hasAnswerText)
                                    <x-button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onclick="window.acodeSubmitAiEvaluation({{ $answer->id }}, false, false)"
                                        class="shrink-0"
                                    >
                                        <x-icon name="code" class="w-4 h-4" />
                                        Evaluar con IA
                                    </x-button>
                                @endif
                            </div>
                        @else
                            <div class="bg-acode-surface/80 border border-acode-tech/25 rounded-xl p-4 space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="flex items-center gap-1.5 text-xs font-bold text-acode-primary">
                                            <x-icon name="code" class="w-4 h-4 text-acode-tech" />
                                            Evaluación Asistida por IA
                                        </span>

                                        @if ($answer->ai_model_used === 'none')
                                            <x-badge variant="neutral">Respuesta en blanco (0 pts)</x-badge>
                                        @elseif ($answer->ai_is_fallback)
                                            <x-badge variant="warning">Respaldo local (sin cobro)</x-badge>
                                        @else
                                            <x-badge variant="info">Sugerencia IA · {{ $answer->ai_model_used ?? 'Gemini' }}</x-badge>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-acode-primary">
                                        <span>Similitud: {{ number_format((float) $answer->ai_similarity_score, 1) }}%</span>
                                        <span class="text-slate-400">·</span>
                                        <span class="{{ $answer->ai_meets_goal ? 'text-acode-accent-dark' : 'text-acode-danger' }}">
                                            {{ $answer->ai_meets_goal ? 'Cumple objetivo' : 'No cumple objetivo' }}
                                        </span>
                                    </div>
                                </div>

                                <p class="text-xs text-slate-600 italic leading-relaxed">
                                    "{{ $answer->ai_feedback ?? 'Sin retroalimentación de IA registrada.' }}"
                                </p>

                                @if ($canSubmitRecoteje && $answer->ai_model_used !== 'none' && $hasAnswerText)
                                    <div class="pt-2 border-t border-acode-tech/15 flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-xs text-slate-500">
                                            Evaluado el {{ $answer->ai_evaluated_at->format('d/m/Y H:i') }}
                                        </span>

                                        <x-button
                                            type="button"
                                            variant="neutral"
                                            size="sm"
                                            onclick="window.acodeRequestAiReevaluation({{ $answer->id }})"
                                        >
                                            <x-icon name="code" class="w-3.5 h-3.5 text-acode-tech" />
                                            Re-evaluar con IA
                                        </x-button>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Campos de recoteje docente --}}
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 pt-2 bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <input type="hidden" name="reviews[{{ $reviewIndex }}][answer_id]" value="{{ $answer->id }}">

                            <div class="sm:col-span-4">
                                <label class="block text-sm font-medium text-slate-800 mb-1">
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
                                <label class="block text-sm font-medium text-slate-800 mb-1">
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

        {{-- Formularios dedicados para evaluación/re-evaluación individual con IA (FUERA del form de recoteje) --}}
        @if ($canSubmitRecoteje)
            @foreach ($qualitativeAnswers as $qualAnswer)
                <form
                    id="ai-eval-form-{{ $qualAnswer->id }}"
                    action="{{ route('exams.revision.evaluate-ai', [$exam, $attempt, $qualAnswer]) }}"
                    method="POST"
                    class="hidden"
                >
                    @csrf
                    <input type="hidden" name="is_reevaluation" value="0">
                    <input type="hidden" name="dismiss_warning" value="0">
                </form>
            @endforeach
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const skipReevaluationWarning = @js((bool) ($skipAiReevaluationWarning ?? false));

        window.acodeSubmitAiEvaluation = function (answerId, isReevaluation, dismissWarning) {
            const form = document.getElementById(`ai-eval-form-${answerId}`);
            if (!form) {
                return;
            }
            const reevalInput = form.querySelector('input[name="is_reevaluation"]');
            const dismissInput = form.querySelector('input[name="dismiss_warning"]');
            if (reevalInput) {
                reevalInput.value = isReevaluation ? '1' : '0';
            }
            if (dismissInput) {
                dismissInput.value = dismissWarning ? '1' : '0';
            }
            form.submit();
        };

        window.acodeRequestAiReevaluation = function (answerId) {
            if (skipReevaluationWarning || typeof window.Swal === 'undefined') {
                window.acodeSubmitAiEvaluation(answerId, true, false);
                return;
            }

            window.Swal.fire({
                icon: 'warning',
                iconColor: '#F4B942',
                title: '¿Re-evaluar respuesta con IA?',
                html: `
                    <p class="text-sm text-slate-600 leading-relaxed mb-4">
                        Solicitar una nueva evaluación con IA consumirá <strong>1 crédito adicional</strong> de tu saldo si produce una evaluación utilizable. Si ocurre un respaldo local no se descontarán créditos.
                    </p>
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer select-none">
                        <input id="acode-dismiss-ai-warning" type="checkbox" class="rounded border-slate-300 text-acode-primary focus:ring-acode-tech">
                        <span>No mostrar de nuevo</span>
                    </label>
                `,
                showCancelButton: true,
                confirmButtonColor: '#142B4A',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Re-evaluar con IA',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl shadow-xl font-sans',
                    confirmButton: 'px-5 py-2.5 rounded-lg text-sm font-semibold',
                    cancelButton: 'px-5 py-2.5 rounded-lg text-sm font-semibold'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const checkbox = document.getElementById('acode-dismiss-ai-warning');
                    const dismiss = Boolean(checkbox && checkbox.checked);
                    window.acodeSubmitAiEvaluation(answerId, true, dismiss);
                }
            });
        };
    })();
</script>
@endpush
