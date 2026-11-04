{{--
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     create.blade.php
 * Ubicación:   resources/views/questions/create.blade.php
 * Módulo:      Módulo 5 - Banco de Preguntas (MPW-V2 §6.3, §6.4, §7.3, §7.8, §8.5)
 * Descripción: Asistente guiado por pasos (Wizard/Stepper) para la autoría
 *              de reactivos polimórficos. Cumple con la paleta de MC-V2,
 *              los 18 verbos de MPW-V2 Tabla 10, y elimina toda sobrecarga
 *              de texto distractora y colisiones cruzadas en el DOM.
 * Organización: A-Code Solutions
 * ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Crear Reactivo')

@section('content')
    <div
        class="max-w-4xl mx-auto py-2"
        x-data="{
            step: {{ $errors->any() ? 3 : 1 }},
            type: '{{ old('question_type', 'multiple_choice') }}',
            isFindError: {{ old('is_find_error') ? 'true' : 'false' }},
            title: {{ json_encode((string) old('title', '')) }},
            stem: {{ json_encode((string) old('stem', '')) }},
            subjectTag: {{ json_encode((string) old('subject_tag', '')) }},
            defaultPoints: '{{ old('default_points', '1.00') }}',
            defaultTime: {{ (int) old('default_time_seconds', 60) }},
            userModifiedTime: {{ old('default_time_seconds') ? 'true' : 'false' }},
            options: {{ json_encode(collect(old('options', ['', '', '']))->values()->all()) }},
            correctOption: {{ (int) old('correct_option_index', 0) }},
            pairs: {{ json_encode(
                old('concepts') && old('definitions')
                    ? collect(old('concepts'))->values()->map(fn($c, $i) => ['concept' => $c, 'definition' => old('definitions')[$i] ?? ''])->values()->all()
                    : [['concept' => '', 'definition' => ''], ['concept' => '', 'definition' => '']]
            ) }},
            setType(newType) {
                this.type = newType;
                if (!this.userModifiedTime) {
                    if (newType === 'multiple_choice') this.defaultTime = 60;
                    else if (newType === 'matching') this.defaultTime = 120;
                    else if (newType === 'open') this.defaultTime = 180;
                    else if (newType === 'code') this.defaultTime = 600;
                }
            },
            setTime(s) {
                this.defaultTime = s;
                this.userModifiedTime = true;
            },
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
            canAdvanceStep1() {
                return this.title.trim().length > 0 && this.stem.trim().length > 0;
            },
            goToStep(target) {
                if (target === 2 && !this.canAdvanceStep1()) {
                    alert('Por favor completa el título y el planteamiento principal antes de continuar.');
                    return;
                }
                this.step = target;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }"
    >
        {{-- Encabezado Principal --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Crear Reactivo</h1>
                <p class="text-xs text-slate-500 mt-0.5">Asistente por etapas para el alta de preguntas en el banco institucional.</p>
            </div>
            <div class="text-xs text-slate-500 font-medium bg-acode-surface px-3 py-1.5 rounded-lg border border-slate-200 self-start sm:self-auto">
                Etapa <span x-text="step" class="font-bold text-acode-tech"></span> de 3
            </div>
        </div>

        {{-- Barra de Progreso del Asistente (Stepper) --}}
        <div class="mb-6 bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between max-w-2xl mx-auto">
                {{-- Paso 1 --}}
                <div class="flex items-center gap-2.5 cursor-pointer select-none" @click="goToStep(1)">
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs transition"
                        :class="step === 1
                            ? 'bg-acode-primary text-white ring-4 ring-acode-primary/20 shadow-xs'
                            : (step > 1 ? 'bg-acode-accent text-white' : 'bg-acode-surface text-slate-500')"
                    >
                        <span x-show="step <= 1">1</span>
                        <x-icon name="check" class="w-4 h-4" x-show="step > 1" x-cloak />
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-slate-800 leading-none">Planteamiento</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Título y tipo</p>
                    </div>
                </div>

                {{-- Línea conectora 1 -> 2 --}}
                <div class="flex-1 h-0.5 mx-3 bg-slate-200 transition" :class="step > 1 ? 'bg-acode-accent' : 'bg-slate-200'"></div>

                {{-- Paso 2 --}}
                <div class="flex items-center gap-2.5 cursor-pointer select-none" @click="step > 2 ? goToStep(2) : null">
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs transition"
                        :class="step === 2
                            ? 'bg-acode-primary text-white ring-4 ring-acode-primary/20 shadow-xs'
                            : (step > 2 ? 'bg-acode-accent text-white' : 'bg-acode-surface text-slate-500')"
                    >
                        <span x-show="step <= 2">2</span>
                        <x-icon name="check" class="w-4 h-4" x-show="step > 2" x-cloak />
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-slate-800 leading-none">Contenido</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Respuestas</p>
                    </div>
                </div>

                {{-- Línea conectora 2 -> 3 --}}
                <div class="flex-1 h-0.5 mx-3 bg-slate-200 transition" :class="step > 2 ? 'bg-acode-accent' : 'bg-slate-200'"></div>

                {{-- Paso 3 --}}
                <div class="flex items-center gap-2.5 select-none">
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs transition"
                        :class="step === 3
                            ? 'bg-acode-primary text-white ring-4 ring-acode-primary/20 shadow-xs'
                            : 'bg-acode-surface text-slate-500'"
                    >
                        3
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-slate-800 leading-none">Evaluación</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Puntos y tiempo</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Formulario Principal con Bloqueo de Enter --}}
        <form
            method="POST"
            action="{{ route('questions.store') }}"
            @keydown.enter="$event.target.tagName === 'TEXTAREA' ? null : $event.preventDefault()"
        >
            @csrf
            <input type="hidden" name="question_type" :value="type">

            {{-- ============================================================ --}}
            {{-- ETAPA 1: Identificación, Enunciado y Selección de Tipo      --}}
            {{-- ============================================================ --}}
            <div x-show="step === 1" x-cloak class="space-y-6">
                <x-card>
                    <div class="space-y-5">
                        <div class="pb-3 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-bold text-acode-primary">Paso 1: Planteamiento y Tipo de Reactivo</h2>
                                <p class="text-xs text-slate-500">Define los datos de identificación y la modalidad de pregunta.</p>
                            </div>
                        </div>

                        {{-- Título identificador del reactivo --}}
                        <x-form.group
                            name="title"
                            label="Título identificador del reactivo"
                            helpText="Nombre breve para localizarlo en tu banco docente."
                            required
                        >
                            <x-form.input
                                type="text"
                                name="title"
                                x-model="title"
                                required
                                maxlength="150"
                                placeholder="Ej. Principios SOLID: Responsabilidad Única"
                                prefixIcon="edit"
                            />
                        </x-form.group>

                        {{-- Materia / Categoría temática --}}
                        <x-form.group
                            name="subject_tag"
                            label="Materia / Categoría temática"
                            helpText="Opcional. Etiqueta para clasificar y filtrar en apartados de examen."
                        >
                            <x-form.input
                                type="text"
                                name="subject_tag"
                                x-model="subjectTag"
                                maxlength="100"
                                placeholder="Ej. Auditoría de Sistemas, Programación Web"
                                prefixIcon="shield"
                            />
                        </x-form.group>

                        {{-- Enunciado o planteamiento principal --}}
                        <x-form.group
                            name="stem"
                            label="Enunciado o planteamiento principal"
                            helpText="Texto que visualizará el alumno al resolver la pregunta."
                            required
                        >
                            <x-form.textarea
                                name="stem"
                                rows="3"
                                x-model="stem"
                                required
                                placeholder="Escribe el planteamiento formal de la pregunta o caso de estudio…"
                            >{{ old('stem') }}</x-form.textarea>
                        </x-form.group>

                        {{-- Selector Visual de 4 Reactivos --}}
                        <div class="pt-2">
                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
                                Modalidad Pedagógica del Reactivo
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup">
                                {{-- 1. Opción Múltiple --}}
                                <div
                                    @click="setType('multiple_choice')"
                                    class="p-3.5 rounded-xl border cursor-pointer transition flex items-center justify-between select-none"
                                    :class="type === 'multiple_choice'
                                        ? 'border-acode-tech bg-acode-surface/80 ring-2 ring-acode-tech/50 shadow-xs'
                                        : 'border-slate-200 bg-white hover:border-slate-300'"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-lg flex items-center justify-center bg-blue-100 text-acode-tech">
                                            <x-icon name="check" class="w-4 h-4" />
                                        </span>
                                        <div>
                                            <p class="font-bold text-xs text-slate-800">Opción múltiple</p>
                                            <p class="text-[11px] text-slate-500">Alternativas cerradas (respuesta única)</p>
                                        </div>
                                    </div>
                                    <x-badge variant="info">Objetivo</x-badge>
                                </div>

                                {{-- 2. Relación de Columnas --}}
                                <div
                                    @click="setType('matching')"
                                    class="p-3.5 rounded-xl border cursor-pointer transition flex items-center justify-between select-none"
                                    :class="type === 'matching'
                                        ? 'border-acode-tech bg-acode-surface/80 ring-2 ring-acode-tech/50 shadow-xs'
                                        : 'border-slate-200 bg-white hover:border-slate-300'"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-lg flex items-center justify-center bg-teal-100 text-acode-accent">
                                            <x-icon name="userGroup" class="w-4 h-4" />
                                        </span>
                                        <div>
                                            <p class="font-bold text-xs text-slate-800">Relación de columnas</p>
                                            <p class="text-[11px] text-slate-500">Pares concepto ↔ definición</p>
                                        </div>
                                    </div>
                                    <x-badge variant="accent">Emparejamiento</x-badge>
                                </div>

                                {{-- 3. Pregunta Abierta --}}
                                <div
                                    @click="setType('open')"
                                    class="p-3.5 rounded-xl border cursor-pointer transition flex items-center justify-between select-none"
                                    :class="type === 'open'
                                        ? 'border-acode-tech bg-acode-surface/80 ring-2 ring-acode-tech/50 shadow-xs'
                                        : 'border-slate-200 bg-white hover:border-slate-300'"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-lg flex items-center justify-center bg-amber-100 text-amber-700">
                                            <x-icon name="edit" class="w-4 h-4" />
                                        </span>
                                        <div>
                                            <p class="font-bold text-xs text-slate-800">Pregunta abierta</p>
                                            <p class="text-[11px] text-slate-500">Evaluada por vector de similitud IA</p>
                                        </div>
                                    </div>
                                    <x-badge variant="warning">Evaluación IA</x-badge>
                                </div>

                                {{-- 4. Código Fuente --}}
                                <div
                                    @click="setType('code')"
                                    class="p-3.5 rounded-xl border cursor-pointer transition flex items-center justify-between select-none"
                                    :class="type === 'code'
                                        ? 'border-acode-tech bg-acode-surface/80 ring-2 ring-acode-tech/50 shadow-xs'
                                        : 'border-slate-200 bg-white hover:border-slate-300'"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-lg flex items-center justify-center bg-indigo-100 text-indigo-700">
                                            <x-icon name="code" class="w-4 h-4" />
                                        </span>
                                        <div>
                                            <p class="font-bold text-xs text-slate-800">Código de programación</p>
                                            <p class="text-[11px] text-slate-500">Desarrollo o depuración supervisada</p>
                                        </div>
                                    </div>
                                    <x-badge variant="primary">Supervisado</x-badge>
                                </div>
                            </div>
                        </div>

                        {{-- Botones de Navegación del Paso 1 --}}
                        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                            <x-button :href="route('questions.index')" variant="neutral" size="md">
                                Volver al listado
                            </x-button>

                            <x-button type="button" variant="primary" size="md" @click="goToStep(2)">
                                Continuar al paso 2 →
                            </x-button>
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- ============================================================ --}}
            {{-- ETAPA 2: Configuración del Contenido (Exclusivo por Tipo)    --}}
            {{-- ============================================================ --}}
            <div x-show="step === 2" x-cloak class="space-y-6">
                <x-card>
                    <div class="space-y-5">
                        <div class="pb-3 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-bold text-acode-primary">Paso 2: Contenido y Solución del Reactivo</h2>
                                <p class="text-xs text-slate-500">Configura las respuestas y criterios según el tipo seleccionado.</p>
                            </div>
                            <x-badge variant="primary" x-text="type === 'multiple_choice' ? 'Opción múltiple' : (type === 'matching' ? 'Relación de columnas' : (type === 'open' ? 'Pregunta abierta' : 'Código'))"></x-badge>
                        </div>

                        {{-- 2.1. Configuración de Opción Múltiple --}}
                        <div x-show="type === 'multiple_choice'" x-cloak class="space-y-4">
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
                                                :disabled="type !== 'multiple_choice'"
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
                                            :disabled="type !== 'multiple_choice'"
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

                        {{-- 2.2. Configuración de Relación de Columnas --}}
                        <div x-show="type === 'matching'" x-cloak class="space-y-4">
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
                                            :disabled="type !== 'matching'"
                                            placeholder="Concepto (Columna izquierda)"
                                            class="flex-1 h-9 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech bg-white"
                                        />
                                        <span class="text-acode-accent font-bold px-1 select-none">↔</span>
                                        <input
                                            type="text"
                                            :name="'definitions[' + i + ']'"
                                            x-model="pair.definition"
                                            :disabled="type !== 'matching'"
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
                            <p class="text-[11px] text-slate-400 italic">La columna de definiciones se desordena automáticamente en el examen para cada alumno.</p>
                        </div>

                        {{-- 2.3. Configuración de Pregunta Abierta --}}
                        <div x-show="type === 'open'" x-cloak class="space-y-4">
                            <x-form.group
                                name="reference_answer"
                                label="Respuesta esperada o modelo de referencia"
                                helpText="Patrón de comparación semántica analizado por el evaluador asistido."
                                required
                            >
                                <x-form.textarea
                                    name="reference_answer"
                                    rows="4"
                                    ::disabled="type !== 'open'"
                                    placeholder="Redacta la solución canónica o los puntos clave que debe contener la respuesta del alumno…"
                                >{{ old('reference_answer') }}</x-form.textarea>
                            </x-form.group>

                            <x-form.group
                                name="evaluation_rubric"
                                label="Rúbrica de evaluación complementaria (opcional)"
                                helpText="Ponderaciones, criterios de penalización o términos obligatorios."
                            >
                                <x-form.textarea
                                    name="evaluation_rubric"
                                    rows="2"
                                    ::disabled="type !== 'open'"
                                    placeholder="Criterios cualitativos adicionales para recotejo…"
                                >{{ old('evaluation_rubric') }}</x-form.textarea>
                            </x-form.group>
                        </div>

                        {{-- 2.4. Configuración de Código de Programación --}}
                        <div x-show="type === 'code'" x-cloak class="space-y-4">
                            {{-- Selector de Modalidad (Desarrollo vs Debugging) --}}
                            <div>
                                <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">
                                    Modalidad de Código
                                </label>
                                <input type="hidden" name="is_find_error" :value="isFindError ? '1' : '0'" :disabled="type !== 'code'">

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
                                        value="{{ old('programming_language', 'PHP') }}"
                                        maxlength="40"
                                        ::disabled="type !== 'code'"
                                        placeholder="PHP"
                                    />
                                </x-form.group>

                                <x-form.group name="instructions" label="Instrucciones técnicas de desarrollo" required>
                                    <x-form.input
                                        type="text"
                                        name="instructions"
                                        value="{{ old('instructions') }}"
                                        ::disabled="type !== 'code'"
                                        placeholder="Ej. Resuelve sin librerías externas…"
                                    />
                                </x-form.group>
                            </div>

                            {{-- Editor 1: Plantilla base o Código con error --}}
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
                                        :disabled="type !== 'code'"
                                        placeholder="// Escribe aquí el código base o defectuoso…"
                                        class="w-full bg-slate-900 text-slate-100 font-mono text-xs p-3 focus:outline-none focus:ring-1 focus:ring-acode-tech border-0"
                                    >{{ old('starter_code') }}</textarea>
                                </div>
                            </div>

                            {{-- Editor 2: Solución de Referencia --}}
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
                                        :disabled="type !== 'code'"
                                        placeholder="// Código funcional de referencia…"
                                        class="w-full bg-slate-900 text-slate-100 font-mono text-xs p-3 focus:outline-none focus:ring-1 focus:ring-acode-tech border-0"
                                    >{{ old('reference_solution') }}</textarea>
                                </div>
                            </div>
                        </div>

                        {{-- Botones de Navegación del Paso 2 --}}
                        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                            <x-button type="button" variant="neutral" size="md" @click="step = 1">
                                ← Volver al paso 1
                            </x-button>

                            <x-button type="button" variant="primary" size="md" @click="goToStep(3)">
                                Continuar al paso 3 →
                            </x-button>
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- ============================================================ --}}
            {{-- ETAPA 3: Ponderación, Cronómetro y Guardado Final            --}}
            {{-- ============================================================ --}}
            <div x-show="step === 3" x-cloak class="space-y-6">
                <x-card>
                    <div class="space-y-5">
                        <div class="pb-3 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-bold text-acode-primary">Paso 3: Ponderación y Guardado</h2>
                                <p class="text-xs text-slate-500">Asigna el puntaje, el tiempo asignado y confirma el reactivo.</p>
                            </div>
                        </div>

                        {{-- Resumen Compacto del Reactivo --}}
                        <div class="p-3.5 bg-acode-surface/60 border border-slate-200 rounded-xl space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800" x-text="title"></span>
                                <x-badge variant="info" x-text="type === 'multiple_choice' ? 'Opción múltiple' : (type === 'matching' ? 'Relación' : (type === 'open' ? 'Abierta' : 'Código'))"></x-badge>
                            </div>
                            <p class="text-xs text-slate-600 line-clamp-2" x-text="stem"></p>
                        </div>

                        {{-- Puntos y Tiempo --}}
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
                                    x-model="defaultPoints"
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
                                    @input="setTime($event.target.value)"
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

                        {{-- Botones de Navegación del Paso 3 --}}
                        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                            <x-button type="button" variant="neutral" size="md" @click="step = 2">
                                ← Volver al paso 2
                            </x-button>

                            <x-button type="submit" variant="primary" size="md">
                                <x-icon name="save" class="w-4 h-4 mr-1.5" />
                                Guardar reactivo
                            </x-button>
                        </div>
                    </div>
                </x-card>
            </div>
        </form>
    </div>
@endsection
