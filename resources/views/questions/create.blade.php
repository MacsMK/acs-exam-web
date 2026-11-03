{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/create.blade.php (MPW-V2 §7.3, §7.8.2, §8.5, Tabla 20, Tabla 28) --}}
@extends('layouts.app')

@section('title', 'Crear Reactivo')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Crear Reactivo</h1>
        <p class="text-sm text-slate-500 mt-1">Configura un nuevo reactivo polimórfico en el banco de preguntas institucional.</p>
    </div>

    <x-card
        class="max-w-3xl"
        x-data="{
            type: '{{ old('question_type', 'multiple_choice') }}',
            options: ['', ''],
            pairs: [{ concept: '', definition: '' }, { concept: '', definition: '' }],
            addOption() { this.options.push(''); },
            removeOption(i) { if (this.options.length > 2) this.options.splice(i, 1); },
            addPair() { this.pairs.push({ concept: '', definition: '' }); },
            removePair(i) { if (this.pairs.length > 2) this.pairs.splice(i, 1); },
        }"
    >
        <form method="POST" action="{{ route('questions.store') }}" class="space-y-5">
            @csrf

            {{-- Título interno del reactivo --}}
            <x-form.group
                name="title"
                label="Título identificador del reactivo"
                helpText="Nombre breve para localizarlo en el catálogo docente."
                required
            >
                <x-form.input
                    type="text"
                    name="title"
                    value="{{ old('title') }}"
                    required
                    maxlength="150"
                    placeholder="Ej. Principio SOLID: Inversión de Dependencias"
                    prefixIcon="edit"
                />
            </x-form.group>

            {{-- Enunciado o Planteamiento pedagógico --}}
            <x-form.group
                name="stem"
                label="Enunciado o planteamiento principal"
                helpText="Texto que visualizará el estudiante durante la resolución del examen."
                required
            >
                <x-form.textarea
                    name="stem"
                    rows="3"
                    required
                    placeholder="Escribe el planteamiento formal de la pregunta…"
                >{{ old('stem') }}</x-form.textarea>
            </x-form.group>

            {{-- Metadatos de evaluación: Materia, Puntos y Tiempo --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <x-form.group name="subject_tag" label="Materia / Categoría">
                    <x-form.input
                        type="text"
                        name="subject_tag"
                        value="{{ old('subject_tag') }}"
                        maxlength="100"
                        placeholder="Ej. Programación"
                        prefixIcon="shield"
                    />
                </x-form.group>

                <x-form.group name="default_points" label="Puntos nominales" required>
                    <x-form.input
                        type="number"
                        step="0.01"
                        min="0"
                        name="default_points"
                        value="{{ old('default_points', 1) }}"
                        required
                        prefixIcon="reportBar"
                    />
                </x-form.group>

                <x-form.group name="default_time_seconds" label="Tiempo límite (segundos)" required>
                    <x-form.input
                        type="number"
                        min="1"
                        name="default_time_seconds"
                        value="{{ old('default_time_seconds', 60) }}"
                        required
                        prefixIcon="clock"
                    />
                </x-form.group>
            </div>

            {{-- Selector de Tipo de Reactivo --}}
            <x-form.group name="question_type" label="Tipo de reactivo pedagógico" required>
                <x-form.select name="question_type" x-model="type" required>
                    <option value="multiple_choice">Opción múltiple (selección unívoca)</option>
                    <option value="matching">Relación de columnas (conceptos y definiciones)</option>
                    <option value="open">Pregunta abierta (evaluación cualitativa / IA)</option>
                    <option value="code">Código de programación (supervisado)</option>
                </x-form.select>
            </x-form.group>

            {{-- Configuración de Opción Múltiple --}}
            <div x-show="type === 'multiple_choice'" class="space-y-3 border-t border-slate-200 pt-5">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-semibold text-slate-800 uppercase tracking-wider">
                        Opciones de respuesta (Marca la alternativa correcta)
                    </label>
                    <x-button type="button" variant="secondary" size="sm" @click="addOption()">
                        <x-icon name="add" class="w-3.5 h-3.5 mr-1" />
                        Crear opción
                    </x-button>
                </div>

                <template x-for="(option, i) in options" :key="i">
                    <div class="flex items-center gap-2.5 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                        <input
                            type="radio"
                            name="correct_option_index"
                            :value="i"
                            :checked="i === 0"
                            class="text-acode-primary focus:ring-acode-tech h-4 w-4"
                            title="Marcar como respuesta correcta"
                        />
                        <input
                            type="text"
                            :name="'options[' + i + ']'"
                            x-model="options[i]"
                            class="flex-1 h-9 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech bg-white"
                            :placeholder="'Texto de opción ' + (i + 1)"
                            required
                        />
                        <button
                            type="button"
                            @click="removeOption(i)"
                            class="p-1.5 text-acode-danger hover:bg-acode-danger/10 rounded-md transition"
                            title="Eliminar opción"
                        >
                            <x-icon name="delete" class="w-4 h-4" />
                        </button>
                    </div>
                </template>
            </div>

            {{-- Configuración de Relación de Columnas --}}
            <div x-show="type === 'matching'" class="space-y-3 border-t border-slate-200 pt-5" style="display: none;">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-semibold text-slate-800 uppercase tracking-wider">
                        Pares de correspondencia (Concepto ↔ Definición)
                    </label>
                    <x-button type="button" variant="secondary" size="sm" @click="addPair()">
                        <x-icon name="add" class="w-3.5 h-3.5 mr-1" />
                        Crear par
                    </x-button>
                </div>

                <template x-for="(pair, i) in pairs" :key="i">
                    <div class="flex items-center gap-2 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                        <input
                            type="text"
                            :name="'concepts[' + i + ']'"
                            x-model="pair.concept"
                            placeholder="Concepto (Columna izquierda)"
                            class="flex-1 h-9 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech bg-white"
                            required
                        />
                        <span class="text-slate-400 font-bold px-1">↔</span>
                        <input
                            type="text"
                            :name="'definitions[' + i + ']'"
                            x-model="pair.definition"
                            placeholder="Definición (Columna derecha)"
                            class="flex-1 h-9 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech bg-white"
                            required
                        />
                        <button
                            type="button"
                            @click="removePair(i)"
                            class="p-1.5 text-acode-danger hover:bg-acode-danger/10 rounded-md transition"
                            title="Eliminar par"
                        >
                            <x-icon name="delete" class="w-4 h-4" />
                        </button>
                    </div>
                </template>
                <p class="text-[11px] text-slate-500 italic">
                    La columna de definiciones se baraja automáticamente de forma pseudoaleatoria al presentarse al alumno.
                </p>
            </div>

            {{-- Configuración de Pregunta Abierta --}}
            <div x-show="type === 'open'" class="space-y-4 border-t border-slate-200 pt-5" style="display: none;">
                <x-form.group
                    name="reference_answer"
                    label="Respuesta esperada o modelo de referencia"
                    helpText="Criterio de comparación semántica empleado por el motor evaluador."
                >
                    <x-form.textarea
                        name="reference_answer"
                        rows="3"
                        placeholder="Redacta la solución canónica o los puntos clave que debe contener la respuesta…"
                    >{{ old('reference_answer') }}</x-form.textarea>
                </x-form.group>

                <x-form.group
                    name="evaluation_rubric"
                    label="Rúbrica de evaluación complementaria (opcional)"
                >
                    <x-form.textarea
                        name="evaluation_rubric"
                        rows="2"
                        placeholder="Ponderaciones, criterios de penalización o conceptos obligatorios…"
                    >{{ old('evaluation_rubric') }}</x-form.textarea>
                </x-form.group>
            </div>

            {{-- Configuración de Pregunta de Programación --}}
            <div x-show="type === 'code'" class="space-y-4 border-t border-slate-200 pt-5" style="display: none;">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-form.group name="programming_language" label="Lenguaje de programación">
                        <x-form.input
                            type="text"
                            name="programming_language"
                            value="{{ old('programming_language', 'PHP') }}"
                            maxlength="40"
                            prefixIcon="shield"
                        />
                    </x-form.group>

                    <div class="flex items-center pt-6">
                        <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                name="is_find_error"
                                value="1"
                                class="rounded border-slate-300 text-acode-tech focus:ring-acode-tech"
                            />
                            <span class="font-semibold">Modalidad: "Encontrar y corregir el error"</span>
                        </label>
                    </div>
                </div>

                <x-form.group
                    name="starter_code"
                    label="Código inicial o plantilla base para el estudiante"
                    helpText="Código fuente con sangría y formateo monoespaciado Fira Code."
                >
                    <x-form.textarea
                        name="starter_code"
                        rows="4"
                        :mono="true"
                        placeholder="// Escribe aquí el código base…"
                    >{{ old('starter_code') }}</x-form.textarea>
                </x-form.group>

                <x-form.group name="instructions" label="Instrucciones técnicas de desarrollo">
                    <x-form.textarea
                        name="instructions"
                        rows="2"
                        placeholder="Instrucciones específicas sobre librerías o condiciones de ejecución…"
                    >{{ old('instructions') }}</x-form.textarea>
                </x-form.group>

                <x-form.group
                    name="reference_solution"
                    label="Solución canónica de referencia (código completo funcional)"
                >
                    <x-form.textarea
                        name="reference_solution"
                        rows="4"
                        :mono="true"
                        placeholder="// Código funcional de referencia…"
                    >{{ old('reference_solution') }}</x-form.textarea>
                </x-form.group>
            </div>

            {{-- Botones de Acción Normativos --}}
            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <x-button type="submit" variant="primary" size="md">
                    <x-icon name="save" class="w-4 h-4 mr-1.5" />
                    Guardar reactivo
                </x-button>

                <x-button :href="route('questions.index')" variant="neutral" size="md">
                    Volver
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
