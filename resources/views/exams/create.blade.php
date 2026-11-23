{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/create.blade.php (MPW-V3 §4.1.2, §7, §8, ADR-043, ADR-056) --}}
@extends('layouts.app')

@section('title', 'Crear examen')

@section('content')
    @php
        $defaultStartsAt = old('starts_at', now()->addMinutes(15)->format('Y-m-d\TH:i'));
        $defaultEndsAt = old('ends_at', now()->addHours(2)->addMinutes(15)->format('Y-m-d\TH:i'));
        $defaultScale = old('grading_scale', '0-10');
        $defaultVisibility = old('result_visibility_mode', 'final_score');
        $defaultRandomize = (string) old('randomize_question_order', '1');
        $defaultIncidents = old('max_allowed_incidents', 3);

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
            title: @js(old('title', '')),
            startsAt: @js($defaultStartsAt),
            endsAt: @js($defaultEndsAt),
            gradingScale: @js($defaultScale),
            visibilityMode: @js($defaultVisibility),
            randomizeOrder: @js($defaultRandomize),
            maxIncidents: @js((int) $defaultIncidents),
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
            },

            get scaleLabel() {
                return this.gradingScale === '0-100' ? '0 a 100 puntos' : '0 a 10 puntos (conversión automática)';
            },

            get visibilityLabel() {
                const map = {
                    final_score: 'Calificación final inmediata',
                    estimated_score: 'Puntaje preliminar estimado',
                    objective_only_pending: 'Solo puntaje objetivo (pendiente docente)',
                    hidden: 'Resultados ocultos hasta liberación',
                };
                return map[this.visibilityMode] || 'No definido';
            }
        }"
    >
        {{-- Navegación superior y encabezado --}}
        <x-page-header
            title="Crear examen"
            subtitle="Define los datos generales, grupos destinatarios, ventana de aplicación y reglas de evaluación."
            :breadcrumbs="[
                ['label' => 'Exámenes', 'href' => route('exams.index')],
                ['label' => 'Crear examen'],
            ]"
        >
            <x-slot:actions>
                <x-button :href="route('exams.index')" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>
            </x-slot:actions>
        </x-page-header>

        @include('exams.partials._stepper', ['currentStep' => 1, 'exam' => null, 'totalPoints' => 0])

        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger" role="alert">
                <p class="font-semibold mb-1">Revisa los datos capturados antes de continuar:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="create-exam-form" method="POST" action="{{ route('exams.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="require_webcam" value="0">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {{-- Columna principal del formulario --}}
                <div class="lg:col-span-8 space-y-6">
                    {{-- Tarjeta 1: Datos generales --}}
                    <x-card title="1. Datos generales" subtitle="Identifica la evaluación y redacta las instrucciones para tus estudiantes.">
                        <div class="space-y-5">
                            <x-form.group name="title" label="Título del examen" :required="true">
                                <x-form.input
                                    name="title"
                                    x-model="title"
                                    maxlength="150"
                                    required
                                    placeholder="Ej. Primer examen parcial de Programación"
                                />
                            </x-form.group>

                            <x-form.group
                                name="description"
                                label="Descripción e instrucciones generales"
                                help-text="Opcional. Se mostrará en la pantalla previa de instrucciones antes de iniciar el intento."
                            >
                                <x-form.textarea
                                    name="description"
                                    rows="4"
                                    placeholder="Ej. Lee cuidadosamente cada reactivo antes de responder. Recuerda que no es posible regresar a preguntas anteriores."
                                >{{ old('description') }}</x-form.textarea>
                            </x-form.group>
                        </div>
                    </x-card>

                    {{-- Tarjeta 2: Grupos asignados --}}
                    <x-card title="2. Grupos escolares asignados" subtitle="Selecciona las aulas que podrán visualizar y presentar este examen.">
                        @if (! $hasOpenGroups)
                            <div class="mb-4 rounded-xl border border-acode-warning/60 bg-acode-warning/15 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-start gap-2.5 text-xs sm:text-sm text-acode-primary">
                                    <x-icon name="warning" class="w-5 h-5 shrink-0 text-acode-warning-dark mt-0.5" />
                                    <div>
                                        <p class="font-semibold">No tienes grupos escolares abiertos todavía</p>
                                        <p class="text-xs text-slate-700 mt-0.5">
                                            Puedes guardar el examen como borrador y crear un grupo abierto antes de publicarlo.
                                        </p>
                                    </div>
                                </div>
                                <x-button :href="route('groups.create')" variant="warning" size="sm" class="shrink-0">
                                    <x-icon name="add" class="w-4 h-4" />
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
                                            title="Sin grupos disponibles"
                                            message="Crea un grupo escolar abierto para asignarlo a tus evaluaciones."
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

                    {{-- Tarjeta 3: Ventana de aplicación --}}
                    <x-card title="3. Ventana de aplicación" subtitle="Define el horario en el que los alumnos podrán ingresar y entregar su examen.">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <x-form.group name="starts_at" label="Fecha y hora de inicio" :required="true" :help-text="\App\Models\Exam::timezoneLabel()">
                                    <x-form.input
                                        type="datetime-local"
                                        name="starts_at"
                                        x-model="startsAt"
                                        required
                                    />
                                </x-form.group>

                                <x-form.group name="ends_at" label="Fecha y hora de cierre" :required="true" :help-text="\App\Models\Exam::timezoneLabel()">
                                    <x-form.input
                                        type="datetime-local"
                                        name="ends_at"
                                        x-model="endsAt"
                                        required
                                    />
                                </x-form.group>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <span class="text-xs font-semibold text-slate-500 mr-1">Duración rápida desde inicio:</span>
                                @foreach ([1 => '+1 hora', 2 => '+2 horas', 24 => '+24 horas', 168 => '+7 días'] as $hours => $presetLabel)
                                    <x-button type="button" variant="neutral" size="sm" x-on:click="applyDurationPreset({{ $hours }})">
                                        {{ $presetLabel }}
                                    </x-button>
                                @endforeach
                            </div>
                        </div>
                    </x-card>

                    {{-- Tarjeta 4: Escala, visibilidad y seguridad --}}
                    <x-card title="4. Escala, visibilidad y seguridad" subtitle="Configura cómo se calculará y mostrará la calificación al concluir.">
                        <div class="space-y-6">
                            {{-- Escala de calificación --}}
                            <div>
                                <p class="text-sm font-medium text-slate-800 mb-2">
                                    Escala de calificación <span class="text-acode-danger font-bold">*</span>
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition"
                                        :class="gradingScale === '0-10' ? 'border-acode-tech bg-acode-surface/70 ring-1 ring-acode-tech/30' : 'border-slate-200 hover:border-slate-300'"
                                    >
                                        <input type="radio" name="grading_scale" value="0-10" x-model="gradingScale" class="mt-1 text-acode-tech focus:ring-acode-tech">
                                        <div>
                                            <span class="block text-sm font-bold text-acode-primary">Escala 0 a 10</span>
                                            <span class="block text-xs text-slate-500 mt-0.5">
                                                Los reactivos suman 100.00 pts y el sistema convierte automáticamente entre 10 al calificar (ej. 85 pts = 8.50).
                                            </span>
                                        </div>
                                    </label>

                                    <label
                                        class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition"
                                        :class="gradingScale === '0-100' ? 'border-acode-tech bg-acode-surface/70 ring-1 ring-acode-tech/30' : 'border-slate-200 hover:border-slate-300'"
                                    >
                                        <input type="radio" name="grading_scale" value="0-100" x-model="gradingScale" class="mt-1 text-acode-tech focus:ring-acode-tech">
                                        <div>
                                            <span class="block text-sm font-bold text-acode-primary">Escala 0 a 100</span>
                                            <span class="block text-xs text-slate-500 mt-0.5">
                                                El puntaje obtenido sobre 100.00 puntos se conserva directo como calificación final.
                                            </span>
                                        </div>
                                    </label>
                                </div>
                                @error('grading_scale')
                                    <p class="mt-1.5 text-xs font-medium text-acode-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Visibilidad de resultados --}}
                            <div>
                                <p class="text-sm font-medium text-slate-800 mb-2">
                                    Visibilidad de resultados para el estudiante <span class="text-acode-danger font-bold">*</span>
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach ($visibilityModes as $modeKey => $modeInfo)
                                        <label
                                            class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition"
                                            :class="visibilityMode === @js($modeKey) ? 'border-acode-tech bg-acode-surface/70 ring-1 ring-acode-tech/30' : 'border-slate-200 hover:border-slate-300'"
                                        >
                                            <input type="radio" name="result_visibility_mode" value="{{ $modeKey }}" x-model="visibilityMode" class="mt-1 text-acode-tech focus:ring-acode-tech">
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

                            {{-- Orden e incidentes --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                                <div>
                                    <p class="text-sm font-medium text-slate-800 mb-2">Orden de preguntas</p>
                                    <div class="space-y-2">
                                        <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 p-3 cursor-pointer hover:border-acode-tech">
                                            <input type="radio" name="randomize_question_order" value="1" x-model="randomizeOrder" class="mt-0.5 text-acode-tech focus:ring-acode-tech">
                                            <span class="text-xs">
                                                <strong class="block text-acode-primary">Aleatorio (Anti-copia)</strong>
                                                <span class="text-slate-500">Baraja el orden por estudiante.</span>
                                            </span>
                                        </label>
                                        <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 p-3 cursor-pointer hover:border-acode-tech">
                                            <input type="radio" name="randomize_question_order" value="0" x-model="randomizeOrder" class="mt-0.5 text-acode-tech focus:ring-acode-tech">
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
                                    help-text="Al superar este límite por cambio de pestaña o aplicación, el intento se cancela automáticamente."
                                >
                                    <x-form.input
                                        type="number"
                                        name="max_allowed_incidents"
                                        min="0"
                                        step="1"
                                        x-model.number="maxIncidents"
                                        required
                                    />
                                </x-form.group>
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- Columna derecha: Guía y resumen en vivo --}}
                <aside class="lg:col-span-4 space-y-5 lg:sticky lg:top-24">
                    <x-card title="Resumen en vivo" subtitle="Vista previa antes de guardar el borrador.">
                        <dl class="space-y-3 text-xs">
                            <div class="rounded-xl bg-acode-surface p-3.5 border border-slate-200">
                                <dt class="text-slate-500 font-semibold uppercase tracking-wider">Título</dt>
                                <dd class="mt-1 text-sm font-bold text-acode-primary break-words" x-text="title.trim() || 'Sin título capturado'"></dd>
                            </div>

                            <div class="flex items-center justify-between py-2 border-b border-slate-100">
                                <dt class="text-slate-500">Grupos asignados</dt>
                                <dd class="font-bold text-acode-primary" x-text="selectedGroups.length + ' grupo(s)'"></dd>
                            </div>

                            <div class="flex items-center justify-between py-2 border-b border-slate-100">
                                <dt class="text-slate-500">Escala</dt>
                                <dd class="font-semibold text-acode-primary text-right" x-text="scaleLabel"></dd>
                            </div>

                            <div class="flex items-center justify-between py-2 border-b border-slate-100">
                                <dt class="text-slate-500">Visibilidad</dt>
                                <dd class="font-semibold text-acode-primary text-right max-w-[12rem]" x-text="visibilityLabel"></dd>
                            </div>

                            <div class="flex items-center justify-between py-2 border-b border-slate-100">
                                <dt class="text-slate-500">Orden</dt>
                                <dd class="font-semibold text-acode-primary" x-text="randomizeOrder === '1' ? 'Aleatorio' : 'Fijo por apartado'"></dd>
                            </div>

                            <div class="flex items-center justify-between py-2">
                                <dt class="text-slate-500">Tolerancia anti-trampa</dt>
                                <dd class="font-mono font-bold text-acode-primary" x-text="maxIncidents + ' incidente(s)'"></dd>
                            </div>
                        </dl>
                    </x-card>

                    {{-- Tarjeta informativa Proctoring Pasivo ADR-043 --}}
                    <div class="rounded-xl border border-acode-tech/25 bg-acode-surface/80 p-4 space-y-2">
                        <div class="flex items-center gap-2 text-acode-primary font-bold text-xs uppercase tracking-wider">
                            <x-icon name="shield" class="w-4 h-4 text-acode-tech" />
                            <span>Proctoring pasivo (ADR-043)</span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Para proteger la privacidad bajo <strong>LFPDPPP</strong>, la plataforma supervisa cambios de foco, salida de pestaña/app y cronómetro en servidor sin solicitar cámara web ni biometría invasiva.
                        </p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4 space-y-3 shadow-sm">
                        <p class="text-xs text-slate-500 leading-relaxed">
                            El examen se guardará como <strong>borrador</strong> y pasarás inmediatamente a la Etapa 2 para sortear o seleccionar reactivos.
                        </p>

                        <div class="flex flex-col gap-2.5">
                            <x-button type="submit" variant="primary" size="lg" class="w-full">
                                <x-icon name="save" class="w-4 h-4" />
                                Crear borrador y armar reactivos
                            </x-button>

                            <div class="flex justify-center">
                                <x-unsaved-changes form-id="create-exam-form" :cancel-url="route('exams.index')" />
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </form>
    </div>
@endsection
