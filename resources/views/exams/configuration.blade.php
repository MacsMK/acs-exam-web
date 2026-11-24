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
        $selectedGroupIds = array_map('intval', (array) old('group_ids', $assignedGroupIds));

        $initialStartsAt = old('starts_at', $exam->starts_at?->format('Y-m-d\TH:i') ?? '');
        $initialEndsAt = old('ends_at', $exam->ends_at?->format('Y-m-d\TH:i') ?? '');
        $initialScale = old('grading_scale', $exam->grading_scale);
        $initialVisibility = old('result_visibility_mode', $exam->result_visibility_mode);
        $initialRandomize = (string) old('randomize_question_order', $exam->randomize_question_order ? '1' : '0');

        $visibilityModes = [
            'final_score' => [
                'title' => 'Calificación final inmediata',
                'desc' => 'El alumno visualiza su nota final al entregar (y tras recoteje si incluye preguntas abiertas o de código).',
            ],
            'estimated_score' => [
                'title' => 'Puntaje preliminar estimado',
                'desc' => 'Muestra una estimación numérica preliminar sin exponer retroalimentación de IA (ADR-050).',
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
            selectedGroups: @js($selectedGroupIds),
            totalWeight: @js($initialPoints),
            totalTimeSeconds: @js($totalTime),
            hasQuestions: @js($examQuestions->isNotEmpty()),

            init() {
                this.recalculate();
            },

            toggleGroup(groupId) {
                const id = Number(groupId);
                if (this.selectedGroups.includes(id)) {
                    this.selectedGroups = this.selectedGroups.filter((g) => g !== id);
                } else {
                    this.selectedGroups.push(id);
                }
            },

            recalculate() {
                const pointInputs = [...document.querySelectorAll('[data-points-input]')];
                const timeInputs = [...document.querySelectorAll('[data-time-input]')];

                const totalCents = pointInputs.reduce((sum, input) => {
                    return sum + Math.round((Number(input.value) || 0) * 100);
                }, 0);

                this.totalWeight = Number((totalCents / 100).toFixed(2));
                this.totalTimeSeconds = timeInputs.reduce((sum, input) => {
                    return sum + Math.max(0, parseInt(input.value || '0', 10));
                }, 0);

                const stepperBadge = document.getElementById('stepper-points-badge');
                if (stepperBadge) {
                    const span = stepperBadge.querySelector('span:last-child');
                    if (span) {
                        span.textContent = `${this.totalWeight.toFixed(2)} / 100.00 pts`;
                    }
                }
            },

            distributeEqually() {
                const pointInputs = [...document.querySelectorAll('[data-points-input]')];
                const count = pointInputs.length;
                if (count === 0) return;

                const baseCents = Math.floor(10000 / count);
                const remainderCents = 10000 - (baseCents * count);

                pointInputs.forEach((input, idx) => {
                    const cents = idx === count - 1 ? baseCents + remainderCents : baseCents;
                    input.value = (cents / 100).toFixed(2);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                });

                this.recalculate();
            },

            get isWeightExact() {
                return this.hasQuestions && Math.abs(this.totalWeight - 100.0) <= 0.01;
            },

            get progressPercent() {
                return Math.min(100, Math.max(0, this.totalWeight));
            },

            get progressWidthClass() {
                const steps = this.progressPercent <= 0 ? 0 : Math.max(1, Math.round(this.progressPercent * 12 / 100));
                if (steps === 0) return 'w-0';
                if (steps >= 12) return 'w-full';
                return ['w-1/12', 'w-2/12', 'w-3/12', 'w-4/12', 'w-5/12', 'w-6/12', 'w-7/12', 'w-8/12', 'w-9/12', 'w-10/12', 'w-11/12'][steps - 1];
            },

            get progressColorClass() {
                if (this.totalWeight > 100.01) return 'bg-acode-danger';
                if (this.isWeightExact) return 'bg-acode-accent';
                return 'bg-acode-warning';
            },

            get formattedTime() {
                const mins = Math.floor(this.totalTimeSeconds / 60);
                const secs = this.totalTimeSeconds % 60;
                return `${mins} min ${secs} s`;
            }
        }"
    >
        {{-- Encabezado --}}
        <x-page-header
            title="Pesos y reglas de aplicación"
            subtitle="Distribuye los 100.00 puntos entre los reactivos, asigna grupos y define la visibilidad de resultados."
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

        @include('exams.partials._stepper', ['currentStep' => 3, 'exam' => $exam, 'totalPoints' => $initialPoints])

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
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger" role="alert">
                <p class="font-semibold mb-1">Revisa los siguientes puntos:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="exam-configuration-form" method="POST" action="{{ route('exams.configuration.update', $exam) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="require_webcam" value="0">

            @if ($lockScale)
                <input type="hidden" name="grading_scale" value="{{ $exam->grading_scale }}">
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {{-- Columna Principal --}}
                <div class="lg:col-span-8 space-y-6">
                    {{-- 1. Matriz de Ponderación Base 100.00 y Tiempos --}}
                    <x-card padding="p-0" class="overflow-hidden">
                        <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-bold text-acode-primary">1. Matriz de ponderaciones (base 100.00 pts)</h2>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Asigna el valor decimal (<code class="font-mono">0.01</code>) y el tiempo límite por reactivo. La suma total debe ser exactamente <strong>100.00</strong> puntos.
                                </p>
                            </div>

                            @if (! $isReadOnly && $examQuestions->isNotEmpty())
                                <x-button type="button" variant="secondary" size="sm" @click="distributeEqually()">
                                    Distribuir 100 pts equitativamente
                                </x-button>
                            @endif
                        </div>

                        <div class="px-6 pt-4">
                            {{-- Barra de progreso tricolor en tiempo real --}}
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-semibold text-slate-600">Suma total asignada</span>
                                    <span
                                        class="font-mono text-sm font-bold"
                                        :class="isWeightExact ? 'text-acode-accent' : (totalWeight > 100.01 ? 'text-acode-danger' : 'text-acode-warning-dark')"
                                        x-text="`${totalWeight.toFixed(2)} / 100.00 pts`"
                                    ></span>
                                </div>
                                {{-- Ancho en 12 pasos (w-N/12, Tailwind 3.4), sin style inline (MPW-V3 §7.4.2) --}}
                                <div class="h-2.5 w-full rounded-full bg-slate-200 overflow-hidden" role="progressbar" aria-label="Suma de ponderaciones" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.round(progressPercent)">
                                    <div
                                        class="h-full rounded-full transition-all duration-200"
                                        :class="[progressColorClass, progressWidthClass]"
                                    ></div>
                                </div>
                                @error('questions')
                                    <p class="text-xs font-medium text-acode-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="p-6">
                            <x-table>
                                <x-slot:header>
                                    <x-table.th>Reactivo</x-table.th>
                                    <x-table.th class="w-36">Puntos (0.01)</x-table.th>
                                    <x-table.th class="w-36">Tiempo (seg.)</x-table.th>
                                </x-slot:header>

                                @forelse ($exam->sections->sortBy('sort_order') as $section)
                                    <tr class="!bg-acode-surface hover:!bg-acode-surface">
                                        <th colspan="3" class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-acode-primary">
                                            {{ $section->title }}
                                        </th>
                                    </tr>
                                    @forelse ($section->examQuestions->sortBy('sort_order') as $examQuestion)
                                        <tr>
                                            <x-table.td>
                                                <p class="font-semibold text-acode-primary text-sm">
                                                    {{ $loop->iteration }}. {{ $examQuestion->question?->title ?? 'Reactivo #' . $examQuestion->question_id }}
                                                </p>
                                                <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">
                                                    {{ \Illuminate\Support\Str::limit($examQuestion->question?->stem ?? '', 90) }}
                                                </p>
                                            </x-table.td>
                                            <x-table.td>
                                                <input
                                                    type="number"
                                                    name="questions[{{ $examQuestion->id }}][points_weight]"
                                                    min="0.01"
                                                    max="100"
                                                    step="0.01"
                                                    required
                                                    data-points-input
                                                    @input="recalculate()"
                                                    @disabled($isReadOnly)
                                                    value="{{ old('questions.' . $examQuestion->id . '.points_weight', number_format((float) $examQuestion->points_weight, 2, '.', '')) }}"
                                                    aria-label="Puntos del reactivo {{ $examQuestion->id }}"
                                                    class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 font-mono text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech disabled:bg-slate-100"
                                                >
                                            </x-table.td>
                                            <x-table.td>
                                                <input
                                                    type="number"
                                                    name="questions[{{ $examQuestion->id }}][time_limit_seconds]"
                                                    min="1"
                                                    step="1"
                                                    required
                                                    data-time-input
                                                    @input="recalculate()"
                                                    @disabled($isReadOnly)
                                                    value="{{ old('questions.' . $examQuestion->id . '.time_limit_seconds', (int) $examQuestion->time_limit_seconds) }}"
                                                    aria-label="Tiempo límite del reactivo {{ $examQuestion->id }}"
                                                    class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 font-mono text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech disabled:bg-slate-100"
                                                >
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

                    {{-- 2. Datos generales y Ventana horaria --}}
                    <x-card title="2. Título, instrucciones y ventana de aplicación" subtitle="Ajusta los horarios de inicio y cierre del examen.">
                        <div class="space-y-4">
                            <x-form.group name="title" label="Título del examen" :required="true">
                                <x-form.input name="title" value="{{ old('title', $exam->title) }}" maxlength="150" required :disabled="$isReadOnly" />
                            </x-form.group>

                            <x-form.group name="description" label="Descripción e instrucciones">
                                <x-form.textarea name="description" rows="3" :disabled="$isReadOnly">{{ old('description', $exam->description) }}</x-form.textarea>
                            </x-form.group>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <x-form.group name="starts_at" label="Fecha y hora de inicio" :required="true" :help-text="\App\Models\Exam::timezoneLabel()">
                                    <x-form.input type="datetime-local" name="starts_at" x-model="startsAt" :disabled="$isReadOnly" required />
                                </x-form.group>

                                <x-form.group name="ends_at" label="Fecha y hora de cierre" :required="true" :help-text="\App\Models\Exam::timezoneLabel()">
                                    <x-form.input type="datetime-local" name="ends_at" x-model="endsAt" :disabled="$isReadOnly" required />
                                </x-form.group>
                            </div>
                        </div>
                    </x-card>

                    {{-- 3. Grupos asignados --}}
                    <x-card title="3. Grupos escolares asignados" subtitle="Selecciona los grupos que presentarán esta evaluación.">
                        @if ($groups->isEmpty())
                            <x-empty-state
                                icon="userGroup"
                                title="Sin grupos disponibles"
                                message="Crea un grupo escolar abierto para poder asignarlo antes de publicar."
                            >
                                <x-button :href="route('groups.create')" variant="primary" size="sm">
                                    Crear grupo
                                </x-button>
                            </x-empty-state>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @forelse ($groups as $group)
                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition select-none"
                                        :class="selectedGroups.includes({{ $group->id }}) ? 'border-acode-tech bg-acode-surface/80' : 'border-slate-200 bg-white hover:border-slate-300'"
                                    >
                                        <input
                                            type="checkbox"
                                            name="group_ids[]"
                                            value="{{ $group->id }}"
                                            :checked="selectedGroups.includes({{ $group->id }})"
                                            @change="toggleGroup({{ $group->id }})"
                                            @disabled($isReadOnly)
                                            class="mt-1 h-4 w-4 rounded border-slate-300 text-acode-tech focus:ring-acode-tech"
                                        >
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-bold text-sm text-acode-primary truncate">{{ $group->name }}</span>
                                                <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                                                    {{ $group->is_closed ? 'Cerrado' : 'Activo' }}
                                                </x-badge>
                                            </div>
                                            <p class="text-xs text-slate-500 mt-0.5 truncate">
                                                {{ $group->subject ?: 'Sin asignatura' }} · {{ (int) ($group->students_count ?? 0) }} alumnos
                                            </p>
                                        </div>
                                    </label>
                                @empty
                                    <p class="text-xs text-slate-500">No hay grupos disponibles.</p>
                                @endforelse
                            </div>
                        @endif

                        @error('group_ids')
                            <p class="mt-2 text-xs font-medium text-acode-danger">{{ $message }}</p>
                        @enderror
                        @error('group_ids.*')
                            <p class="mt-2 text-xs font-medium text-acode-danger">{{ $message }}</p>
                        @enderror
                    </x-card>

                    {{-- 4. Escala, Visibilidad y Seguridad --}}
                    <x-card title="4. Escala, visibilidad y proctoring pasivo" subtitle="Define la conversión de escala y el comportamiento al entregar.">
                        <div class="space-y-6">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <p class="text-sm font-medium text-slate-800">Escala de calificación</p>
                                    @if ($hasAttempts)
                                        <x-badge variant="warning">Bloqueada: ya existen intentos</x-badge>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-4 transition {{ $lockScale ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer' }}"
                                        :class="gradingScale === '0-10' ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200'"
                                    >
                                        <input type="radio" name="grading_scale" value="0-10" x-model="gradingScale" @disabled($lockScale) class="mt-1 text-acode-tech">
                                        <div>
                                            <span class="block text-sm font-bold text-acode-primary">Escala 0 a 10</span>
                                            <span class="block text-xs text-slate-500 mt-0.5">
                                                La suma de 100.00 pts se divide automáticamente entre 10 al calcular la calificación final (ej. 85.00 → 8.50).
                                            </span>
                                        </div>
                                    </label>

                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-4 transition {{ $lockScale ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer' }}"
                                        :class="gradingScale === '0-100' ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200'"
                                    >
                                        <input type="radio" name="grading_scale" value="0-100" x-model="gradingScale" @disabled($lockScale) class="mt-1 text-acode-tech">
                                        <div>
                                            <span class="block text-sm font-bold text-acode-primary">Escala 0 a 100</span>
                                            <span class="block text-xs text-slate-500 mt-0.5">
                                                La puntuación obtenida sobre 100.00 puntos se conserva sin conversión.
                                            </span>
                                        </div>
                                    </label>
                                </div>
                                @error('grading_scale')
                                    <p class="mt-1.5 text-xs font-medium text-acode-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <p class="text-sm font-medium text-slate-800 mb-2">Visibilidad de resultados</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach ($visibilityModes as $modeKey => $modeInfo)
                                        <label
                                            class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition"
                                            :class="visibilityMode === @js($modeKey) ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200 hover:border-slate-300'"
                                        >
                                            <input type="radio" name="result_visibility_mode" value="{{ $modeKey }}" x-model="visibilityMode" @disabled($isReadOnly) class="mt-1 text-acode-tech">
                                            <div>
                                                <span class="block text-sm font-bold text-acode-primary">{{ $modeInfo['title'] }}</span>
                                                <span class="block text-xs text-slate-500 mt-0.5 leading-relaxed">{{ $modeInfo['desc'] }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                                <div>
                                    <p class="text-sm font-medium text-slate-800 mb-2">Orden de preguntas</p>
                                    <div class="space-y-2">
                                        <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 p-3 cursor-pointer">
                                            <input type="radio" name="randomize_question_order" value="1" x-model="randomizeOrder" @disabled($isReadOnly) class="mt-0.5 text-acode-tech">
                                            <span class="text-xs">
                                                <strong class="block text-acode-primary">Aleatorio</strong>
                                                <span class="text-slate-500">Baraja preguntas por intento.</span>
                                            </span>
                                        </label>
                                        <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 p-3 cursor-pointer">
                                            <input type="radio" name="randomize_question_order" value="0" x-model="randomizeOrder" @disabled($isReadOnly) class="mt-0.5 text-acode-tech">
                                            <span class="text-xs">
                                                <strong class="block text-acode-primary">Fijo por apartados</strong>
                                                <span class="text-slate-500">Mantiene secuencia de secciones y reactivos.</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <x-form.group name="max_allowed_incidents" label="Incidencias anti-trampa permitidas" :required="true">
                                    <x-form.input
                                        type="number"
                                        name="max_allowed_incidents"
                                        min="0"
                                        step="1"
                                        required
                                        :disabled="$isReadOnly"
                                        value="{{ old('max_allowed_incidents', $exam->max_allowed_incidents) }}"
                                    />
                                </x-form.group>
                            </div>

                            <div class="rounded-xl border border-acode-tech/25 bg-acode-surface/80 p-4 flex items-start gap-3">
                                <x-icon name="shield" class="w-5 h-5 text-acode-tech shrink-0 mt-0.5" />
                                <div class="text-xs text-slate-600 leading-relaxed">
                                    <p class="font-bold text-acode-primary">Proctoring pasivo activo (ADR-043 · LFPDPPP)</p>
                                    <p class="mt-0.5">Se detectan cambios de pestaña/app y expiración server-side por reactivo sin capturar cámara web ni datos biométricos.</p>
                                </div>
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- Columna Derecha Sticky --}}
                <aside class="lg:col-span-4 space-y-5 lg:sticky lg:top-24">
                    <x-card title="Resumen de configuración" subtitle="Se actualiza en vivo mientras editas.">
                        <div class="space-y-4">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ponderación total</p>
                                <p class="mt-1 font-mono text-2xl font-bold text-acode-primary">
                                    <span x-text="totalWeight.toFixed(2)"></span>
                                    <span class="text-sm font-medium text-slate-500">/ 100.00</span>
                                </p>
                                <p class="mt-1 text-xs font-semibold" :class="isWeightExact ? 'text-acode-accent' : 'text-acode-warning-dark'">
                                    <span x-text="isWeightExact ? 'Suma exacta completada' : 'Ajusta o distribuye los puntos hasta sumar 100.00'"></span>
                                </p>
                            </div>

                            <dl class="space-y-2.5 text-xs">
                                <div class="flex justify-between border-b border-slate-100 pb-2">
                                    <dt class="text-slate-500">Apartados</dt>
                                    <dd class="font-bold text-acode-primary">{{ $exam->sections->count() }}</dd>
                                </div>
                                <div class="flex justify-between border-b border-slate-100 pb-2">
                                    <dt class="text-slate-500">Reactivos</dt>
                                    <dd class="font-bold text-acode-primary">{{ $examQuestions->count() }}</dd>
                                </div>
                                <div class="flex justify-between border-b border-slate-100 pb-2">
                                    <dt class="text-slate-500">Tiempo total asignado</dt>
                                    <dd class="font-mono font-bold text-acode-primary" x-text="formattedTime"></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">Grupos seleccionados</dt>
                                    <dd class="font-bold text-acode-primary" x-text="selectedGroups.length + ' grupo(s)'"></dd>
                                </div>
                            </dl>
                        </div>
                    </x-card>

                    <div class="rounded-xl border border-slate-200 bg-white p-4 space-y-3 shadow-sm">
                        @if ($isReadOnly)
                            <x-button :href="route('exams.review', $exam)" variant="primary" size="lg" class="w-full">
                                Abrir resumen y publicación
                            </x-button>
                        @else
                            <x-button
                                type="submit"
                                id="save-configuration"
                                variant="primary"
                                size="lg"
                                class="w-full"
                                x-bind:disabled="!isWeightExact"
                            >
                                <x-icon name="save" class="w-4 h-4" />
                                Guardar y continuar a Revisión
                            </x-button>

                            <div class="flex justify-center">
                                <x-unsaved-changes form-id="exam-configuration-form" :cancel-url="route('exams.assemble', $exam)" />
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        </form>
    </div>
@endsection
