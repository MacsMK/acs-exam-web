{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/edit.blade.php (MPW-V2 §4.1.2, §7, §8, ADR-043, ADR-056) --}}
@extends('layouts.app')

@section('title', 'Editar examen: ' . $exam->title)

@section('content')
    @php
        $isPublished = (bool) $exam->is_published;
        $hasAttempts = ((int) ($exam->student_attempts_count ?? 0)) > 0;
        $hasStartedWindow = $exam->hasStartedWindow();
        $isWindowClosed = $exam->isWindowClosed();
        $lockScale = $isPublished || $hasAttempts;
        $lockRandomize = $isPublished;
        $lockStart = $isPublished && $hasStartedWindow;

        $initialStartsAt = old('starts_at', $exam->starts_at?->format('Y-m-d\TH:i') ?? '');
        $initialEndsAt = old('ends_at', $exam->ends_at?->format('Y-m-d\TH:i') ?? '');
        $initialScale = old('grading_scale', $exam->grading_scale);
        $initialVisibility = old('result_visibility_mode', $exam->result_visibility_mode);
        $initialRandomize = (string) old('randomize_question_order', $exam->randomize_question_order ? '1' : '0');
        $initialIncidents = old('max_allowed_incidents', $exam->max_allowed_incidents ?? 3);

        $visibilityModes = [
            'final_score' => [
                'title' => 'Calificación final inmediata',
                'desc' => 'El alumno visualiza su nota al entregar (y tras recoteje si hay reactivos abiertos o de código).',
            ],
            'estimated_score' => [
                'title' => 'Puntaje preliminar estimado',
                'desc' => 'Muestra un puntaje provisional al entregar, manteniendo oculta cualquier retroalimentación de IA (ADR-050).',
            ],
            'objective_only_pending' => [
                'title' => 'Solo puntaje objetivo (pendiente docente)',
                'desc' => 'Exhibe únicamente los aciertos objetivos y marca los reactivos abiertos como pendientes de revisión docente.',
            ],
            'hidden' => [
                'title' => 'Resultados ocultos hasta liberación',
                'desc' => 'Reserva calificaciones y desglose hasta que tú decidas liberarlos manualmente.',
            ],
        ];
    @endphp

    <div
        class="space-y-6"
        x-data="{
            title: @js(old('title', $exam->title)),
            startsAt: @js($initialStartsAt),
            endsAt: @js($initialEndsAt),
            gradingScale: @js($initialScale),
            visibilityMode: @js($initialVisibility),
            randomizeOrder: @js($initialRandomize),
            maxIncidents: @js((int) $initialIncidents),
            selectedGroups: @js(array_map('intval', $selectedGroupIds)),

            applyDurationPreset(hours) {
                let base = this.startsAt ? new Date(this.startsAt) : new Date();
                if (Number.isNaN(base.getTime())) {
                    base = new Date();
                    this.startsAt = this.formatLocalDateTime(base);
                }
                const target = new Date(base.getTime() + hours * 3600 * 1000);
                this.endsAt = this.formatLocalDateTime(target);
            },

            formatLocalDateTime(date) {
                const pad = (n) => String(n).padStart(2, '0');
                return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
            },

            toggleGroup(groupId) {
                const id = Number(groupId);
                if (this.selectedGroups.includes(id)) {
                    this.selectedGroups = this.selectedGroups.filter((g) => g !== id);
                } else {
                    this.selectedGroups.push(id);
                }
            }
        }"
    >
        <x-page-header
            title="Editar examen"
            subtitle="Actualiza metadatos, grupos asignados, ventana de aplicación y políticas del examen."
            :breadcrumbs="[
                ['label' => 'Exámenes', 'href' => route('exams.index')],
                ['label' => $exam->title],
                ['label' => 'Editar'],
            ]"
        >
            <x-slot:actions>
                <x-button :href="route('exams.index')" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
            </x-slot:actions>
        </x-page-header>

        @include('exams.partials._stepper', ['currentStep' => 1, 'exam' => $exam])

        @if ($isPublished)
            <div class="rounded-xl border border-acode-tech/30 bg-acode-surface px-4 py-3 text-xs sm:text-sm text-acode-primary flex items-start gap-2.5">
                <x-icon name="lock" class="w-5 h-5 shrink-0 text-acode-tech mt-0.5" />
                <div>
                    <p class="font-bold">Examen publicado: protecciones de integridad activas</p>
                    <p class="mt-0.5 text-slate-600">
                        @if ($isWindowClosed)
                            La ventana de aplicación ya concluyó. Puedes consultar monitoreo o recoteje desde el resumen.
                        @elseif ($hasStartedWindow)
                            La ventana ya dio inicio; la escala, el orden y la fecha de inicio permanecen bloqueados.
                        @else
                            El examen está programado; la escala de calificación y el orden estructural no pueden alterarse tras publicar.
                        @endif
                    </p>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger" role="alert">
                <p class="font-semibold mb-1">Revisa lo siguiente:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="edit-exam-form" method="POST" action="{{ route('exams.update', $exam) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="require_webcam" value="0">

            @if ($lockScale)
                <input type="hidden" name="grading_scale" value="{{ $exam->grading_scale }}">
            @endif
            @if ($lockRandomize)
                <input type="hidden" name="randomize_question_order" value="{{ $exam->randomize_question_order ? '1' : '0' }}">
            @endif
            @if ($lockStart)
                <input type="hidden" name="starts_at" value="{{ $exam->starts_at?->format('Y-m-d\TH:i') }}">
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <div class="lg:col-span-8 space-y-6">
                    {{-- Tarjeta 1: Datos generales --}}
                    <x-card title="1. Datos generales" subtitle="Título visible e instrucciones iniciales.">
                        <div class="space-y-5">
                            <x-form.group name="title" label="Título del examen" :required="true">
                                <x-form.input
                                    name="title"
                                    x-model="title"
                                    maxlength="150"
                                    required
                                    :disabled="$isWindowClosed"
                                />
                            </x-form.group>

                            <x-form.group name="description" label="Descripción e instrucciones generales">
                                <x-form.textarea
                                    name="description"
                                    rows="4"
                                    :disabled="$isWindowClosed"
                                >{{ old('description', $exam->description) }}</x-form.textarea>
                            </x-form.group>
                        </div>
                    </x-card>

                    {{-- Tarjeta 2: Grupos escolares --}}
                    <x-card title="2. Grupos escolares asignados" subtitle="Selecciona los grupos con acceso habilitado a esta evaluación.">
                        @if (! $hasOpenGroups)
                            <div class="mb-4 rounded-xl border border-acode-warning/60 bg-acode-warning/15 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-start gap-2.5 text-xs sm:text-sm text-acode-primary">
                                    <x-icon name="warning" class="w-5 h-5 shrink-0 text-acode-warning-dark mt-0.5" />
                                    <p class="font-medium">No tienes grupos abiertos en tu catálogo. Crea uno nuevo para poder publicar el examen.</p>
                                </div>
                                <x-button :href="route('groups.create')" variant="warning" size="sm" class="shrink-0">
                                    Crear grupo
                                </x-button>
                            </div>
                        @endif

                        @if ($groups->isNotEmpty())
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @forelse ($groups as $group)
                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition select-none"
                                        :class="selectedGroups.includes({{ $group->id }})
                                            ? 'border-acode-tech bg-acode-surface/80 ring-1 ring-acode-tech/30'
                                            : 'border-slate-200 bg-white hover:border-slate-300'"
                                    >
                                        <input
                                            type="checkbox"
                                            name="group_ids[]"
                                            value="{{ $group->id }}"
                                            :checked="selectedGroups.includes({{ $group->id }})"
                                            @change="toggleGroup({{ $group->id }})"
                                            @disabled($isWindowClosed)
                                            class="mt-1 h-4 w-4 rounded border-slate-300 text-acode-tech focus:ring-acode-tech"
                                        >
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-semibold text-sm text-acode-primary truncate">{{ $group->name }}</span>
                                                <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                                                    {{ $group->is_closed ? 'Cerrado' : 'Activo' }}
                                                </x-badge>
                                            </div>
                                            <p class="text-xs text-slate-500 mt-0.5 truncate">
                                                {{ $group->subject ?: 'Sin asignatura' }} · Folio <span class="font-mono font-semibold">{{ $group->access_folio }}</span>
                                            </p>
                                            <p class="text-xs text-slate-500 mt-1">
                                                {{ (int) ($group->students_count ?? 0) }} {{ (int) ($group->students_count ?? 0) === 1 ? 'alumno inscrito' : 'alumnos inscritos' }}
                                            </p>
                                        </div>
                                    </label>
                                @empty
                                    <div class="col-span-full">
                                        <x-empty-state
                                            icon="userGroup"
                                            title="Sin grupos registrados"
                                            message="Crea un grupo escolar para asignarlo al examen."
                                        />
                                    </div>
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

                    {{-- Tarjeta 3: Ventana horaria --}}
                    <x-card title="3. Ventana de aplicación" subtitle="Ajusta las fechas y horas de apertura y cierre.">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <x-form.group name="starts_at" label="Fecha y hora de inicio" :required="true">
                                    <x-form.input
                                        type="datetime-local"
                                        name="starts_at"
                                        x-model="startsAt"
                                        :disabled="$lockStart || $isWindowClosed"
                                        required
                                    />
                                </x-form.group>

                                <x-form.group name="ends_at" label="Fecha y hora de cierre" :required="true">
                                    <x-form.input
                                        type="datetime-local"
                                        name="ends_at"
                                        x-model="endsAt"
                                        :disabled="$isWindowClosed"
                                        required
                                    />
                                </x-form.group>
                            </div>

                            @unless ($isWindowClosed)
                                <div class="flex flex-wrap items-center gap-2 pt-1">
                                    <span class="text-xs font-semibold text-slate-500 mr-1">Duración rápida desde inicio:</span>
                                    @foreach ([1 => '+1 hora', 2 => '+2 horas', 24 => '+24 horas', 168 => '+7 días'] as $hours => $presetLabel)
                                        <x-button type="button" variant="neutral" size="sm" x-on:click="applyDurationPreset({{ $hours }})">
                                            {{ $presetLabel }}
                                        </x-button>
                                    @endforeach
                                </div>
                            @endunless
                        </div>
                    </x-card>

                    {{-- Tarjeta 4: Escala, visibilidad y seguridad --}}
                    <x-card title="4. Escala, visibilidad y seguridad" subtitle="Reglas de calificación y supervisión pasiva.">
                        <div class="space-y-6">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <p class="text-sm font-medium text-slate-800">
                                        Escala de calificación <span class="text-acode-danger font-bold">*</span>
                                    </p>
                                    @if ($lockScale)
                                        <x-badge variant="neutral">Bloqueada ({{ $hasAttempts ? 'con intentos' : 'publicado' }})</x-badge>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-4 transition {{ $lockScale ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer' }}"
                                        :class="gradingScale === '0-10' ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200'"
                                    >
                                        <input type="radio" name="grading_scale" value="0-10" x-model="gradingScale" @disabled($lockScale) class="mt-1 text-acode-tech focus:ring-acode-tech">
                                        <div>
                                            <span class="block text-sm font-bold text-acode-primary">Escala 0 a 10</span>
                                            <span class="block text-xs text-slate-500 mt-0.5">Conversión automática / 10 desde ponderación 100.00 pts.</span>
                                        </div>
                                    </label>

                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-4 transition {{ $lockScale ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer' }}"
                                        :class="gradingScale === '0-100' ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200'"
                                    >
                                        <input type="radio" name="grading_scale" value="0-100" x-model="gradingScale" @disabled($lockScale) class="mt-1 text-acode-tech focus:ring-acode-tech">
                                        <div>
                                            <span class="block text-sm font-bold text-acode-primary">Escala 0 a 100</span>
                                            <span class="block text-xs text-slate-500 mt-0.5">Calificación directa sobre 100.00 puntos.</span>
                                        </div>
                                    </label>
                                </div>
                                @error('grading_scale')
                                    <p class="mt-1.5 text-xs font-medium text-acode-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <p class="text-sm font-medium text-slate-800 mb-2">
                                    Visibilidad de resultados <span class="text-acode-danger font-bold">*</span>
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach ($visibilityModes as $modeKey => $modeInfo)
                                        <label
                                            class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition"
                                            :class="visibilityMode === @js($modeKey) ? 'border-acode-tech bg-acode-surface/70' : 'border-slate-200 hover:border-slate-300'"
                                        >
                                            <input type="radio" name="result_visibility_mode" value="{{ $modeKey }}" x-model="visibilityMode" @disabled($isWindowClosed) class="mt-1 text-acode-tech focus:ring-acode-tech">
                                            <div>
                                                <span class="block text-sm font-bold text-acode-primary">{{ $modeInfo['title'] }}</span>
                                                <span class="block text-xs text-slate-500 mt-0.5 leading-relaxed">{{ $modeInfo['desc'] }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                @error('result_visibility_mode')
                                    <p class="mt-1.5 text-xs font-medium text-acode-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-sm font-medium text-slate-800">Orden de preguntas</p>
                                        @if ($lockRandomize)
                                            <x-badge variant="neutral">Bloqueado</x-badge>
                                        @endif
                                    </div>
                                    <div class="space-y-2">
                                        <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 p-3 {{ $lockRandomize ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer hover:border-acode-tech' }}">
                                            <input type="radio" name="randomize_question_order" value="1" x-model="randomizeOrder" @disabled($lockRandomize) class="mt-0.5 text-acode-tech">
                                            <span class="text-xs">
                                                <strong class="block text-acode-primary">Aleatorio (Anti-copia)</strong>
                                                <span class="text-slate-500">Baraja el orden por estudiante.</span>
                                            </span>
                                        </label>
                                        <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 p-3 {{ $lockRandomize ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer hover:border-acode-tech' }}">
                                            <input type="radio" name="randomize_question_order" value="0" x-model="randomizeOrder" @disabled($lockRandomize) class="mt-0.5 text-acode-tech">
                                            <span class="text-xs">
                                                <strong class="block text-acode-primary">Fijo por apartados</strong>
                                                <span class="text-slate-500">Respeta el orden exacto de tus apartados.</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <x-form.group
                                    name="max_allowed_incidents"
                                    label="Incidencias anti-trampa permitidas"
                                    :required="true"
                                >
                                    <x-form.input
                                        type="number"
                                        name="max_allowed_incidents"
                                        min="0"
                                        step="1"
                                        x-model.number="maxIncidents"
                                        :disabled="$isWindowClosed"
                                        required
                                    />
                                </x-form.group>
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- Columna derecha: Resumen y guardado --}}
                <aside class="lg:col-span-4 space-y-5 lg:sticky lg:top-24">
                    <x-card title="Estado del examen" subtitle="Resumen de cambios pendientes.">
                        <dl class="space-y-3 text-xs">
                            <div class="rounded-xl bg-acode-surface p-3.5 border border-slate-200">
                                <dt class="text-slate-500 font-semibold uppercase tracking-wider">Examen</dt>
                                <dd class="mt-1 text-sm font-bold text-acode-primary break-words" x-text="title.trim() || 'Sin título'"></dd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-slate-100">
                                <dt class="text-slate-500">Grupos seleccionados</dt>
                                <dd class="font-bold text-acode-primary" x-text="selectedGroups.length + ' grupo(s)'"></dd>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-slate-100">
                                <dt class="text-slate-500">Intentos registrados</dt>
                                <dd class="font-mono font-bold text-acode-primary">{{ (int) ($exam->student_attempts_count ?? 0) }}</dd>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <dt class="text-slate-500">Escala</dt>
                                <dd class="font-semibold text-acode-primary" x-text="gradingScale"></dd>
                            </div>
                        </dl>
                    </x-card>

                    <div class="rounded-xl border border-acode-tech/25 bg-acode-surface/80 p-4 space-y-2">
                        <div class="flex items-center gap-2 text-acode-primary font-bold text-xs uppercase tracking-wider">
                            <x-icon name="shield" class="w-4 h-4 text-acode-tech" />
                            <span>Proctoring pasivo (ADR-043)</span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Supervisión mediante eventos de foco y temporizador server-side sin uso de cámara web.
                        </p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4 space-y-3 shadow-sm">
                        @if ($isWindowClosed)
                            <x-button :href="route('exams.review', $exam)" variant="primary" size="lg" class="w-full">
                                Abrir resumen y monitoreo
                            </x-button>
                        @else
                            <x-button type="submit" variant="primary" size="lg" class="w-full">
                                <x-icon name="save" class="w-4 h-4" />
                                Guardar cambios y continuar
                            </x-button>

                            <div class="flex justify-center">
                                <x-unsaved-changes form-id="edit-exam-form" :cancel-url="route('exams.index')" />
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        </form>
    </div>
@endsection
