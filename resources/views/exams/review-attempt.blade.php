{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/review-attempt.blade.php (MPW-V3 §4.1.2, §7, §8, RF-IAR-001..014, RF-EVA-004..009, RW-11, ADR-059) --}}
@extends('layouts.app')

@section('title', 'Revisión de entrega: ' . ($attempt->student?->full_name ?? 'Estudiante'))

@section('content')
    @php
        $canSubmitRecoteje = $attempt->status === 'submitted';
        $questionTypeLabels = [
            'multiple_choice' => 'Opción múltiple',
            'matching' => 'Relación de columnas',
            'open' => 'Pregunta abierta',
            'code' => 'Código fuente',
        ];
        $qualitativeAnswers = $attempt->answers->filter(
            static fn ($ans): bool => in_array((string) ($ans->examQuestion?->question?->question_type ?? ''), ['open', 'code'], true)
        );
        $pendingUsableAiCount = $qualitativeAnswers->filter(
            static fn ($ans): bool => $ans->canBeReviewedQualitatively()
                && ($ans->ai_evaluated_at === null || (bool) $ans->ai_is_fallback)
        )->count();
        $availableAiCredits = (int) ($aiCreditSummary['remaining'] ?? 0);
        $aiQuotaVariant = $aiCreditSummary['variant'] ?? 'tech';
        $nextAiResetAt = $aiCreditSummary['next_reset_at'] ?? null;
        $otherErrors = collect($errors->getMessages())
            ->except(['ai_credits'])
            ->flatten();
        $gradeScale = str_replace('-', '–', (string) $exam->grading_scale);
        $formatPoints = static fn ($points): string => number_format((float) $points, 2, ',', '');
        $attemptStatusLabels = [
            'in_progress' => 'En progreso',
            'submitted' => 'Entregado',
            'expired' => 'Expirado',
            'cancelled_by_incident' => 'Cancelado por incidencias',
            'graded' => 'Calificado',
        ];
        $attemptStatusLabel = $attempt->wasStoppedByTeacher()
            ? 'Finalizado por detención del examen'
            : ($attemptStatusLabels[$attempt->status] ?? 'En curso');
    @endphp

    <div
        class="space-y-6 [&_a]:scroll-mt-20 [&_button]:scroll-mt-20 [&_input]:scroll-mt-20"
        x-data="acodeReviewAttempt(@js((bool) $skipAiReevaluationWarning), @js((bool) session('review_ai_queued')))"
        @click="guardNavigation($event)"
        @beforeunload.window="guardUnload($event)"
        @pageshow.window="resetSubmission()"
    >
        <p role="status" class="sr-only" x-text="submissionMessage"></p>

        {{-- Encabezado y ruta de navegación --}}
        <x-page-header
            title="Revisión de entrega"
            :institutional="true"
            :breadcrumbs="[
                ['label' => 'Revisión', 'href' => $reviewCenterUrl],
                ['label' => $exam->title, 'href' => $reviewBackUrl],
                ['label' => 'Revisión de entrega'],
            ]"
        >
            <x-slot:actions>
                <x-button :href="$reviewBackUrl" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- Alerta específica de saldo de créditos IA (RF-EVA-004 / RF-EVA-005) --}}
        @if ($errors->has('ai_credits'))
            <div class="p-4 rounded-xl bg-acode-danger/10 border border-acode-danger/35 text-sm text-acode-primary flex items-start gap-3" role="alert" data-review-feedback>
                <x-icon name="warning" class="w-5 h-5 shrink-0 text-acode-danger mt-0.5" />
                <div class="space-y-1">
                    <p class="font-bold text-acode-danger-dark">{{ $errors->first('ai_credits') }}</p>
                    <p class="text-xs text-slate-700">
                        Puedes calificar y guardar manualmente todas las respuestas cualitativas sin necesidad de créditos de IA, o revisar tu plan en
                        <a href="{{ route('teacher.subscription.index') }}" class="font-semibold text-acode-tech hover:underline">Suscripción</a>.
                    </p>
                </div>
            </div>
        @endif

        @if ($otherErrors->isNotEmpty())
            <div class="p-4 rounded-xl bg-acode-danger/10 border border-acode-danger/30 text-acode-danger-dark text-sm" role="alert" data-review-feedback>
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
                        Solo los intentos en estado <strong>entregado</strong> admiten revisión docente y evaluación bajo demanda con IA.
                    </p>
                </div>
            </div>
        @endif

        <x-card padding="p-4 sm:p-5" class="border-l-4 border-l-acode-primary">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="min-w-0">
                    <dt class="text-xs font-semibold text-slate-600">Estudiante</dt>
                    <dd class="mt-1 break-words text-base font-semibold text-acode-primary">{{ $attempt->student?->full_name ?? 'Estudiante' }}</dd>
                    <dd class="break-words text-xs text-slate-600">{{ $attempt->student?->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-600">Fecha de envío</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-700">
                        {{ $attempt->submitted_at?->format('d/m/Y H:i') ?? 'Sin envío formal' }}
                    </dd>
                    <dd class="text-xs text-slate-600">Incidentes reportados: {{ (int) $attempt->incidents_count }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-600">Puntaje objetivo</dt>
                    <dd class="mt-1 text-sm font-semibold tabular-nums text-slate-800">
                        {{ $formatPoints($attempt->objective_score) }} pts
                    </dd>
                    <dt class="mt-2 text-xs font-semibold text-slate-600">Estimado IA</dt>
                    <dd class="text-sm tabular-nums text-slate-700">
                        {{ $aiSuggestedScore === null ? 'Sin sugerencia' : $formatPoints($aiSuggestedScore) . ' pts' }}
                    </dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-acode-surface p-3">
                    <dt class="text-xs font-semibold text-acode-primary">Calificación final</dt>
                    <dd class="mt-0.5 text-xl font-semibold tabular-nums text-acode-primary">
                        {{ $attempt->final_score === null ? 'Pendiente' : $formatPoints($attempt->final_score) }}
                    </dd>
                    <dd class="text-xs font-medium text-slate-600">Escala: {{ $gradeScale }}</dd>
                </div>
            </dl>
        </x-card>

        @if ($canSubmitRecoteje)
            <div x-cloak x-show="refreshAvailable" class="flex flex-wrap items-center gap-3">
                <x-button
                    type="button"
                    variant="secondary"
                    size="sm"
                    loading-when="submission === 'refresh'"
                    ::disabled="submission !== null"
                    @click="refreshReview()"
                >
                    Actualizar sugerencias de IA
                </x-button>
                <p x-cloak x-show="refreshError" x-text="refreshError" class="text-sm text-acode-danger-dark" role="alert"></p>
            </div>
        @endif

        @if ($canSubmitRecoteje && $pendingUsableAiCount > 0)
            <x-card padding="p-4 sm:p-5">
                <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-center">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="text-sm font-semibold text-acode-primary">Créditos de IA</span>
                            @if (! empty($aiCreditSummary['is_unlimited']))
                                <x-badge variant="accent">Ilimitados</x-badge>
                            @else
                                <x-badge :variant="$aiQuotaVariant" @class(['!text-acode-danger-dark' => $aiQuotaVariant === 'danger'])>
                                    {{ number_format($availableAiCredits) }} {{ $availableAiCredits === 1 ? 'crédito disponible' : 'créditos disponibles' }}
                                </x-badge>
                            @endif
                        </div>
                        <p id="review-batch-credit-help" class="text-xs text-slate-600">
                            @if (! empty($aiCreditSummary['is_suspended']))
                                Suscripción suspendida. Puedes calificar manualmente.
                            @elseif (empty($aiCreditSummary['is_unlimited']) && $availableAiCredits === 0)
                                Has alcanzado el límite. Puedes calificar manualmente.
                                @if ($nextAiResetAt)
                                    Próxima reposición si tu plan sigue activo: <time datetime="{{ $nextAiResetAt->toIso8601String() }}">{{ $nextAiResetAt->format('d/m/Y') }} a las {{ $nextAiResetAt->format('H:i') }}</time>.
                                @endif
                            @else
                                @if ($aiQuotaVariant === 'warning')
                                    Cerca del límite.
                                @endif
                                Se descuenta un crédito por sugerencia de IA utilizable. El respaldo local no tiene costo.
                            @endif
                        </p>
                    </div>
                    <form
                        method="POST"
                        action="{{ route('exams.revision.evaluate-ai-batch', [$exam, $attempt, ...$reviewContext]) }}"
                        class="shrink-0"
                        @submit="submitAi($event, 'batch')"
                    >
                        @csrf
                        <x-button
                            type="submit"
                            variant="secondary"
                            size="md"
                            loading-when="submission === 'batch'"
                            ::disabled="submission !== null"
                            aria-describedby="review-batch-credit-help"
                            class="h-auto min-h-10 w-full py-1.5 whitespace-normal sm:w-auto"
                        >
                            <span x-show="submission !== 'batch'" aria-hidden="true"><x-icon name="code" class="h-4 w-4" /></span>
                            Evaluar pendientes con IA ({{ $pendingUsableAiCount }})
                        </x-button>
                    </form>
                </div>
            </x-card>
        @endif

        <form
            x-ref="reviewForm"
            action="{{ route('exams.revision.update', [$exam, $attempt, ...$reviewContext]) }}"
            method="POST"
            class="space-y-5"
            @submit="submitReview($event)"
        >
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
                    $hasAnswerText = $isAiQuestion && $answer->canBeReviewedQualitatively();
                    $isBlank = $isAiQuestion && ! $hasAnswerText;
                    $suggestedPoints = $hasAnswerText ? $answer->aiSuggestedPoints() : null;
                    $answerText = $payload;

                    if ($hasAnswerText && is_array($payload)) {
                        $answerText = $questionType === 'code'
                            ? ($payload['code'] ?? $payload['text'] ?? null)
                            : ($payload['text'] ?? $payload['answer'] ?? null);
                    }

                    if ($hasAnswerText && ! is_string($answerText)) {
                        $answerText = \App\Models\StudentAnswer::qualitativeText($payload);
                    }
                @endphp

                <x-card padding="p-4 sm:p-6">
                    <div class="space-y-4">
                        {{-- Encabezado del Reactivo --}}
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3 gap-2">
                            <div class="flex items-center space-x-3">
                                <span class="w-7 h-7 rounded-full bg-acode-primary text-white text-xs font-bold flex items-center justify-center shrink-0">
                                    {{ $index + 1 }}
                                </span>
                            <div class="min-w-0">
                                    <h2 class="break-words text-xl font-semibold leading-7 text-acode-primary">{{ $question?->title ?? 'Reactivo #' . $answer->exam_question_id }}</h2>
                                    <span class="text-xs font-medium text-slate-600">
                                        {{ $questionTypeLabels[$questionType] ?? $questionType }} · Valor máximo: {{ $formatPoints($maxPoints) }} pts
                                    </span>
                                </div>
                            </div>

                            @if ($isBlank)
                                <x-badge variant="neutral" class="self-start sm:self-auto">Respuesta en blanco (0 pts)</x-badge>
                            @elseif (! $isAiQuestion)
                                <x-badge variant="neutral" class="self-start sm:self-auto">Reactivo objetivo (solo lectura)</x-badge>
                            @endif
                        </div>

                        {{-- Enunciado servido al alumno --}}
                        <div class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-slate-600">Enunciado</span>
                                @if ($answer->question_paraphrase_id !== null)
                                    <x-badge variant="info">Enunciado con paráfrasis anti-copia</x-badge>
                                @endif
                            </div>
                            <p class="whitespace-pre-wrap break-words leading-relaxed">{{ $answer->served_stem_snapshot ?: ($question?->stem ?? 'Sin enunciado') }}</p>
                            @if ($question)
                                <x-question.files class="mt-3" :question-id="$question->id" :has-image="$question->image_path !== null" :has-pdf="$question->pdf_path !== null" />
                            @endif
                        </div>

                        @unless ($isBlank)
                            <div class="space-y-2">
                                <span class="text-xs font-semibold text-slate-600">Respuesta enviada por el estudiante</span>

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
                                        <x-badge :variant="$answer->is_correct ? 'accent' : 'danger'" @class(['!text-acode-danger-dark' => ! $answer->is_correct])>
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
                                                <span class="text-slate-600" aria-hidden="true">→</span>
                                                <span class="font-medium">{{ $pair['right'] }}</span>
                                            </div>
                                        @empty
                                            <p class="text-slate-500 italic">
                                                {{ is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : ($payload ?: 'Sin pares enviados') }}
                                            </p>
                                        @endforelse
                                    </div>
                                @elseif ($questionType === 'open')
                                    <p class="whitespace-pre-wrap break-words rounded-xl border border-slate-200 bg-white p-4 text-sm leading-relaxed text-slate-800">{{ $answerText }}</p>
                                @elseif ($questionType === 'code')
                                    <pre class="whitespace-pre-wrap break-words rounded-lg border border-slate-300 border-l-4 border-l-acode-primary bg-slate-100 p-4 font-mono text-[13px] leading-5 text-acode-ink"><code>{{ $answerText }}</code></pre>
                                @endif
                            </div>
                        @endunless

                        @if ($hasAnswerText)
                            @php
                                $fieldPrefix = 'review-' . $answer->id;
                                $scoreId = $fieldPrefix . '-score';
                                $notesId = $fieldPrefix . '-notes';
                                $fieldKey = 'reviews.' . $reviewIndex;
                                $draftKey = 'review_draft.' . $answer->id;
                                $teacherScore = $answer->teacher_recoteje_score;
                                $recordedPoints = (float) $answer->points_awarded;
                                $initialScore = $teacherScore ?? $suggestedPoints ?? ($recordedPoints !== 0.0 ? $recordedPoints : null);
                                $initialScore = $initialScore === null ? '' : $formatPoints($initialScore);
                                $initialNotes = str_replace(["\r", "\n"], '', $answer->teacher_recoteje_notes ?? '');
                                $scoreHelp = match (true) {
                                    $teacherScore !== null => 'El valor inicial es el puntaje docente guardado.',
                                    $suggestedPoints !== null && $answer->ai_is_fallback => 'El valor inicial procede del respaldo local. Revísalo antes de guardar.',
                                    $suggestedPoints !== null => 'El valor inicial es una sugerencia de IA. Revísalo antes de guardar.',
                                    $initialScore !== '' => 'El valor inicial es el puntaje registrado en el intento.',
                                    default => 'Escribe el puntaje asignado a esta respuesta.',
                                };
                                $scoreError = $errors->first($fieldKey . '.score');
                                $notesError = $errors->first($fieldKey . '.notes');
                            @endphp

                            <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 lg:grid-cols-12">
                                @if ($canSubmitRecoteje)
                                    <input type="hidden" name="reviews[{{ $reviewIndex }}][answer_id]" value="{{ $answer->id }}">
                                @endif

                                <x-form.group
                                    :name="$fieldKey . '.score'"
                                    :field-id="$scoreId"
                                    :label="'Puntaje docente (máx. ' . $formatPoints($maxPoints) . ' pts)'"
                                    :help-text="$scoreHelp"
                                    :required="$canSubmitRecoteje"
                                    :accessible="true"
                                    class="lg:col-span-5"
                                >
                                    <x-form.input
                                        :id="$scoreId"
                                        name="reviews[{{ $reviewIndex }}][score]"
                                        numeric="decimal"
                                        min="0"
                                        :max="$maxPoints"
                                        :value="old($fieldKey . '.score', old($draftKey . '.score', $initialScore))"
                                        :required="$canSubmitRecoteje"
                                        :disabled="! $canSubmitRecoteje"
                                        :has-error="(bool) $scoreError"
                                        :accessible="true"
                                        :aria-describedby="$scoreId . '-help ' . ($scoreError ? $scoreId . '-error ' : '') . $scoreId . '-validation'"
                                        data-review-answer="{{ $answer->id }}"
                                        data-review-field="score"
                                        data-review-initial="{{ $initialScore }}"
                                        data-review-server-invalid="{{ $scoreError ? 'true' : 'false' }}"
                                        @invalid="showFieldError($event)"
                                        @input="clearFieldError($event)"
                                        ::aria-invalid="fieldErrors[$el.id] || $el.dataset.reviewServerInvalid === 'true' ? 'true' : null"
                                        class="font-semibold tabular-nums aria-[invalid=true]:border-acode-danger"
                                    />
                                    <p id="{{ $scoreId }}-validation" x-cloak x-show="fieldErrors['{{ $scoreId }}']" x-text="fieldErrors['{{ $scoreId }}']" class="mt-1 text-xs font-medium text-acode-danger-dark" role="alert"></p>
                                </x-form.group>

                                <x-form.group
                                    :name="$fieldKey . '.notes'"
                                    :field-id="$notesId"
                                    label="Notas de retroalimentación (opcional)"
                                    :accessible="true"
                                    class="lg:col-span-7"
                                >
                                    <x-form.input
                                        :id="$notesId"
                                        name="reviews[{{ $reviewIndex }}][notes]"
                                        :value="old($fieldKey . '.notes', old($draftKey . '.notes', $initialNotes))"
                                        maxlength="1000"
                                        :disabled="! $canSubmitRecoteje"
                                        :has-error="(bool) $notesError"
                                        :accessible="true"
                                        :aria-describedby="$notesError ? $notesId . '-error' : null"
                                        data-review-answer="{{ $answer->id }}"
                                        data-review-field="notes"
                                        data-review-initial="{{ $initialNotes }}"
                                    />
                                </x-form.group>
                            </div>
                            @php($reviewIndex++)

                            <div class="space-y-3 rounded-xl border border-slate-200 bg-acode-surface/60 p-4">
                                @if ($answer->ai_evaluated_at === null)
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <p class="text-xs font-semibold text-slate-700">Sin sugerencia de IA para esta respuesta</p>
                                        @if ($canSubmitRecoteje)
                                            <x-button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                :loading-when="'submission === ' . $answer->id"
                                                ::disabled="submission !== null"
                                                @click="requestAi({{ $answer->id }}, false, $event.currentTarget)"
                                            >
                                                <span x-show="submission !== {{ $answer->id }}" aria-hidden="true"><x-icon name="code" class="h-4 w-4" /></span>
                                                Evaluar con IA
                                            </x-button>
                                        @endif
                                    </div>
                                @else
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-semibold text-acode-primary">Evaluación Asistida por IA</h3>
                                            @if ($answer->ai_is_fallback)
                                                <x-badge variant="warning">Respaldo local (sin cobro)</x-badge>
                                            @endif
                                        </div>
                                        @if ($suggestedPoints !== null)
                                            <span class="text-xs font-semibold tabular-nums text-acode-primary">{{ $formatPoints($suggestedPoints) }} pts sugeridos</span>
                                        @endif
                                    </div>

                                    @if ($answer->ai_is_fallback)
                                        <p class="text-xs leading-5 text-slate-700">
                                            El respaldo local estima coincidencias de palabras. Revisa la respuesta antes de asignar el puntaje.
                                        </p>
                                    @else
                                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs font-semibold text-slate-700">
                                            @if ($answer->ai_similarity_score !== null)
                                                <span>Similitud: {{ number_format((float) $answer->ai_similarity_score, 1, ',', '') }} %</span>
                                            @endif
                                            @if ($answer->ai_meets_goal !== null)
                                                <span class="{{ $answer->ai_meets_goal ? 'text-acode-primary' : 'text-acode-danger-dark' }}">
                                                    {{ $answer->ai_meets_goal ? 'Cumple objetivo' : 'No cumple objetivo' }}
                                                </span>
                                            @endif
                                        </div>
                                        @if (filled($answer->ai_feedback))
                                            <p class="whitespace-pre-wrap break-words text-xs leading-5 text-slate-700">{{ $answer->ai_feedback }}</p>
                                        @endif
                                    @endif

                                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-2">
                                        <span class="text-xs text-slate-600">
                                            Evaluado el {{ $answer->ai_evaluated_at->format('d/m/Y H:i') }}
                                        </span>

                                        @if ($canSubmitRecoteje)
                                            <x-button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                :loading-when="'submission === ' . $answer->id"
                                                ::disabled="submission !== null"
                                                @click="requestAi({{ $answer->id }}, true, $event.currentTarget)"
                                            >
                                                <span x-show="submission !== {{ $answer->id }}" aria-hidden="true"><x-icon name="code" class="h-4 w-4" /></span>
                                                Re-evaluar con IA
                                            </x-button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @elseif (! $isAiQuestion)
                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-2 text-xs text-slate-600">
                                <span>Puntaje asignado automáticamente</span>
                                <span class="font-semibold tabular-nums text-acode-primary">
                                    {{ $formatPoints($answer->points_awarded) }} / {{ $formatPoints($maxPoints) }} pts
                                </span>
                            </div>
                        @endif
                    </div>
                </x-card>
            @empty
                <x-empty-state
                    icon="document"
                    title="Sin respuestas registradas"
                    message="Este intento no contiene respuestas registradas para revisar."
                />
            @endforelse

            @if ($reviewIndex > 0 && $canSubmitRecoteje)
                <x-card padding="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-700">
                            {{ $reviewIndex === 1 ? 'Se actualizará 1 reactivo en este intento.' : "Se actualizarán {$reviewIndex} reactivos en este intento." }}
                        </p>
                        <x-button
                            type="submit"
                            variant="primary"
                            size="lg"
                            loading-when="submission === 'review'"
                            ::disabled="submission !== null"
                        >
                            <span x-show="submission !== 'review'" aria-hidden="true"><x-icon name="save" class="h-4 w-4" /></span>
                            Guardar revisión
                        </x-button>
                    </div>
                </x-card>
            @endif
        </form>

        @if ($canSubmitRecoteje)
            @foreach ($qualitativeAnswers as $qualAnswer)
                @continue(! $qualAnswer->canBeReviewedQualitatively())
                <form
                    id="ai-eval-form-{{ $qualAnswer->id }}"
                    action="{{ route('exams.revision.evaluate-ai', [$exam, $attempt, $qualAnswer, ...$reviewContext]) }}"
                    method="POST"
                    class="hidden"
                    @submit="submitAi($event, {{ $qualAnswer->id }})"
                >
                    @csrf
                    <input type="hidden" name="is_reevaluation" value="0">
                    <input type="hidden" name="dismiss_warning" value="0">
                </form>
            @endforeach

            @if ($qualitativeAnswers->contains(static fn ($answer): bool => $answer->canBeReviewedQualitatively()))
                <x-modal name="review-reevaluate-ai" title="¿Re-evaluar respuesta con IA?" max-width="md" :focus-close="false">
                    <p>Se descontará 1 crédito si la nueva evaluación produce una sugerencia utilizable. El respaldo local no tiene costo.</p>
                    <label class="mt-3 inline-flex min-h-8 items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" x-model="dismissWarning" class="h-4 w-4 rounded border-slate-500 text-acode-primary focus:ring-acode-tech">
                        No mostrar de nuevo
                    </label>
                    <x-slot:footer>
                        <x-button type="button" variant="neutral" @click="$dispatch('close-modal', 'review-reevaluate-ai')" autofocus>
                            Cancelar
                        </x-button>
                        <x-button type="button" variant="primary" @click="confirmReevaluation()">
                            Re-evaluar con IA
                        </x-button>
                    </x-slot:footer>
                </x-modal>

                <x-modal name="review-discard" title="¿Descartar los cambios?" max-width="md" :focus-close="false">
                    <p>Tienes cambios sin guardar. Si sales de esta pantalla, se perderán.</p>
                    <x-slot:footer>
                        <x-button type="button" variant="neutral" @click="$dispatch('close-modal', 'review-discard')" autofocus>
                            Continuar editando
                        </x-button>
                        <x-button type="button" variant="danger" @click="discardChanges()">
                            Descartar cambios
                        </x-button>
                    </x-slot:footer>
                </x-modal>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/review-attempt.js') }}"></script>
@endpush
