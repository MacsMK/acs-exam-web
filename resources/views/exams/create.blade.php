{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/create.blade.php (MPW-V3 §4.1.2, §7, §8, ADR-043, ADR-056) --}}
@extends('layouts.app')

@section('title', 'Crear examen')

@section('content')
    @php
        $currentTime = now();
        $minimumStart = $currentTime->format('Y-m-d\TH:i');
        $minimumEnd = $currentTime->copy()->addMinute()->format('Y-m-d\TH:i');
        $defaultStartsAt = old('starts_at', $currentTime->copy()->addMinutes(15)->format('Y-m-d\TH:i'));
        $defaultEndsAt = old('ends_at', $currentTime->copy()->addMinutes(135)->format('Y-m-d\TH:i'));
        $defaultScale = old('grading_scale', '0-10');
        $defaultVisibility = old('result_visibility_mode', 'final_score');
        $defaultRandomize = (string) old('randomize_question_order', '1');
        $defaultIncidents = old('max_allowed_incidents', 3);
        $selectedGroupIds = array_map('intval', $selectedGroupIds);
        $groupError = $errors->first('group_ids') ?: $errors->first('group_ids.*');
        $choiceClasses = 'flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-acode-tech';
        $selectedChoiceClasses = 'border-acode-tech bg-acode-surface/70';
        $plainChoiceClasses = 'border-slate-200 hover:border-slate-300';

        $visibilityModes = [
            'final_score' => [
                'title' => 'Calificación final inmediata',
                'desc' => 'Al entregar, el estudiante ve su calificación final si está lista. Si hay revisión pendiente, primero ve el puntaje objetivo.',
            ],
            'estimated_score' => [
                'title' => 'Puntaje preliminar estimado',
                'desc' => 'Mientras la revisión está pendiente, muestra un puntaje provisional con la estimación de IA disponible. La retroalimentación de IA sigue oculta.',
            ],
            'objective_only_pending' => [
                'title' => 'Solo puntaje objetivo (pendiente docente)',
                'desc' => 'Si hay revisión pendiente, muestra solo los puntos de las respuestas objetivas. La calificación final aparece al terminar la revisión.',
            ],
            'hidden' => [
                'title' => 'Resultados ocultos hasta liberación',
                'desc' => 'Reserva calificaciones y desglose hasta que tú decidas liberarlos manualmente.',
            ],
        ];

        $form = [
            'title' => old('title') ?? '',
            'startsAt' => $defaultStartsAt,
            'endsAt' => $defaultEndsAt,
            'minimumStart' => $minimumStart,
            'timezone' => config('app.timezone'),
            'initialScale' => $defaultScale,
            'gradingScale' => $defaultScale,
            'scaleEdited' => session()->hasOldInput('grading_scale'),
            'visibilityMode' => $defaultVisibility,
            'randomizeOrder' => $defaultRandomize,
            'maxIncidents' => $defaultIncidents,
            'selectedGroups' => $selectedGroupIds,
            'groupScheduling' => $examGroupScheduling,
            'visibilityModes' => $visibilityModes,
        ];
    @endphp

    <div class="space-y-5" x-data="examCreateForm(@js($form))">
        {{-- Navegación superior y encabezado --}}
        <x-page-header
            title="Crear examen"
            :institutional="true"
            :breadcrumbs="[
                ['label' => 'Exámenes', 'href' => route('exams.index')],
                ['label' => 'Crear examen'],
            ]"
        />

        @include('exams.partials._stepper', ['currentStep' => 1, 'exam' => null, 'totalPoints' => 0])

        @if ($errors->any())
            @php
                $errorTargets = ['title', 'description', 'group_ids', 'starts_at', 'ends_at', 'grading_scale', 'result_visibility_mode', 'randomize_question_order', 'max_allowed_incidents'];
            @endphp
            <section tabindex="-1" x-init="$nextTick(() => $el.focus())" aria-labelledby="create-exam-errors"
                     class="scroll-mt-24 rounded-xl border border-acode-danger/30 bg-white px-4 py-3 text-sm text-acode-danger-dark focus:outline focus:outline-2 focus:outline-acode-danger">
                <h2 id="create-exam-errors" class="mb-1 font-semibold">Revisa los datos capturados antes de continuar</h2>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->getMessages() as $field => $messages)
                        @php($target = \Illuminate\Support\Str::before($field, '.'))
                        @foreach ($messages as $message)
                            <li>
                                @if (in_array($target, $errorTargets, true))
                                    <a href="#{{ $target }}" class="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-danger">
                                        {{ $message }}
                                    </a>
                                @else
                                    {{ $message }}
                                @endif
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </section>
        @endif

        <form
            id="create-exam-form"
            method="POST"
            action="{{ route('exams.store') }}"
            @submit="submit($event)"
            @pageshow.window="saving = false; refreshMinimumStart()"
            class="space-y-5"
        >
            @csrf
            <input type="hidden" name="require_webcam" value="0">

            <div class="grid gap-5 lg:grid-cols-2">
                {{-- Tarjeta 1: Datos generales --}}
                <x-card title="1. Datos generales" padding="p-4 sm:p-5">
                    <div class="space-y-4">
                        <x-form.group name="title" label="Título del examen" :required="true" accessible>
                            <x-form.input
                                name="title"
                                :value="old('title')"
                                x-model="title"
                                maxlength="150"
                                required
                                accessible
                                placeholder="Ej. Primer examen parcial de Programación"
                                :aria-describedby="$errors->has('title') ? 'title-error' : null"
                                class="scroll-mt-24"
                            />
                        </x-form.group>

                        <x-form.group
                            name="description"
                            label="Descripción e instrucciones generales"
                            help-text="Opcional. Se mostrará en la pantalla previa de instrucciones antes de iniciar el intento."
                            accessible
                        >
                            <x-form.textarea
                                name="description"
                                rows="3"
                                accessible
                                placeholder="Ej. Lee cada instrucción con atención antes de responder."
                                :aria-describedby="$errors->has('description') ? 'description-help description-error' : 'description-help'"
                                class="scroll-mt-24"
                            >{{ old('description') }}</x-form.textarea>
                        </x-form.group>
                    </div>
                </x-card>

                {{-- Tarjeta 2: Grupos asignados --}}
                <x-card title="2. Grupos escolares asignados" padding="p-4 sm:p-5">
                    <fieldset id="group_ids" tabindex="-1" aria-describedby="group-assignments-help{{ $groupError ? ' group-assignments-error' : '' }}"
                              @if ($groupError) aria-invalid="true" @endif
                              class="min-w-0 scroll-mt-24 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                        <legend class="sr-only">Grupos escolares asignados</legend>
                        <p id="group-assignments-help" class="mb-3 text-xs text-slate-600">
                            Puedes guardar sin grupos. Para publicar, asigna al menos un grupo escolar abierto.
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 @if ($groups->count() > 6) max-h-72 overflow-y-auto p-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech @endif"
                             @if ($groups->count() > 6) tabindex="0" role="region" aria-label="Grupos disponibles" @endif>
                            @forelse ($groups as $group)
                                <label
                                    class="{{ $choiceClasses }}"
                                    :class="selectedGroups.includes({{ $group->id }}) ? @js($selectedChoiceClasses) : @js($plainChoiceClasses)"
                                >
                                    <input
                                        type="checkbox"
                                        name="group_ids[]"
                                        value="{{ $group->id }}"
                                        @checked(in_array((int) $group->id, $selectedGroupIds, true))
                                        x-model.number="selectedGroups"
                                        aria-labelledby="group-{{ $group->id }}-name"
                                        aria-describedby="group-{{ $group->id }}-details"
                                        class="mt-1 h-4 w-4 shrink-0 rounded-md border-slate-500 text-acode-tech focus:ring-acode-tech"
                                    >
                                    <span class="min-w-0 flex-1">
                                        <span id="group-{{ $group->id }}-name" class="block break-words text-sm font-semibold text-acode-primary">{{ $group->name }}</span>
                                        <span id="group-{{ $group->id }}-details" class="mt-0.5 block space-y-1 text-xs text-slate-600">
                                            <span class="block break-words">{{ $group->subject ?: 'Sin asignatura' }} · Folio <span class="font-mono font-semibold">{{ $group->access_folio }}</span></span>
                                            <span class="block">
                                                {{ (int) ($group->students_count ?? 0) }} {{ (int) ($group->students_count ?? 0) === 1 ? 'alumno inscrito' : 'alumnos inscritos' }}
                                                @php($periodCount = count($examGroupScheduling[$group->id]['periods']))
                                                @if ($periodCount > 0)
                                                    · {{ $periodCount }} {{ $periodCount === 1 ? 'periodo disponible' : 'periodos disponibles' }}
                                                @endif
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            @empty
                                <div class="col-span-full">
                                    <x-empty-state
                                        icon="userGroup"
                                        title="Sin grupos escolares abiertos"
                                        message="Crea un grupo escolar para asignarlo a este examen."
                                    >
                                        <x-button :href="route('groups.create')" variant="secondary" size="sm" data-cancel-form="create-exam-form">
                                            <x-icon name="add" class="w-4 h-4" />
                                            Crear grupo
                                        </x-button>
                                    </x-empty-state>
                                </div>
                            @endforelse
                        </div>
                        @if ($groupError)
                            <p id="group-assignments-error" class="mt-1 text-xs text-acode-danger">{{ $groupError }}</p>
                        @endif
                    </fieldset>
                </x-card>
            </div>

            {{-- Tarjeta 3: Ventana de aplicación --}}
            <x-card title="3. Ventana de aplicación" padding="p-4 sm:p-5">
                <div class="space-y-4">
                    <div id="application-window-help" class="space-y-1 text-xs text-slate-600">
                        <p>{{ \App\Models\Exam::timezoneLabel() }}</p>
                        <p>Al cierre dejan de admitirse nuevos intentos. Quienes ya comenzaron conservan el tiempo de su intento.</p>
                    </div>
                    <div x-show="selectedGroups.length > 0" x-cloak
                         class="rounded-lg border border-acode-tech/25 bg-acode-surface px-4 py-3">
                        <div x-show="suggestedWindow"
                             class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0 space-y-1">
                                <p class="text-sm font-semibold text-acode-primary">Ventana sugerida por los periodos</p>
                                <p class="text-sm text-slate-700">
                                    <span x-text="formatWindowDate(suggestedWindow?.startsAt)"></span>
                                    <span> a </span>
                                    <span x-text="formatWindowDate(suggestedWindow?.endsAt)"></span>
                                </p>
                                <p class="break-words text-xs text-slate-600" x-text="suggestedWindow?.periodNames"></p>
                                <p x-show="calendars.length < selectedGroups.length" class="text-xs text-slate-600">
                                    La sugerencia usa solo los grupos con periodos vigentes o futuros.
                                </p>
                            </div>
                            <x-button type="button" variant="secondary" size="sm" x-on:click="applyPeriodWindow()">
                                Usar fechas sugeridas
                            </x-button>
                        </div>
                        <p x-show="!suggestedWindow" class="text-sm text-slate-700" x-text="periodAdvice"></p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.group name="starts_at" label="Fecha y hora de inicio" :required="true" accessible>
                            <x-form.input
                                type="datetime-local"
                                name="starts_at"
                                :value="$defaultStartsAt"
                                :min="$minimumStart"
                                x-bind:min="minimumStart"
                                x-model="startsAt"
                                x-on:focus="refreshMinimumStart()"
                                x-on:change="announceWindow('Inicio', $event.target.value)"
                                required
                                accessible
                                :aria-describedby="$errors->has('starts_at') ? 'application-window-help starts_at-error' : 'application-window-help starts-at-limit'"
                                class="scroll-mt-24"
                            />
                            @unless ($errors->has('starts_at'))
                                <p id="starts-at-limit" x-show="startsInPast" x-cloak class="text-xs text-acode-danger-dark">
                                    El inicio debe ser a partir de la fecha y hora actuales.
                                </p>
                            @endunless
                        </x-form.group>

                        <x-form.group name="ends_at" label="Fecha y hora de cierre" :required="true" accessible>
                            <x-form.input
                                type="datetime-local"
                                name="ends_at"
                                :value="$defaultEndsAt"
                                :min="$minimumEnd"
                                x-bind:min="minimumEnd"
                                x-model="endsAt"
                                x-on:focus="refreshMinimumStart()"
                                x-on:change="announceWindow('Cierre', $event.target.value)"
                                required
                                accessible
                                :aria-describedby="$errors->has('ends_at') ? 'application-window-help ends_at-error' : 'application-window-help ends-at-limit'"
                                class="scroll-mt-24"
                            />
                            @unless ($errors->has('ends_at'))
                                <p id="ends-at-limit" x-show="endsTooEarly" x-cloak class="text-xs text-acode-danger-dark">
                                    El cierre debe ser posterior al inicio y a la fecha y hora actuales.
                                </p>
                            @endunless
                        </x-form.group>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="mr-1 text-xs font-semibold text-slate-600">Ajustar el cierre desde el inicio</span>
                        @foreach ([1 => '+1 hora', 2 => '+2 horas', 24 => '+24 horas', 168 => '+7 días'] as $hours => $presetLabel)
                            <x-button type="button" variant="neutral" size="sm" x-on:click="applyDurationPreset({{ $hours }})" ::disabled="!startsAt || startsInPast">
                                {{ $presetLabel }}
                            </x-button>
                        @endforeach
                    </div>
                </div>
            </x-card>

            {{-- Tarjeta 4: Escala, visibilidad y seguridad --}}
            <x-card title="4. Escala, visibilidad y seguridad" padding="p-4 sm:p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.group name="grading_scale" label="Escala de calificación" :required="true" accessible>
                        <x-form.select name="grading_scale" x-model="gradingScale" x-on:change="scaleEdited = true"
                                       :aria-describedby="$errors->has('grading_scale') ? 'grading-scale-help scale-advice grading_scale-error' : 'grading-scale-help scale-advice'"
                                       required accessible class="scroll-mt-24">
                            <option value="0-10" @selected($defaultScale === '0-10')>Escala 0 a 10</option>
                            <option value="0-100" @selected($defaultScale === '0-100')>Escala 0 a 100</option>
                        </x-form.select>
                        <p id="grading-scale-help" class="text-xs text-slate-600"
                           x-text="gradingScale === '0-10' ? 'El puntaje sobre 100 se divide entre 10 al calificar.' : 'El puntaje sobre 100 se conserva como calificación final.'"></p>
                        <p id="scale-advice" class="text-xs font-medium text-acode-primary" x-show="scaleAdvice" x-cloak
                           role="status" x-text="scaleAdvice"></p>
                    </x-form.group>

                    <x-form.group name="result_visibility_mode" label="Visibilidad de resultados para el estudiante" :required="true" accessible>
                        <x-form.select name="result_visibility_mode" x-model="visibilityMode"
                                       :aria-describedby="$errors->has('result_visibility_mode') ? 'visibility-help result_visibility_mode-error' : 'visibility-help'"
                                       required accessible class="scroll-mt-24">
                            @foreach ($visibilityModes as $value => $mode)
                                <option value="{{ $value }}" @selected($defaultVisibility === $value)>{{ $mode['title'] }}</option>
                            @endforeach
                        </x-form.select>
                        <p id="visibility-help" class="text-xs text-slate-600" x-text="visibilityModes[visibilityMode]?.desc"></p>
                    </x-form.group>

                    <x-form.group name="randomize_question_order" label="Orden de preguntas" accessible>
                        <x-form.select name="randomize_question_order" x-model="randomizeOrder"
                                       :aria-describedby="$errors->has('randomize_question_order') ? 'order-help randomize_question_order-error' : 'order-help'"
                                       accessible class="scroll-mt-24">
                            <option value="1" @selected($defaultRandomize === '1')>Aleatorio</option>
                            <option value="0" @selected($defaultRandomize === '0')>Fijo por apartados</option>
                        </x-form.select>
                        <p id="order-help" class="text-xs text-slate-600"
                           x-text="randomizeOrder === '1'
                               ? 'Mezcla las preguntas para cada estudiante y conserva el orden de los apartados.'
                               : 'Conserva el orden de los apartados y de las preguntas en cada uno.'"></p>
                    </x-form.group>

                    <x-form.group name="max_allowed_incidents" label="Incidencias anti-trampa permitidas" :required="true"
                                  help-text="Al superar este límite por cambio de pestaña o aplicación, el intento se cancela. La supervisión funciona sin cámara ni biometría."
                                  accessible>
                        <x-form.input
                            numeric="integer"
                            name="max_allowed_incidents"
                            :value="$defaultIncidents"
                            min="0"
                            :max="\App\Models\Exam::MAX_ALLOWED_INCIDENTS"
                            x-model="maxIncidents"
                            required
                            accessible
                            :aria-describedby="$errors->has('max_allowed_incidents') ? 'max_allowed_incidents-help max_allowed_incidents-error' : 'max_allowed_incidents-help'"
                            class="scroll-mt-24"
                        />
                    </x-form.group>
                </div>
            </x-card>

            <x-card padding="p-4 sm:p-5">
                <details class="group">
                    <summary class="flex min-h-8 cursor-pointer list-none items-center justify-between gap-2 rounded-md text-sm font-semibold text-acode-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech [&::-webkit-details-marker]:hidden">
                        Resumen del examen
                        <x-icon name="chevronDown" class="h-4 w-4 text-acode-tech transition-transform group-open:rotate-180 motion-reduce:transition-none" />
                    </summary>
                    <x-summary-list class="mt-4">
                        <x-summary-item label="Título" compact>
                            <x-slot:valueSlot><span x-text="title.trim() || 'Sin título capturado'"></span></x-slot:valueSlot>
                        </x-summary-item>
                        <x-summary-item label="Grupos asignados" compact>
                            <x-slot:valueSlot><span x-text="groupCountLabel"></span></x-slot:valueSlot>
                        </x-summary-item>
                        <x-summary-item label="Inicio" compact>
                            <x-slot:valueSlot><span x-text="formatWindowDate(startsAt)"></span></x-slot:valueSlot>
                        </x-summary-item>
                        <x-summary-item label="Cierre" compact>
                            <x-slot:valueSlot><span x-text="formatWindowDate(endsAt)"></span></x-slot:valueSlot>
                        </x-summary-item>
                        <x-summary-item label="Escala" compact>
                            <x-slot:valueSlot><span x-text="scaleLabel"></span></x-slot:valueSlot>
                        </x-summary-item>
                        <x-summary-item label="Visibilidad" compact>
                            <x-slot:valueSlot><span x-text="visibilityModes[visibilityMode]?.title"></span></x-slot:valueSlot>
                        </x-summary-item>
                        <x-summary-item label="Orden" compact>
                            <x-slot:valueSlot><span x-text="randomizeOrder === '1' ? 'Aleatorio' : 'Fijo por apartados'"></span></x-slot:valueSlot>
                        </x-summary-item>
                        <x-summary-item label="Incidencias anti-trampa permitidas" compact>
                            <x-slot:valueSlot><span x-text="incidentCountLabel"></span></x-slot:valueSlot>
                        </x-summary-item>
                    </x-summary-list>
                </details>

                <x-slot:footer>
                    <div class="flex w-full flex-wrap items-center gap-3">
                        <p id="create-exam-next-step" class="w-full text-sm text-slate-600 lg:w-auto lg:flex-1">
                            Al guardar el borrador, pasarás a la etapa 2 para armar los reactivos.
                        </p>
                        <x-unsaved-changes form-id="create-exam-form" :cancel-url="route('exams.index')" />
                        <x-button type="submit" variant="primary" size="md" loading-when="saving" ::disabled="saving"
                                  aria-describedby="create-exam-next-step" class="w-full sm:w-auto">
                            <x-icon name="save" class="w-4 h-4" x-show="!saving" />
                            <span x-text="saving ? 'Guardando…' : 'Crear borrador y armar reactivos'">Crear borrador y armar reactivos</span>
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>

            <p class="sr-only" role="status" x-text="windowAnnouncement"></p>
            <p class="sr-only" role="status" x-text="saving ? 'Guardando el borrador…' : ''"></p>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/exam-create.js') }}"></script>
@endpush
