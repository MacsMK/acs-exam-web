{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/edit.blade.php (MPW-V2 §2.4, §5.2.2, §6.3, §7.3, §7.8, §8.4, §8.7, Tabla 20, Tabla 31, RF-BAN-007, RF-BAN-010) --}}
@extends('layouts.app')

@section('title', 'Editar Reactivo · ' . $question->title)

@section('content')
    @php
        $rawOptions = old('options');
        if ($rawOptions === null) {
            $rawOptions = collect($question->questionable->options_payload ?? [])->map(function ($item) {
                return is_array($item) ? ($item['text'] ?? '') : (string) $item;
            })->values()->all();
        }
        if (empty($rawOptions)) {
            $rawOptions = ['', ''];
        }
        $initialCorrectOption = (int) old('correct_option_index', $question->questionable->correct_option_index ?? 0);

        $oldConcepts = old('concepts');
        $oldDefinitions = old('definitions');
        if ($oldConcepts !== null && $oldDefinitions !== null) {
            $pairsData = collect($oldConcepts)->values()->map(function ($c, $i) use ($oldDefinitions) {
                return ['concept' => (string) $c, 'definition' => (string) ($oldDefinitions[$i] ?? '')];
            })->values()->all();
        } else {
            $leftMap = collect($question->questionable->left_column_payload ?? [])->keyBy('id');
            $rightMap = collect($question->questionable->right_column_payload ?? [])->keyBy('id');
            $pairsData = collect($question->questionable->pairs_payload ?? [])->map(function ($pair) use ($leftMap, $rightMap) {
                $l = $leftMap->get($pair['left_id'] ?? '');
                $r = $rightMap->get($pair['right_id'] ?? '');
                return [
                    'concept' => is_array($l) ? ($l['text'] ?? '') : (string) ($l ?? ''),
                    'definition' => is_array($r) ? ($r['text'] ?? '') : (string) ($r ?? ''),
                ];
            })->values()->all();
        }
        if (empty($pairsData)) {
            $pairsData = [['concept' => '', 'definition' => ''], ['concept' => '', 'definition' => '']];
        }

        $initialIsFindError = (bool) old('is_find_error', $question->questionable->is_find_error ?? false);
    @endphp

    <div
        class="max-w-4xl mx-auto space-y-6"
        x-data="{
            options: {{ json_encode($rawOptions) }},
            correctOption: {{ $initialCorrectOption }},
            pairs: {{ json_encode($pairsData) }},
            isFindError: {{ $initialIsFindError ? 'true' : 'false' }},
            defaultTime: {{ (int) old('default_time_seconds', $question->default_time_seconds ?? 60) }},
            addOption() {
                if (this.options.length < 6) this.options.push('');
            },
            removeOption(i) {
                if (this.options.length > 2) {
                    this.options.splice(i, 1);
                    if (this.correctOption >= this.options.length) this.correctOption = this.options.length - 1;
                }
            },
            addPair() {
                if (this.pairs.length < 8) this.pairs.push({ concept: '', definition: '' });
            },
            removePair(i) {
                if (this.pairs.length > 2) this.pairs.splice(i, 1);
            },
            setTime(s) {
                this.defaultTime = s;
            }
        }"
    >
        {{-- Barra de Navegación y Breadcrumb --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <nav class="flex items-center gap-2 text-xs text-slate-500 select-none" aria-label="Migas de pan">
                <a href="{{ route('questions.index') }}" class="hover:text-acode-tech transition flex items-center gap-1 font-medium">
                    <x-icon name="arrowLeft" class="w-3.5 h-3.5" />
                    <span>Banco de reactivos</span>
                </a>
                <span class="text-slate-300">/</span>
                <a href="{{ route('questions.show', $question) }}" class="hover:text-acode-tech transition truncate max-w-[150px] sm:max-w-xs">
                    {{ $question->title }}
                </a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-700">Editar</span>
            </nav>

            <x-button :href="route('questions.show', $question)" variant="neutral" size="sm">
                Cancelar
            </x-button>
        </div>

        {{-- Encabezado Institucional --}}
        <div>
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Editar Reactivo</h1>
            <p class="text-xs text-slate-500 mt-0.5">Modificación de enunciados, alternativas de evaluación y parámetros técnicos.</p>
        </div>

        {{-- Banner Institucional de Integridad Histórica (ADR-003 / RF-BAN-010) --}}
        @if ($isLinkedToExams)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-900 shadow-xs flex items-start gap-3">
                <div class="p-2 rounded-xl bg-amber-100 text-amber-700 shrink-0">
                    <x-icon name="warning" class="w-5 h-5" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-amber-950">Aviso de Integridad Histórica (Copy-on-Write)</h3>
                    <p class="text-xs text-amber-900 leading-relaxed">
                        Este reactivo ya está asignado a exámenes. Al guardar tus cambios se creará automáticamente una nueva versión (v2) para no alterar las evaluaciones históricas de tus estudiantes.
                    </p>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-blue-200 bg-blue-50/80 p-4 text-blue-900 shadow-xs flex items-start gap-3">
                <div class="p-2 rounded-xl bg-blue-100 text-acode-tech shrink-0">
                    <x-icon name="shield" class="w-5 h-5" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-blue-950">Edición directa habilitada</h3>
                    <p class="text-xs text-blue-800 leading-relaxed">
                        Edición directa: este reactivo aún no se ha usado en exámenes y se actualizará en sitio.
                    </p>
                </div>
            </div>
        @endif

        {{-- Formulario Principal de Actualización --}}
        <form
            method="POST"
            action="{{ route('questions.update', $question) }}"
            class="space-y-6"
            @keydown.enter="$event.target.tagName === 'TEXTAREA' ? null : $event.preventDefault()"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="question_type" value="{{ $question->question_type }}">

            {{-- Tarjeta 1: Planteamiento y Clasificación --}}
            <x-card>
                <div class="space-y-5">
                    <div class="pb-3 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-acode-primary">1. Planteamiento e Identificación</h2>
                            <p class="text-xs text-slate-500">Datos informativos y redacción principal de la pregunta.</p>
                        </div>

                        {{-- Badge fijo de Tipo de Reactivo --}}
                        @switch($question->question_type)
                            @case('multiple_choice')
                                <x-badge variant="info">Opción múltiple</x-badge>
                                @break
                            @case('matching')
                                <x-badge variant="accent">Relación de columnas</x-badge>
                                @break
                            @case('open')
                                <x-badge variant="warning">Pregunta abierta</x-badge>
                                @break
                            @case('code')
                                <x-badge variant="primary">Código fuente</x-badge>
                                @break
                            @default
                                <x-badge variant="neutral">{{ $question->question_type }}</x-badge>
                        @endswitch
                    </div>

                    {{-- Título --}}
                    <x-form.group
                        name="title"
                        label="Título identificador del reactivo"
                        helpText="Nombre breve para localizarlo en tu banco docente."
                        required
                    >
                        <x-form.input
                            type="text"
                            name="title"
                            value="{{ old('title', $question->title) }}"
                            required
                            maxlength="150"
                            placeholder="Ej. Principios SOLID: Responsabilidad Única"
                            prefixIcon="edit"
                        />
                    </x-form.group>

                    {{-- Materia --}}
                    <x-form.group
                        name="subject_tag"
                        label="Materia / Categoría temática"
                        helpText="Opcional. Etiqueta para clasificar y filtrar en apartados de examen."
                    >
                        <x-form.input
                            type="text"
                            name="subject_tag"
                            value="{{ old('subject_tag', $question->subject_tag) }}"
                            maxlength="100"
                            placeholder="Ej. Auditoría de Sistemas, Programación Web"
                            prefixIcon="shield"
                        />
                    </x-form.group>

                    {{-- Enunciado Principal (Stem) --}}
                    <x-form.group
                        name="stem"
                        label="Enunciado o planteamiento principal"
                        helpText="Texto que visualizará el alumno al resolver la pregunta."
                        required
                    >
                        <x-form.textarea
                            name="stem"
                            rows="4"
                            required
                            placeholder="Escribe el planteamiento formal de la pregunta…"
                        >{{ old('stem', $question->stem) }}</x-form.textarea>
                    </x-form.group>
                </div>
            </x-card>

            {{-- Tarjeta 2: Contenido Específico según question_type --}}
            <x-card>
                <div class="space-y-5">
                    <div class="pb-3 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-acode-primary">2. Contenido y Solución Canónica</h2>
                            <p class="text-xs text-slate-500">Alternativas de respuesta y criterios específicos de evaluación.</p>
                        </div>
                    </div>

                    {{-- 2.1. Modo Opción Múltiple --}}
                    @if ($question->question_type === 'multiple_choice')
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                                    Alternativas (<span x-text="options.length"></span> de 6) — Marca la respuesta correcta
                                </label>
                                <x-button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    @click="addOption()"
                                    x-bind:disabled="options.length >= 6"
                                >
                                    <x-icon name="add" class="w-3.5 h-3.5 mr-1" />
                                    Crear opción
                                </x-button>
                            </div>

                            <div class="space-y-2.5">
                                <template x-for="(option, i) in options" :key="i">
                                    <div
                                        class="flex items-center gap-2.5 p-2.5 rounded-xl border transition"
                                        :class="correctOption === i
                                            ? 'bg-emerald-50/60 border-emerald-300 ring-1 ring-emerald-300/40'
                                            : 'bg-acode-surface/50 border-slate-200'"
                                    >
                                        <label class="flex items-center gap-1.5 cursor-pointer shrink-0" :title="'Marcar opción ' + (i + 1) + ' como correcta'">
                                            <input
                                                type="radio"
                                                name="correct_option_index"
                                                :value="i"
                                                x-model.number="correctOption"
                                                class="text-emerald-600 focus:ring-emerald-500 h-4 w-4"
                                            />
                                            <span
                                                class="text-[11px] font-bold px-2 py-0.5 rounded"
                                                :class="correctOption === i ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700'"
                                                x-text="correctOption === i ? 'Correcta' : 'Opción ' + (i + 1)"
                                            ></span>
                                        </label>

                                        <input
                                            type="text"
                                            :name="'options[' + i + ']'"
                                            x-model="options[i]"
                                            class="flex-1 h-9 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech bg-white"
                                            :placeholder="'Texto de la opción ' + (i + 1) + '…'"
                                        />

                                        <button
                                            type="button"
                                            @click="removeOption(i)"
                                            x-show="options.length > 2"
                                            class="p-1.5 text-slate-400 hover:text-acode-danger hover:bg-red-50 rounded-lg transition"
                                            title="Eliminar opción"
                                        >
                                            <x-icon name="delete" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @endif

                    {{-- 2.2. Modo Relación de Columnas --}}
                    @if ($question->question_type === 'matching')
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                                    Pares Concepto ↔ Definición (<span x-text="pairs.length"></span> de 8)
                                </label>
                                <x-button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    @click="addPair()"
                                    x-bind:disabled="pairs.length >= 8"
                                >
                                    <x-icon name="add" class="w-3.5 h-3.5 mr-1" />
                                    Crear par
                                </x-button>
                            </div>

                            <div class="space-y-2.5">
                                <template x-for="(pair, i) in pairs" :key="i">
                                    <div class="flex items-center gap-2 p-2.5 bg-acode-surface/50 rounded-xl border border-slate-200">
                                        <span class="w-5 text-center text-xs font-bold text-slate-400" x-text="(i + 1) + '.'"></span>
                                        <input
                                            type="text"
                                            :name="'concepts[' + i + ']'"
                                            x-model="pair.concept"
                                            placeholder="Concepto (Columna izquierda)"
                                            class="flex-1 h-9 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech bg-white"
                                        />
                                        <span class="text-acode-accent font-bold px-1 select-none">↔</span>
                                        <input
                                            type="text"
                                            :name="'definitions[' + i + ']'"
                                            x-model="pair.definition"
                                            placeholder="Definición (Columna derecha)"
                                            class="flex-1 h-9 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech bg-white"
                                        />
                                        <button
                                            type="button"
                                            @click="removePair(i)"
                                            x-show="pairs.length > 2"
                                            class="p-1.5 text-slate-400 hover:text-acode-danger hover:bg-red-50 rounded-lg transition"
                                            title="Eliminar par"
                                        >
                                            <x-icon name="delete" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @endif

                    {{-- 2.3. Modo Pregunta Abierta --}}
                    @if ($question->question_type === 'open')
                        <div class="space-y-4">
                            <x-form.group
                                name="reference_answer"
                                label="Respuesta esperada o modelo de referencia"
                                helpText="Patrón de comparación semántica analizado por el evaluador asistido por IA."
                                required
                            >
                                <x-form.textarea
                                    name="reference_answer"
                                    rows="4"
                                    required
                                    placeholder="Redacta la solución canónica o los puntos clave que debe contener la respuesta del alumno…"
                                >{{ old('reference_answer', $question->questionable->reference_answer ?? '') }}</x-form.textarea>
                            </x-form.group>

                            <x-form.group
                                name="evaluation_rubric"
                                label="Rúbrica de evaluación complementaria (opcional)"
                                helpText="Ponderaciones, criterios de penalización o términos obligatorios."
                            >
                                <x-form.textarea
                                    name="evaluation_rubric"
                                    rows="2"
                                    placeholder="Criterios cualitativos adicionales para recotejo…"
                                >{{ old('evaluation_rubric', $question->questionable->evaluation_rubric ?? '') }}</x-form.textarea>
                            </x-form.group>
                        </div>
                    @endif

                    {{-- 2.4. Modo Código Fuente --}}
                    @if ($question->question_type === 'code')
                        <div class="space-y-4">
                            {{-- Selector de Modalidad --}}
                            <div>
                                <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">
                                    Modalidad de Código
                                </label>
                                <input type="hidden" name="is_find_error" :value="isFindError ? '1' : '0'">

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div
                                        @click="isFindError = false"
                                        class="p-3 rounded-xl border cursor-pointer transition select-none flex items-center justify-between"
                                        :class="!isFindError
                                            ? 'border-acode-tech bg-acode-surface/80 ring-2 ring-acode-tech/50'
                                            : 'border-slate-200 bg-white hover:border-slate-300'"
                                    >
                                        <div>
                                            <p class="font-bold text-xs text-slate-800">Modo Desarrollo</p>
                                            <p class="text-[11px] text-slate-500">Escribir solución desde cero</p>
                                        </div>
                                        <x-badge variant="info">Estándar</x-badge>
                                    </div>

                                    <div
                                        @click="isFindError = true"
                                        class="p-3 rounded-xl border cursor-pointer transition select-none flex items-center justify-between"
                                        :class="isFindError
                                            ? 'border-amber-400 bg-amber-50/70 ring-2 ring-amber-400/50'
                                            : 'border-slate-200 bg-white hover:border-slate-300'"
                                    >
                                        <div>
                                            <p class="font-bold text-xs text-slate-800">Modo Auditoría / Debugging</p>
                                            <p class="text-[11px] text-slate-500">Encontrar y corregir el error</p>
                                        </div>
                                        <x-badge variant="warning">Corregir bug</x-badge>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <x-form.group name="programming_language" label="Lenguaje de programación" required>
                                    <x-form.input
                                        type="text"
                                        name="programming_language"
                                        value="{{ old('programming_language', $question->questionable->programming_language ?? 'PHP') }}"
                                        maxlength="40"
                                        required
                                        placeholder="PHP"
                                    />
                                </x-form.group>

                                <x-form.group name="instructions" label="Instrucciones técnicas de desarrollo" required>
                                    <x-form.input
                                        type="text"
                                        name="instructions"
                                        value="{{ old('instructions', $question->questionable->instructions ?? '') }}"
                                        required
                                        placeholder="Ej. Resuelve sin librerías externas…"
                                    />
                                </x-form.group>
                            </div>

                            {{-- Editor Starter Code --}}
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-bold text-slate-800">
                                        <span x-show="!isFindError">Código inicial o plantilla base (opcional)</span>
                                        <span x-show="isFindError" class="text-amber-800">Código defectuoso para auditoría del alumno (obligatorio)</span>
                                    </label>
                                    <span class="text-[10px] font-mono text-slate-400">Fira Code</span>
                                </div>
                                <div class="rounded-xl overflow-hidden border border-slate-700 bg-slate-900">
                                    <div class="bg-slate-800 px-3 py-1 border-b border-slate-700 text-[11px] font-mono text-slate-300 flex items-center justify-between">
                                        <span x-text="isFindError ? 'codigo_con_error.php' : 'plantilla_inicial.php'"></span>
                                        <span class="text-[9px] text-slate-400 uppercase">Editor Alumno</span>
                                    </div>
                                    <textarea
                                        name="starter_code"
                                        rows="4"
                                        placeholder="// Escribe aquí el código base o defectuoso…"
                                        class="w-full bg-slate-900 text-slate-100 font-mono text-xs p-3 focus:outline-none focus:ring-1 focus:ring-acode-tech border-0"
                                    >{{ old('starter_code', $question->questionable->starter_code ?? '') }}</textarea>
                                </div>
                            </div>

                            {{-- Editor Reference Solution --}}
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-bold text-slate-800">
                                        Solución canónica de referencia (código funcional)
                                    </label>
                                    <span class="text-[10px] font-mono text-emerald-400">Solución Correcta</span>
                                </div>
                                <div class="rounded-xl overflow-hidden border border-slate-700 bg-slate-900">
                                    <div class="bg-slate-800 px-3 py-1 border-b border-slate-700 text-[11px] font-mono text-slate-300 flex items-center justify-between">
                                        <span>solucion_referencia.php</span>
                                        <span class="text-[9px] text-emerald-400 uppercase">Patrón Evaluación</span>
                                    </div>
                                    <textarea
                                        name="reference_solution"
                                        rows="4"
                                        required
                                        placeholder="// Código funcional de referencia…"
                                        class="w-full bg-slate-900 text-slate-100 font-mono text-xs p-3 focus:outline-none focus:ring-1 focus:ring-acode-tech border-0"
                                    >{{ old('reference_solution', $question->questionable->reference_solution ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- Tarjeta 3: Ponderación y Cronómetro --}}
            <x-card>
                <div class="space-y-5">
                    <div class="pb-3 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-acode-primary">3. Ponderación y Tiempo Límite</h2>
                            <p class="text-xs text-slate-500">Valores predeterminados que heredarán los exámenes al agregar este reactivo.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-form.group
                            name="default_points"
                            label="Puntos nominales"
                            helpText="Ponderación que aporta a la calificación del examen."
                            required
                        >
                            <x-form.input
                                type="number"
                                step="0.01"
                                min="0"
                                name="default_points"
                                value="{{ old('default_points', $question->default_points) }}"
                                required
                                prefixIcon="reportBar"
                            />
                        </x-form.group>

                        <x-form.group
                            name="default_time_seconds"
                            label="Tiempo límite (segundos)"
                            helpText="Cronómetro individual asignado para este reactivo."
                            required
                        >
                            <x-form.input
                                type="number"
                                min="1"
                                name="default_time_seconds"
                                x-model.number="defaultTime"
                                required
                                prefixIcon="clock"
                            />
                        </x-form.group>
                    </div>

                    {{-- Presets rápidos de tiempo --}}
                    <div class="flex items-center gap-2 pt-1 text-xs text-slate-500">
                        <span class="font-medium">Presets recomendados:</span>
                        <button type="button" @click="setTime(60)" class="px-2 py-0.5 rounded border border-slate-200 hover:bg-slate-100 transition" :class="defaultTime === 60 ? 'bg-acode-primary text-white border-acode-primary' : 'bg-white'">60 s</button>
                        <button type="button" @click="setTime(120)" class="px-2 py-0.5 rounded border border-slate-200 hover:bg-slate-100 transition" :class="defaultTime === 120 ? 'bg-acode-primary text-white border-acode-primary' : 'bg-white'">120 s</button>
                        <button type="button" @click="setTime(180)" class="px-2 py-0.5 rounded border border-slate-200 hover:bg-slate-100 transition" :class="defaultTime === 180 ? 'bg-acode-primary text-white border-acode-primary' : 'bg-white'">180 s</button>
                        <button type="button" @click="setTime(600)" class="px-2 py-0.5 rounded border border-slate-200 hover:bg-slate-100 transition" :class="defaultTime === 600 ? 'bg-acode-primary text-white border-acode-primary' : 'bg-white'">600 s (10 min)</button>
                    </div>
                </div>
            </x-card>

            {{-- Barra Inferior de Acciones --}}
            <div class="flex items-center justify-between pt-2">
                <x-button :href="route('questions.show', $question)" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4 mr-1.5" />
                    Cancelar
                </x-button>

                <x-button type="submit" variant="primary" size="md">
                    <x-icon name="save" class="w-4 h-4 mr-1.5" />
                    Guardar cambios
                </x-button>
            </div>
        </form>
    </div>
@endsection
