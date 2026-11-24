{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/configuration.blade.php (MPW-V3 §4.1.2, §7, §8, ADR-043, ADR-056) --}}
@extends('layouts.app')

@section('title', 'Configuración: ' . $exam->title)

@section('content')
    @php
        $examQuestions = $exam->sections->sortBy('sort_order')->flatMap(fn ($section) => $section->examQuestions->sortBy('sort_order'))->values();
        $initialPoints = round((float) ($totalPoints ?? $examQuestions->sum('points_weight')), 2);
        $totalTime = (int) $examQuestions->sum('time_limit_seconds');
        $isPublished = (bool) $exam->is_published;
        $hasAttempts = (bool) ($hasAttempts ?? (((int) ($exam->student_attempts_count ?? 0)) > 0));
        $isReadOnly = $isPublished && ($exam->hasStartedWindow() || $exam->isWindowClosed());
        $lockScale = $hasAttempts || $isReadOnly;

        $assignedGroupIds = $exam->groups->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $selectedGroupIds = array_values(array_map('intval', (array) old('group_ids', old('group_selection_submitted') ? [] : $assignedGroupIds)));
        $openGroupIds = $groups->reject(fn ($group) => $group->is_closed)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $groupError = $errors->first('group_ids') ?: $errors->first('group_ids.*');

        $initialStartsAt = old('starts_at', $exam->starts_at?->format('Y-m-d\TH:i') ?? '');
        $initialEndsAt = old('ends_at', $exam->ends_at?->format('Y-m-d\TH:i') ?? '');
        $initialNavigationMode = old('navigation_mode', $exam->free_duration_minutes ? 'free' : 'sequential');
        $initialFreeDuration = old('free_duration_minutes', $exam->free_duration_minutes);
        $timezoneLabel = \App\Models\Exam::timezoneLabel();
        $initialScale = old('grading_scale', $exam->grading_scale);
        $initialVisibility = old('result_visibility_mode', $exam->result_visibility_mode);
        $initialRandomize = (string) old('randomize_question_order', $exam->randomize_question_order ? '1' : '0');

        $visibilityModes = [
            'final_score' => [
                'title' => 'Calificación final inmediata',
                'desc' => 'El alumno visualiza su nota final al entregar (y tras la revisión docente si incluye preguntas abiertas o de código).',
            ],
            'estimated_score' => [
                'title' => 'Puntaje preliminar estimado',
                'desc' => 'Muestra una estimación de la calificación sin mostrar la retroalimentación de IA.',
            ],
            'objective_only_pending' => [
                'title' => 'Solo puntaje objetivo (pendiente docente)',
                'desc' => 'Muestra únicamente puntos objetivos y marca los reactivos abiertos como pendientes de evaluación docente.',
            ],
            'hidden' => [
                'title' => 'Resultados ocultos hasta liberación',
                'desc' => 'Oculta calificación y desglose hasta que liberes los resultados manualmente.',
            ],
        ];
    @endphp

    <div
        class="space-y-6"
        x-data="{
            gradingScale: @js($initialScale),
            visibilityMode: @js($initialVisibility),
            randomizeOrder: @js($initialRandomize),
            startsAt: @js($initialStartsAt),
            endsAt: @js($initialEndsAt),
            navigationMode: @js($initialNavigationMode),
            freeDuration: @js($initialFreeDuration),
            navigationLocked: @js($isReadOnly || $hasAttempts),
            selectedGroups: @js($selectedGroupIds),
            openGroupIds: @js($openGroupIds),
            weightCents: @js((int) round($initialPoints * 100)),
            totalTimeSeconds: @js($totalTime),
            saving: false,
            distributionStatus: '',

            init() {
                this.$nextTick(() => this.recalculate());
            },

            startSaving(event) {
                if (this.saving) {
                    event.preventDefault();
                    return;
                }
                this.saving = true;
            },

            get pointInputs() {
                return [...this.$refs.form.querySelectorAll('[data-points-input]')];
            },

            recalculate() {
                this.distributionStatus = '';
                this.weightCents = this.pointInputs.reduce((sum, input) => {
                    return sum + Math.round((input.valueAsNumber || 0) * 100);
                }, 0);
                const timeInputs = [...this.$refs.form.querySelectorAll('[data-time-input]')];
                this.totalTimeSeconds = timeInputs.reduce((sum, input) => sum + Math.max(0, Math.floor(input.valueAsNumber || 0)), 0);
            },

            requestDistribution() {
                const inputs = this.pointInputs;
                if (this.isWeightValid || inputs.some(input => input.value !== input.defaultValue)) {
                    this.$dispatch('open-modal', 'redistribute-exam-points');
                    return;
                }
                this.distributeEqually();
            },

            distributeEqually() {
                const inputs = this.pointInputs;
                const count = inputs.length;
                if (!count) return;
                const baseCents = Math.floor(10000 / count);
                const remainderCents = 10000 % count;
                inputs.forEach((input, index) => {
                    const cents = baseCents + (index < remainderCents ? 1 : 0);
                    input.value = (cents / 100).toFixed(2);
                });
                this.recalculate();
                this.distributionStatus = 'Se distribuyeron los 100.00 puntos. Los cambios se aplicarán al guardar.';
            },

            get isWeightValid() {
                return Math.abs(this.weightCents - 10000) <= 1;
            },

            get weightStatus() {
                if (this.weightCents === 10000) return 'Suma exacta completada';
                if (this.isWeightValid) return 'Suma válida dentro de la tolerancia';
                return this.weightCents > 10000 ? 'La suma supera los 100.00 puntos' : 'Faltan puntos para sumar 100.00';
            },

            get selectedOpenGroups() {
                return this.selectedGroups.filter(id => this.openGroupIds.includes(id)).length;
            },

            formatDateTime(value) {
                if (!value) return 'Sin fecha';
                const [date, time] = value.split('T');
                return `${date.split('-').reverse().join('/')} ${time?.slice(0, 5) ?? ''}`.trim();
            },

            get formattedTime() {
                if (this.navigationMode === 'free') {
                    return Number(this.freeDuration) > 0 ? `${this.freeDuration} min` : 'Sin duración asignada';
                }
                const mins = Math.floor(this.totalTimeSeconds / 60);
                const secs = this.totalTimeSeconds % 60;
                return `${mins} min ${secs} s`;
            }
        }"
        @pageshow.window="saving = false"
    >
        {{-- Encabezado --}}
        <x-page-header
            title="Pesos y reglas de aplicación"
            subtitle="Distribuye los 100.00 puntos entre los reactivos, asigna grupos y define la visibilidad de resultados."
            institutional
            data-cancel-form="exam-configuration-form"
            :breadcrumbs="[
                ['label' => 'Exámenes', 'href' => route('exams.index')],
                ['label' => $exam->title, 'href' => route('exams.assemble', $exam)],
                ['label' => 'Pesos y reglas'],
            ]"
        >
            <x-slot:actions>
                <x-button :href="route('exams.assemble', $exam)" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
            </x-slot:actions>
        </x-page-header>

        @include('exams.partials._stepper', ['currentStep' => 3, 'exam' => $exam, 'showPoints' => false, 'cancelForm' => 'exam-configuration-form'])

        @if ($isReadOnly)
            <div class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 px-4 py-3 text-sm text-acode-primary flex items-start gap-2.5">
                <x-icon name="lock" class="w-5 h-5 shrink-0 text-acode-warning-dark mt-0.5" />
                <div>
                    <p class="font-semibold">Configuración en modo solo lectura</p>
                    <p class="text-xs text-slate-700 mt-0.5">
                        Este examen ya está publicado y su ventana inició o concluyó. No es posible alterar ponderaciones ni reglas activas.
                    </p>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger-dark focus:outline-acode-tech"
                 role="alert" tabindex="-1" x-init="$nextTick(() => $el.focus())">
                <p class="font-semibold mb-1">Revisa los siguientes puntos:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="exam-configuration-form" method="POST" action="{{ route('exams.configuration.update', $exam) }}"
              class="space-y-6" x-ref="form" @submit="startSaving($event)">
            @csrf
            @method('PUT')
            <input type="hidden" name="require_webcam" value="0">
            @unless ($isReadOnly)
                <input type="hidden" name="group_selection_submitted" value="1">
            @endunless

            @if ($lockScale)
                <input type="hidden" name="grading_scale" value="{{ $exam->grading_scale }}">
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {{-- Columna Principal --}}
                <div class="lg:col-span-8 space-y-6">
                    {{-- 1. Matriz de Ponderación Base 100.00 y Tiempos --}}
                    <x-card id="weights" tabindex="-1" padding="p-0" class="scroll-mt-24 overflow-hidden">
                        <x-section-header
                            title="Matriz de ponderaciones"
                            subtitle="Asigna puntos y tiempo por reactivo. La meta es 100.00 puntos, con tolerancia de 0.01."
                            institutional
                            class="px-4 py-5 border-b border-slate-100 sm:px-6"
                        >
                            @if (! $isReadOnly && $examQuestions->isNotEmpty())
                                <x-slot:actions>
                                    <x-button type="button" variant="secondary" size="sm" @click="requestDistribution()">
                                        Distribuir 100 pts equitativamente
                                    </x-button>
                                </x-slot:actions>
                            @endif
                        </x-section-header>

                        <div class="px-4 pt-4 sm:px-6">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-acode-primary">
                                    <span class="text-sm font-semibold">Suma total asignada</span>
                                    <span class="font-mono text-sm font-bold" x-text="`${(weightCents / 100).toFixed(2)} / 100.00 pts`">
                                        {{ number_format($initialPoints, 2, '.', '') }} / 100.00 pts
                                    </span>
                                </div>
                                <p id="weights-status" class="mt-1 text-xs font-medium text-slate-700" role="status" x-text="weightStatus"></p>
                                <p class="text-xs text-slate-700" role="status" x-text="distributionStatus"></p>
                                @error('questions')
                                    <p class="mt-1 text-xs font-medium text-acode-danger-dark">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="p-4 sm:p-6">
                            <x-table scroll-label="Matriz de ponderaciones y tiempos">
                                <x-slot:header>
                                    <x-table.th align="center">Reactivo</x-table.th>
                                    <x-table.th align="center" class="w-36">Puntos</x-table.th>
                                    <x-table.th align="center" class="w-36">Tiempo (seg.)</x-table.th>
                                </x-slot:header>

                                @forelse ($exam->sections->sortBy('sort_order') as $section)
                                    <tr class="!bg-acode-surface hover:!bg-acode-surface">
                                        <th colspan="3" class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-acode-primary">
                                            {{ $section->title }}
                                        </th>
                                    </tr>
                                    @forelse ($section->examQuestions->sortBy('sort_order') as $examQuestion)
                                        @php
                                            $questionTitle = $examQuestion->question?->title ?? 'Reactivo #' . $examQuestion->question_id;
                                            $pointsName = "questions[{$examQuestion->id}][points_weight]";
                                            $timeName = "questions[{$examQuestion->id}][time_limit_seconds]";
                                            $pointsId = "question-points-{$examQuestion->id}";
                                            $timeId = "question-time-{$examQuestion->id}";
                                            $pointsKey = "questions.{$examQuestion->id}.points_weight";
                                            $timeKey = "questions.{$examQuestion->id}.time_limit_seconds";
                                        @endphp
                                        <tr>
                                            <th scope="row" class="px-4 py-3 text-left font-normal">
                                                <span class="block break-words text-sm font-semibold text-acode-primary">
                                                    {{ $loop->iteration }}. {{ $questionTitle }}
                                                </span>
                                                <span class="mt-0.5 block text-xs text-slate-600 line-clamp-1">
                                                    {{ \Illuminate\Support\Str::limit($examQuestion->question?->stem ?? '', 90) }}
                                                </span>
                                            </th>
                                            <x-table.td>
                                                <x-form.group :name="$pointsKey" :field-id="$pointsId" accessible>
                                                    <x-form.input
                                                        type="number"
                                                        :name="$pointsName"
                                                        :id="$pointsId"
                                                        min="0.01"
                                                        max="100"
                                                        step="0.01"
                                                        required
                                                        accessible
                                                        data-points-input
                                                        @input="recalculate()"
                                                        :disabled="$isReadOnly"
                                                        :has-error="$errors->has($pointsKey)"
                                                        :value="old($pointsKey, number_format((float) $examQuestion->points_weight, 2, '.', ''))"
                                                        :aria-label="'Puntos de ' . $questionTitle"
                                                        :aria-describedby="$errors->has($pointsKey) ? $pointsId . '-error' : null"
                                                        class="min-w-24 font-mono"
                                                    />
                                                </x-form.group>
                                            </x-table.td>
                                            <x-table.td>
                                                <x-form.group :name="$timeKey" :field-id="$timeId" accessible>
                                                    <x-form.input
                                                        type="number"
                                                        :name="$timeName"
                                                        :id="$timeId"
                                                        min="1"
                                                        step="1"
                                                        required
                                                        accessible
                                                        data-time-input
                                                        @input="recalculate()"
                                                        :disabled="$isReadOnly"
                                                        :has-error="$errors->has($timeKey)"
                                                        :value="old($timeKey, (int) $examQuestion->time_limit_seconds)"
                                                        :aria-label="'Tiempo límite en segundos de ' . $questionTitle"
                                                        :aria-describedby="$errors->has($timeKey) ? $timeId . '-error' : null"
                                                        class="min-w-24 scroll-mt-24 font-mono"
                                                    />
                                                </x-form.group>
                                            </x-table.td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <x-table.td colspan="3" class="text-xs text-slate-500">
                                                Este apartado no tiene reactivos.
                                            </x-table.td>
                                        </tr>
                                    @endforelse
                                @empty
                                    <tr>
                                        <x-table.td colspan="3" class="py-6">
                                            <x-empty-state
                                                icon="document"
                                                title="No hay reactivos asignados"
                                                message="Regresa a la Etapa 2 para sortear o seleccionar reactivos del banco."
                                            >
                                                <x-button :href="route('exams.assemble', $exam)" variant="primary" size="sm">
                                                    Volver a Armar reactivos
                                                </x-button>
                                            </x-empty-state>
                                        </x-table.td>
                                    </tr>
                                @endforelse
                            </x-table>
                        </div>
                    </x-card>
                    @if (! $isReadOnly && $examQuestions->isNotEmpty())
                        <x-modal name="redistribute-exam-points" title="¿Distribuir 100 puntos?" max-width="md">
                            Esto reemplazará los puntos asignados a todos los reactivos. Los valores se aplicarán al guardar la configuración.
                            <x-slot:footer>
                                <x-button type="button" variant="neutral" @click="$dispatch('close-modal', 'redistribute-exam-points')">
                                    Cancelar
                                </x-button>
                                <x-button type="button" variant="primary" @click="distributeEqually(); $dispatch('close-modal', 'redistribute-exam-points')">
                                    Distribuir 100 pts equitativamente
                                </x-button>
                            </x-slot:footer>
                        </x-modal>
                    @endif

                    {{-- 2. Datos generales y Ventana horaria --}}
                    <x-card id="application-window" tabindex="-1" class="scroll-mt-24" padding="p-4 sm:p-6">
                        <x-section-header title="Título, instrucciones y ventana de aplicación" institutional class="mb-4 border-b border-slate-100 pb-3" />
                        <div class="space-y-4">
                            <x-form.group name="title" label="Título del examen" :required="true" accessible>
                                <x-form.input name="title" value="{{ old('title', $exam->title) }}" maxlength="150" required accessible :disabled="$isReadOnly"
                                              :aria-describedby="$errors->has('title') ? 'title-error' : null" />
                            </x-form.group>

                            <x-form.group name="description" label="Descripción e instrucciones" accessible>
                                <x-form.textarea name="description" rows="3" accessible :disabled="$isReadOnly"
                                                 :aria-describedby="$errors->has('description') ? 'description-error' : null">{{ old('description', $exam->description) }}</x-form.textarea>
                            </x-form.group>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <x-form.group name="starts_at" label="Fecha y hora de inicio" :required="true" :help-text="$timezoneLabel" accessible>
                                    <x-form.input type="datetime-local" name="starts_at" x-model="startsAt" :value="$initialStartsAt" :disabled="$isReadOnly" required accessible
                                                  :aria-describedby="$errors->has('starts_at') ? 'starts_at-help starts_at-error' : 'starts_at-help'" />
                                </x-form.group>

                                <x-form.group name="ends_at" label="Fecha y hora de cierre" :required="true" :help-text="$timezoneLabel" accessible>
                                    <x-form.input type="datetime-local" name="ends_at" x-model="endsAt" :value="$initialEndsAt" :disabled="$isReadOnly" required accessible
                                                  :aria-describedby="$errors->has('ends_at') ? 'ends_at-help ends_at-error' : 'ends_at-help'" />
                                </x-form.group>
                            </div>
                        </div>
                    </x-card>

                    {{-- 3. Grupos asignados --}}
                    <x-card id="assigned-groups" tabindex="-1" class="scroll-mt-24" padding="p-4 sm:p-6">
                        <x-section-header title="Grupos escolares asignados" subtitle="Para publicar, asigna al menos un grupo abierto."
                                          institutional class="mb-4 border-b border-slate-100 pb-3" />
                        @if ($groups->isEmpty())
                            <x-empty-state
                                icon="userGroup"
                                title="Sin grupos disponibles"
                                message="Crea un grupo escolar abierto para poder asignarlo antes de publicar."
                            >
                                @unless ($isReadOnly)
                                    <x-button :href="route('groups.create')" variant="secondary" size="sm" data-cancel-form="exam-configuration-form">
                                        Crear grupo
                                    </x-button>
                                @endunless
                            </x-empty-state>
                        @else
                            <div role="group" aria-label="Grupos escolares asignados" class="grid grid-cols-1 sm:grid-cols-2 gap-3"
                                 @if ($groupError) aria-describedby="assigned-groups-error" @endif>
                                @foreach ($groups as $group)
                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-3.5 transition select-none {{ $isReadOnly ? 'cursor-not-allowed' : 'cursor-pointer' }}"
                                        :class="selectedGroups.includes({{ $group->id }}) ? 'border-acode-tech bg-acode-surface/80' : 'border-slate-200 bg-white hover:border-slate-300'"
                                    >
                                        <input
                                            type="checkbox"
                                            name="group_ids[]"
                                            value="{{ $group->id }}"
                                            x-model.number="selectedGroups"
                                            @checked(in_array((int) $group->id, $selectedGroupIds, true))
                                            @disabled($isReadOnly)
                                            class="mt-1 h-4 w-4 shrink-0 rounded border-slate-500 accent-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech"
                                        >
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="break-words text-sm font-bold text-acode-primary">{{ $group->name }}</span>
                                                <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                                                    {{ $group->is_closed ? 'Cerrado' : 'Abierto' }}
                                                </x-badge>
                                            </div>
                                            <p class="mt-0.5 break-words text-xs text-slate-600">
                                                {{ $group->subject ?: 'Sin asignatura' }} · {{ (int) ($group->students_count ?? 0) }} alumnos
                                            </p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        @if ($groupError)
                            <p id="assigned-groups-error" class="mt-2 text-xs font-medium text-acode-danger-dark">{{ $groupError }}</p>
                        @endif
                    </x-card>

                    @if ($allowsFreeNavigation ?? false)
                        <x-card id="application-mode" tabindex="-1" class="scroll-mt-24" padding="p-4 sm:p-6">
                            <x-section-header title="Modalidad de aplicación" institutional class="mb-4 border-b border-slate-100 pb-3" />
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-form.group name="navigation_mode" label="Modalidad de aplicación" accessible>
                                    <x-form.select name="navigation_mode" x-model="navigationMode" :disabled="$isReadOnly || $hasAttempts" accessible
                                                   :aria-describedby="$errors->has('navigation_mode') ? 'navigation_mode-error' : null">
                                        <option value="sequential" @selected($initialNavigationMode === 'sequential')>Secuencial</option>
                                        <option value="free" @selected($initialNavigationMode === 'free')>Libre</option>
                                    </x-form.select>
                                </x-form.group>
                                <div x-show="navigationMode === 'free'" x-cloak>
                                    <x-form.group name="free_duration_minutes" label="Tiempo total en modalidad libre (minutos)" :required="true"
                                                  help-text="Es el tiempo de todo el intento en modalidad libre." accessible>
                                        <x-form.input type="number" name="free_duration_minutes" min="1" max="2147483647" step="1" accessible
                                                      x-model="freeDuration" :value="$initialFreeDuration"
                                                      :disabled="$isReadOnly || $hasAttempts"
                                                      ::disabled="navigationMode !== 'free' || navigationLocked"
                                                      ::required="navigationMode === 'free'"
                                                      :aria-describedby="$errors->has('free_duration_minutes') ? 'free_duration_minutes-help free_duration_minutes-error' : 'free_duration_minutes-help'" />
                                    </x-form.group>
                                </div>
                            </div>
                        </x-card>
                    @endif

                    {{-- 4. Escala, Visibilidad y Seguridad --}}
                    <x-card padding="p-4 sm:p-6">
                        <x-section-header title="Escala, visibilidad y seguridad" institutional class="mb-4 border-b border-slate-100 pb-3" />
                        <div class="space-y-6">
                            <fieldset class="min-w-0" @if ($errors->has('grading_scale')) aria-describedby="grading-scale-error" @endif>
                                <legend class="mb-2 text-[13px] font-medium text-slate-800">Escala de calificación</legend>
                                @if ($hasAttempts)
                                    <x-badge variant="warning" class="mb-2">Bloqueada: ya existen intentos</x-badge>
                                @endif
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-4 transition {{ $lockScale ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer' }}"
                                        :class="gradingScale === '0-10' ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200'"
                                    >
                                        <input type="radio" name="grading_scale" value="0-10" x-model="gradingScale" @disabled($lockScale)
                                               @checked($initialScale === '0-10') class="mt-1 accent-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                                        <div>
                                            <span class="block text-sm font-bold text-acode-primary">Escala 0 a 10</span>
                                            <span class="mt-0.5 block text-xs text-slate-600">
                                                La suma de 100.00 pts se divide automáticamente entre 10 al calcular la calificación final (ej. 85.00 → 8.50).
                                            </span>
                                        </div>
                                    </label>

                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-4 transition {{ $lockScale ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer' }}"
                                        :class="gradingScale === '0-100' ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200'"
                                    >
                                        <input type="radio" name="grading_scale" value="0-100" x-model="gradingScale" @disabled($lockScale)
                                               @checked($initialScale === '0-100') class="mt-1 accent-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                                        <div>
                                            <span class="block text-sm font-bold text-acode-primary">Escala 0 a 100</span>
                                            <span class="mt-0.5 block text-xs text-slate-600">
                                                La puntuación obtenida sobre 100.00 puntos se conserva sin conversión.
                                            </span>
                                        </div>
                                    </label>
                                </div>
                                @error('grading_scale')
                                    <p id="grading-scale-error" class="mt-1.5 text-xs font-medium text-acode-danger-dark">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            <fieldset class="min-w-0" @if ($errors->has('result_visibility_mode')) aria-describedby="result-visibility-error" @endif>
                                <legend class="mb-2 text-[13px] font-medium text-slate-800">Visibilidad de resultados</legend>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach ($visibilityModes as $modeKey => $modeInfo)
                                        <label
                                            class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition"
                                            :class="visibilityMode === @js($modeKey) ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200 hover:border-slate-300'"
                                        >
                                            <input type="radio" name="result_visibility_mode" value="{{ $modeKey }}" x-model="visibilityMode" @disabled($isReadOnly)
                                                   @checked($initialVisibility === $modeKey) class="mt-1 accent-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                                            <div>
                                                <span class="block text-sm font-bold text-acode-primary">{{ $modeInfo['title'] }}</span>
                                                <span class="mt-0.5 block text-xs leading-relaxed text-slate-600">{{ $modeInfo['desc'] }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                @error('result_visibility_mode')
                                    <p id="result-visibility-error" class="mt-1.5 text-xs font-medium text-acode-danger-dark">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                                <fieldset class="min-w-0" @if ($errors->has('randomize_question_order')) aria-describedby="question-order-error" @endif>
                                    <legend class="mb-2 text-[13px] font-medium text-slate-800">Orden de preguntas</legend>
                                    <div class="space-y-2">
                                        <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 p-3 cursor-pointer">
                                            <input type="radio" name="randomize_question_order" value="1" x-model="randomizeOrder" @disabled($isReadOnly)
                                                   @checked($initialRandomize === '1') class="mt-0.5 accent-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                                            <span class="text-xs">
                                                <strong class="block text-acode-primary">Aleatorio</strong>
                                                <span class="text-slate-600">Cambia el orden de las preguntas en cada intento.</span>
                                            </span>
                                        </label>
                                        <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 p-3 cursor-pointer">
                                            <input type="radio" name="randomize_question_order" value="0" x-model="randomizeOrder" @disabled($isReadOnly)
                                                   @checked($initialRandomize === '0') class="mt-0.5 accent-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                                            <span class="text-xs">
                                                <strong class="block text-acode-primary">Fijo por apartados</strong>
                                                <span class="text-slate-600">Conserva el orden de apartados y reactivos.</span>
                                            </span>
                                        </label>
                                    </div>
                                    @error('randomize_question_order')
                                        <p id="question-order-error" class="mt-1.5 text-xs font-medium text-acode-danger-dark">{{ $message }}</p>
                                    @enderror
                                </fieldset>

                                <x-form.group name="max_allowed_incidents" label="Incidencias anti-trampa permitidas" :required="true" accessible
                                              help-text="Al superar el límite por cambio de pestaña o aplicación, el intento se cancela. La supervisión funciona sin cámara ni biometría.">
                                    <x-form.input
                                        numeric="integer"
                                        name="max_allowed_incidents"
                                        min="0"
                                        :max="\App\Models\Exam::MAX_ALLOWED_INCIDENTS"
                                        required
                                        accessible
                                        :disabled="$isReadOnly"
                                        value="{{ old('max_allowed_incidents', $exam->max_allowed_incidents) }}"
                                        :aria-describedby="$errors->has('max_allowed_incidents') ? 'max_allowed_incidents-help max_allowed_incidents-error' : 'max_allowed_incidents-help'"
                                    />
                                </x-form.group>
                            </div>

                            <div class="rounded-xl border border-acode-tech/25 bg-acode-surface/80 p-4 flex items-start gap-3">
                                <x-icon name="shield" class="w-5 h-5 text-acode-tech shrink-0 mt-0.5" />
                                <div class="text-xs text-slate-600 leading-relaxed">
                                    <p class="font-bold text-acode-primary">Supervisión pasiva</p>
                                    <p class="mt-0.5">El sistema registra los cambios de pestaña o aplicación mientras se presenta el examen.</p>
                                </div>
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- Columna Derecha Sticky --}}
                <aside class="lg:col-span-4 lg:sticky lg:top-24">
                    <x-card padding="p-4">
                        <x-section-header title="Resumen de configuración" institutional class="mb-4" />
                        <x-summary-list>
                            <x-summary-item label="Grupos abiertos" compact>
                                <x-slot:valueSlot><span x-text="`${selectedOpenGroups} de ${selectedGroups.length} seleccionados`"></span></x-slot:valueSlot>
                            </x-summary-item>
                            <x-summary-item compact>
                                <x-slot:labelSlot><span x-text="navigationMode === 'free' ? 'Tiempo total en modalidad libre' : 'Suma de tiempos por reactivo'"></span></x-slot:labelSlot>
                                <x-slot:valueSlot><span x-text="formattedTime"></span></x-slot:valueSlot>
                            </x-summary-item>
                            <x-summary-item label="Inicio" compact>
                                <x-slot:valueSlot><span x-text="formatDateTime(startsAt)"></span></x-slot:valueSlot>
                            </x-summary-item>
                            <x-summary-item label="Cierre" compact>
                                <x-slot:valueSlot><span x-text="formatDateTime(endsAt)"></span></x-slot:valueSlot>
                            </x-summary-item>
                        </x-summary-list>
                        <p class="mt-3 text-xs text-slate-600">{{ $timezoneLabel }}</p>

                        <x-slot:footer>
                            <div class="w-full space-y-3">
                                @if ($isReadOnly)
                                    <x-button :href="route('exams.review', $exam)" variant="primary" size="md" class="w-full">
                                        Abrir resumen
                                    </x-button>
                                @else
                                    <x-button
                                        type="submit"
                                        id="save-configuration"
                                        variant="primary"
                                        size="md"
                                        class="w-full"
                                        loading-when="saving"
                                        ::disabled="!isWeightValid || saving"
                                        aria-describedby="weights-status"
                                    >
                                        <x-icon name="save" class="w-4 h-4 shrink-0" x-show="!saving" />
                                        <span x-text="saving ? 'Guardando…' : 'Guardar y continuar a Revisión'">Guardar y continuar a Revisión</span>
                                    </x-button>
                                    <p class="sr-only" role="status" x-text="saving ? 'Guardando la configuración…' : ''"></p>
                                    <div class="flex justify-center">
                                        <x-unsaved-changes form-id="exam-configuration-form" :cancel-url="route('exams.assemble', $exam)" />
                                    </div>
                                @endif
                            </div>
                        </x-slot:footer>
                    </x-card>
                </aside>
            </div>
        </form>
    </div>
@endsection
