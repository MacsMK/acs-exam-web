{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Parcial: questions/_partials/form-fields.blade.php (MPW-V2 §7, §8, Tabla 20, Tabla 28, Tabla 29) --}}
@props([
    'mode' => 'create',
    'subjects' => collect(),
    'question' => null,
    'isLinkedToExams' => false,
])

@php
    $typeLabels = [
        'multiple_choice' => 'Opción múltiple',
        'matching' => 'Relación de columnas',
        'open' => 'Pregunta abierta',
        'code' => 'Código fuente',
    ];
    $typeDescriptions = [
        'multiple_choice' => 'De 2 a 6 alternativas con una única respuesta correcta.',
        'matching' => 'De 2 a 8 pares concepto ↔ definición mezclados al estudiante.',
        'open' => 'Respuesta argumentativa evaluada con apoyo de rúbrica e IA.',
        'code' => 'Reto de programación o depuración con solución canónica.',
    ];
    $typeIcons = [
        'multiple_choice' => 'check',
        'matching' => 'filter',
        'open' => 'document',
        'code' => 'code',
    ];
@endphp

<div class="space-y-6">
    {{-- Paso 1: Clasificación, tipo y materia --}}
    <x-card
        title="Paso 1: Clasificación, tipo y materia"
        subtitle="Define el formato del reactivo, su título interno y la materia a la que pertenece."
    >
        <div class="space-y-5">
            {{-- Tipo de reactivo --}}
            @if ($mode === 'create')
                <div class="space-y-2">
                    <label class="block text-[13px] font-medium text-slate-800">
                        Tipo de reactivo <span class="text-acode-danger font-bold ml-0.5" aria-hidden="true">*</span>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup" aria-label="Tipo de reactivo">
                        @foreach ($typeLabels as $typeKey => $typeLabel)
                            <label
                                class="relative flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition-all"
                                :class="type === '{{ $typeKey }}'
                                    ? 'border-acode-tech bg-acode-tech/5 ring-2 ring-acode-tech/30 shadow-xs'
                                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/70'"
                            >
                                <input
                                    type="radio"
                                    name="question_type"
                                    value="{{ $typeKey }}"
                                    x-model="type"
                                    @change="notifyFormChanged()"
                                    class="sr-only"
                                />
                                <span
                                    class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                                    :class="type === '{{ $typeKey }}'
                                        ? 'bg-acode-tech text-white'
                                        : 'bg-slate-100 text-slate-500'"
                                >
                                    <x-icon :name="$typeIcons[$typeKey]" class="w-4 h-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-acode-primary">{{ $typeLabel }}</span>
                                    <span class="block text-xs text-slate-500 mt-0.5 leading-snug">{{ $typeDescriptions[$typeKey] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('question_type')
                        <p class="text-xs text-acode-danger mt-1 flex items-center gap-1 font-medium">
                            <x-icon name="error" class="w-3.5 h-3.5 shrink-0" />
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>
            @else
                <input type="hidden" name="question_type" value="{{ $question->question_type }}">
                <div class="rounded-xl border border-slate-200 bg-acode-surface/60 px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg bg-acode-primary text-white flex items-center justify-center shrink-0">
                            <x-icon name="lock" class="w-4 h-4" />
                        </span>
                        <div>
                            <p class="text-xs font-semibold text-acode-primary">
                                Tipo de reactivo bloqueado: {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                El tipo es inmutable tras la creación para mantener la integridad de sus respuestas e historial.
                            </p>
                        </div>
                    </div>
                    <x-badge variant="info">{{ $typeLabels[$question->question_type] ?? $question->question_type }}</x-badge>
                </div>
            @endif

            {{-- Título interno --}}
            <x-form.group
                name="title"
                label="Título interno del reactivo"
                :required="true"
                help-text="Solo visible para ti en el banco de preguntas; nunca se muestra al estudiante."
            >
                <x-form.input
                    name="title"
                    x-model="title"
                    maxlength="150"
                    required
                    placeholder="Ej. Concepto de llave foránea en SQL"
                    value="{{ old('title', $question?->title ?? '') }}"
                />
            </x-form.group>

            {{-- Combobox inteligente de materia --}}
            <x-form.group
                name="subject_tag"
                label="Materia o asignatura (opcional)"
                help-text="Organiza tu banco por materia. Evita duplicados por mayúsculas o acentos."
            >
                <x-form.input
                    name="subject_tag"
                    list="teacher-subjects-datalist"
                    x-model="subjectTag"
                    @input="handleSubjectInput()"
                    maxlength="100"
                    placeholder="Ej. Bases de Datos, Programación Orientada a Objetos"
                    value="{{ old('subject_tag', $question?->subject_tag ?? ($defaultValues['subject_tag'] ?? '')) }}"
                />
                <datalist id="teacher-subjects-datalist">
                    @foreach ($subjects as $subjectOption)
                        <option value="{{ $subjectOption }}"></option>
                    @endforeach
                </datalist>

                @if ($subjects->isNotEmpty())
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span class="text-[11px] text-slate-500 mr-1">Registradas:</span>
                        @foreach ($subjects->take(8) as $quickSubject)
                            <button
                                type="button"
                                @click="applySuggestedSubject(@js($quickSubject))"
                                class="px-2 py-0.5 rounded-full text-[11px] font-medium border transition-colors"
                                :class="normalizeText(subjectTag) === normalizeText(@js($quickSubject))
                                    ? 'bg-acode-tech text-white border-acode-tech'
                                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                            >
                                {{ $quickSubject }}
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Alerta amigable de similitud para evitar typos --}}
                <div
                    x-show="subjectSuggestions.length > 0"
                    x-cloak
                    x-transition
                    class="mt-2.5 rounded-xl border border-acode-warning/60 bg-acode-warning/15 px-3.5 py-2.5 text-xs text-slate-800 space-y-2"
                >
                    <div class="flex items-start gap-2">
                        <x-icon name="warning" class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <p class="font-semibold text-amber-950">
                                Ya existe una materia similar: «<span x-text="subjectSuggestions[0]?.name"></span>» ¿Usar esa?
                            </p>
                            <p class="text-[11px] text-amber-900 mt-0.5">
                                Seleccionarla mantiene agrupados tus reactivos bajo el mismo nombre canónico.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5 pl-6">
                        <template x-for="suggestion in subjectSuggestions" :key="suggestion.name">
                            <button
                                type="button"
                                @click="applySuggestedSubject(suggestion.name)"
                                class="inline-flex items-center gap-1 rounded-lg border border-amber-400 bg-white px-2.5 py-1 text-xs font-semibold text-amber-950 hover:bg-amber-50 transition"
                            >
                                <x-icon name="check" class="w-3.5 h-3.5 text-acode-accent" />
                                <span>Usar «<span x-text="suggestion.name"></span>»</span>
                            </button>
                        </template>
                    </div>
                </div>
            </x-form.group>

            {{-- Enunciado --}}
            <x-form.group
                name="stem"
                label="Enunciado del reactivo"
                :required="true"
                help-text="Planteamiento exacto que verá el estudiante durante la evaluación (3 a 5,000 caracteres)."
            >
                <x-form.textarea
                    name="stem"
                    x-model="stem"
                    rows="4"
                    minlength="3"
                    maxlength="5000"
                    required
                    placeholder="Redacta aquí la instrucción o planteamiento del reactivo…"
                >{{ old('stem', $question?->stem ?? '') }}</x-form.textarea>
                <div class="flex justify-end">
                    <span class="text-[11px] font-mono text-slate-400" x-text="stem.length + ' / 5000'"></span>
                </div>
            </x-form.group>
        </div>
    </x-card>

    {{-- Paso 2: Contenido técnico y respuesta correcta --}}
    <x-card
        title="Paso 2: Contenido técnico y respuesta correcta"
        subtitle="Configura las alternativas, pares o solución modelo según el tipo de reactivo."
    >
        {{-- 2A. Opción múltiple --}}
        <fieldset x-show="type === 'multiple_choice'" :disabled="type !== 'multiple_choice'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-semibold text-acode-primary">Alternativas de respuesta</h3>
                    <p class="text-xs text-slate-500">Agrega entre 2 y 6 opciones distintas y marca la correcta.</p>
                </div>
                <x-badge variant="info">
                    <span x-text="options.length + ' / 6 opciones'"></span>
                </x-badge>
            </div>

            @error('options')
                <p class="text-xs text-acode-danger font-medium flex items-center gap-1">
                    <x-icon name="error" class="w-3.5 h-3.5 shrink-0" />
                    <span>{{ $message }}</span>
                </p>
            @enderror
            @error('correct_option_index')
                <p class="text-xs text-acode-danger font-medium flex items-center gap-1">
                    <x-icon name="error" class="w-3.5 h-3.5 shrink-0" />
                    <span>{{ $message }}</span>
                </p>
            @enderror

            <div class="space-y-2.5">
                <template x-for="(opt, i) in options" :key="opt.uid">
                    <div
                        class="rounded-xl border p-3 transition-colors"
                        :class="correctOption === i
                            ? 'border-acode-accent bg-acode-accent/5'
                            : 'border-slate-200 bg-white'"
                    >
                        <div class="flex items-center gap-3">
                            <input
                                type="radio"
                                name="correct_option_index"
                                :value="i"
                                x-model.number="correctOption"
                                @change="notifyFormChanged()"
                                :aria-label="'Marcar opción ' + String.fromCharCode(65 + i) + ' como correcta'"
                                class="h-4 w-4 text-acode-accent focus:ring-acode-tech shrink-0 cursor-pointer"
                            />
                            <span
                                class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold shrink-0"
                                :class="correctOption === i ? 'bg-acode-accent text-white' : 'bg-slate-100 text-slate-600'"
                                x-text="String.fromCharCode(65 + i)"
                            ></span>
                            <div class="min-w-0 flex-1">
                                <x-form.input
                                    type="text"
                                    x-bind:name="'options[' + i + ']'"
                                    x-model="opt.text"
                                    maxlength="500"
                                    x-bind:placeholder="'Texto de la opción ' + String.fromCharCode(65 + i)"
                                    x-bind:class="hasFieldError('options.' + i) ? '!border-acode-danger !focus:ring-acode-danger' : ''"
                                    required
                                />
                            </div>
                            <span
                                x-show="correctOption === i"
                                class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-acode-accent/15 text-teal-800 shrink-0"
                            >
                                Correcta
                            </span>
                            <x-button
                                type="button"
                                variant="danger"
                                size="sm"
                                @click="removeOption(i)"
                                x-bind:disabled="options.length <= 2"
                            >
                                <x-icon name="delete" class="w-3.5 h-3.5 sm:mr-1" />
                                <span class="hidden sm:inline">Quitar</span>
                            </x-button>
                        </div>

                        <p
                            x-show="hasFieldError('options.' + i)"
                            x-cloak
                            x-text="firstFieldError('options.' + i)"
                            class="mt-1.5 pl-10 text-xs text-acode-danger font-medium"
                        ></p>
                    </div>
                </template>
            </div>

            <div class="flex items-center justify-between pt-1">
                <x-button
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="addOption()"
                    x-bind:disabled="options.length >= 6"
                >
                    <x-icon name="add" class="w-3.5 h-3.5 mr-1.5" />
                    Agregar opción
                </x-button>
                <span class="text-xs text-slate-400">Máximo 6 opciones sin duplicados.</span>
            </div>
        </fieldset>

        {{-- 2B. Relación de columnas --}}
        <fieldset x-show="type === 'matching'" :disabled="type !== 'matching'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-semibold text-acode-primary">Pares biyectivos (Concepto ↔ Definición)</h3>
                    <p class="text-xs text-slate-500">Define de 2 a 8 pares. El examen mezclará las definiciones para el alumno.</p>
                </div>
                <x-badge variant="accent">
                    <span x-text="pairs.length + ' / 8 pares'"></span>
                </x-badge>
            </div>

            @error('concepts')
                <p class="text-xs text-acode-danger font-medium flex items-center gap-1">
                    <x-icon name="error" class="w-3.5 h-3.5 shrink-0" />
                    <span>{{ $message }}</span>
                </p>
            @enderror
            @error('definitions')
                <p class="text-xs text-acode-danger font-medium flex items-center gap-1">
                    <x-icon name="error" class="w-3.5 h-3.5 shrink-0" />
                    <span>{{ $message }}</span>
                </p>
            @enderror

            <div class="space-y-2.5">
                <template x-for="(pair, i) in pairs" :key="pair.uid">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                Par #<span x-text="i + 1"></span>
                            </span>
                            <x-button
                                type="button"
                                variant="danger"
                                size="sm"
                                @click="removePair(i)"
                                x-bind:disabled="pairs.length <= 2"
                            >
                                <x-icon name="delete" class="w-3.5 h-3.5 mr-1" />
                                Quitar
                            </x-button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Concepto / Premisa</label>
                                <x-form.input
                                    type="text"
                                    x-bind:name="'concepts[' + i + ']'"
                                    x-model="pair.concept"
                                    maxlength="500"
                                    placeholder="Ej. SELECT"
                                    x-bind:class="hasFieldError('concepts.' + i) ? '!border-acode-danger !focus:ring-acode-danger' : ''"
                                    required
                                />
                                <p
                                    x-show="hasFieldError('concepts.' + i)"
                                    x-cloak
                                    x-text="firstFieldError('concepts.' + i)"
                                    class="mt-1 text-xs text-acode-danger font-medium"
                                ></p>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Definición / Respuesta</label>
                                <x-form.input
                                    type="text"
                                    x-bind:name="'definitions[' + i + ']'"
                                    x-model="pair.definition"
                                    maxlength="500"
                                    placeholder="Ej. Consulta filas de una o más tablas"
                                    x-bind:class="hasFieldError('definitions.' + i) ? '!border-acode-danger !focus:ring-acode-danger' : ''"
                                    required
                                />
                                <p
                                    x-show="hasFieldError('definitions.' + i)"
                                    x-cloak
                                    x-text="firstFieldError('definitions.' + i)"
                                    class="mt-1 text-xs text-acode-danger font-medium"
                                ></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex items-center justify-between pt-1">
                <x-button
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="addPair()"
                    x-bind:disabled="pairs.length >= 8"
                >
                    <x-icon name="add" class="w-3.5 h-3.5 mr-1.5" />
                    Agregar par
                </x-button>
                <span class="text-xs text-slate-400">Todos los conceptos y definiciones deben ser únicos entre sí.</span>
            </div>
        </fieldset>

        {{-- 2C. Pregunta abierta --}}
        <fieldset x-show="type === 'open'" :disabled="type !== 'open'" class="space-y-4">
            <x-form.group
                name="reference_answer"
                label="Respuesta modelo canónica"
                :required="true"
                help-text="Sirve como referencia principal para el cotejo docente y la evaluación asistida por IA."
            >
                <x-form.textarea
                    name="reference_answer"
                    x-model="referenceAnswer"
                    rows="4"
                    maxlength="10000"
                    x-bind:required="type === 'open'"
                    placeholder="Redacta la respuesta ideal esperada del estudiante…"
                >{{ old('reference_answer', $question?->questionable?->reference_answer ?? '') }}</x-form.textarea>
            </x-form.group>

            <x-form.group
                name="evaluation_rubric"
                label="Rúbrica o criterios de evaluación (opcional)"
                help-text="Puntos clave, sinónimos aceptados o aspectos que deben validarse en la respuesta."
            >
                <x-form.textarea
                    name="evaluation_rubric"
                    x-model="evaluationRubric"
                    rows="3"
                    maxlength="10000"
                    placeholder="Ej. Menciona atomicidad, consistencia, aislamiento y durabilidad (0.25 pts cada uno)…"
                >{{ old('evaluation_rubric', $question?->questionable?->evaluation_rubric ?? '') }}</x-form.textarea>
            </x-form.group>
        </fieldset>

        {{-- 2D. Código fuente --}}
        <fieldset x-show="type === 'code'" :disabled="type !== 'code'" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <x-form.group
                    name="programming_language"
                    label="Lenguaje de programación"
                    :required="true"
                >
                    <x-form.select
                        name="programming_language"
                        x-model="programmingLanguage"
                        x-bind:required="type === 'code'"
                    >
                        <option value="Python">Python (.py)</option>
                        <option value="JavaScript">JavaScript (.js)</option>
                        <option value="TypeScript">TypeScript (.ts)</option>
                        <option value="PHP">PHP (.php)</option>
                        <option value="Java">Java (.java)</option>
                        <option value="SQL">SQL (.sql)</option>
                        <option value="C">C (.c)</option>
                        <option value="C++">C++ (.cpp)</option>
                        <option value="C#">C# (.cs)</option>
                        <option value="Go">Go (.go)</option>
                        <option value="Otro">Otro (.txt)</option>
                        <template x-if="programmingLanguage && !knownLanguages.includes(programmingLanguage)">
                            <option :value="programmingLanguage" x-text="programmingLanguage"></option>
                        </template>
                    </x-form.select>
                </x-form.group>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 flex items-center justify-between gap-3">
                    <div class="flex items-start gap-2.5">
                        <input type="hidden" name="is_find_error" value="0">
                        <input
                            id="is_find_error"
                            type="checkbox"
                            name="is_find_error"
                            value="1"
                            x-model="isFindError"
                            @change="notifyFormChanged()"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-acode-tech focus:ring-acode-tech cursor-pointer"
                        />
                        <label for="is_find_error" class="cursor-pointer select-none">
                            <span class="block text-xs font-semibold text-acode-primary">Modo «Encuentra y corrige el error»</span>
                            <span class="block text-[11px] text-slate-500">Exige código inicial defectuoso para que el alumno lo depure.</span>
                        </label>
                    </div>
                    <span class="font-mono text-[11px] font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-700 shrink-0" x-text="'.' + codeExtension"></span>
                </div>
            </div>

            <x-form.group
                name="instructions"
                label="Instrucciones técnicas adicionales (opcional)"
                help-text="Especifica entradas esperadas, formato de salida o restricciones del ejercicio."
            >
                <x-form.textarea
                    name="instructions"
                    x-model="instructions"
                    rows="3"
                    maxlength="5000"
                    placeholder="Ej. Implementa la función sin modificar la firma ni usar librerías externas…"
                >{{ old('instructions', $question?->questionable?->instructions ?? '') }}</x-form.textarea>
            </x-form.group>

            <x-form.group
                name="starter_code"
                label="Código base / Código con error"
                :required="false"
                help-text="Obligatorio cuando activas «Encuentra y corrige el error»; opcional como plantilla base."
            >
                <div class="flex items-center justify-between text-[11px] font-mono text-slate-500 mb-1">
                    <span>Archivo inicial: <strong class="text-acode-primary" x-text="'starter_code.' + codeExtension"></strong></span>
                    <span x-show="isFindError" class="text-amber-700 font-semibold">* Requerido en modo depuración</span>
                </div>
                <x-form.textarea
                    name="starter_code"
                    x-model="starterCode"
                    :mono="true"
                    rows="5"
                    maxlength="10000"
                    x-bind:required="type === 'code' && isFindError"
                    placeholder="// Escribe aquí el código base o defectuoso que recibirá el estudiante…"
                >{{ old('starter_code', $question?->questionable?->starter_code ?? '') }}</x-form.textarea>
            </x-form.group>

            <x-form.group
                name="reference_solution"
                label="Solución canónica de referencia"
                :required="true"
                help-text="Código correcto utilizado para evaluar la entrega del estudiante."
            >
                <div class="flex items-center justify-between text-[11px] font-mono text-slate-500 mb-1">
                    <span>Archivo solución: <strong class="text-acode-accent" x-text="'solution.' + codeExtension"></strong></span>
                    <span>Solo visible para el docente</span>
                </div>
                <x-form.textarea
                    name="reference_solution"
                    x-model="referenceSolution"
                    :mono="true"
                    rows="6"
                    maxlength="10000"
                    x-bind:required="type === 'code'"
                    placeholder="// Solución canónica esperada…"
                >{{ old('reference_solution', $question?->questionable?->reference_solution ?? '') }}</x-form.textarea>
            </x-form.group>
        </fieldset>
    </x-card>

    {{-- Paso 3: Puntuación, tiempo sugerido y blindaje anti-copia --}}
    <x-card
        title="Paso 3: Puntuación, tiempo sugerido y blindaje anti-copia"
        subtitle="Configura el valor predeterminado del reactivo y sus variantes para reducir copia entre alumnos."
    >
        <div class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-form.group
                    name="default_points"
                    label="Puntaje predeterminado"
                    :required="true"
                    help-text="Valor entre 0.10 y 100 puntos (editable después al armar un examen)."
                >
                    <x-form.input
                        type="number"
                        name="default_points"
                        x-model.number="defaultPoints"
                        step="0.01"
                        min="0.1"
                        max="100"
                        required
                        value="{{ old('default_points', $question?->default_points ?? ($defaultValues['default_points'] ?? 1)) }}"
                    />
                </x-form.group>

                <x-form.group
                    name="default_time_seconds"
                    label="Tiempo límite sugerido (segundos)"
                    :required="true"
                    help-text="Mínimo 5 seg. y máximo 3,600 seg. (60 minutos)."
                >
                    <x-form.input
                        type="number"
                        name="default_time_seconds"
                        x-model.number="defaultTime"
                        min="5"
                        max="3600"
                        step="1"
                        required
                        value="{{ old('default_time_seconds', $question?->default_time_seconds ?? ($defaultValues['default_time_seconds'] ?? 60)) }}"
                    />
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ([30 => '30s', 60 => '60s', 90 => '90s', 120 => '2 min', 180 => '3 min', 300 => '5 min'] as $seconds => $presetLabel)
                            <button
                                type="button"
                                @click="setTime({{ $seconds }})"
                                class="h-8 px-2.5 rounded-lg border text-xs font-semibold transition-colors"
                                :class="Number(defaultTime) === {{ $seconds }}
                                    ? 'border-acode-tech bg-acode-tech text-white'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                            >
                                {{ $presetLabel }}
                            </button>
                        @endforeach
                    </div>
                </x-form.group>
            </div>

            {{-- Blindaje anti-copia --}}
            @if ($mode === 'create')
                <div class="rounded-xl border border-slate-200 bg-acode-surface/60 p-4 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-acode-primary">Paráfrasis anti-copia iniciales (opcional)</h3>
                                <x-badge variant="info" x-text="activeInitialParaphrases.length + ' / 3'"></x-badge>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Durante el examen, el sistema alterna de forma equitativa entre el enunciado original y sus variantes para dificultar copias sin alterar la respuesta correcta.
                            </p>
                        </div>

                        <x-button
                            type="button"
                            variant="neutral"
                            size="sm"
                            @click="showInitialParaphrases = !showInitialParaphrases"
                        >
                            <span x-text="showInitialParaphrases ? 'Ocultar variantes' : 'Agregar variantes'"></span>
                        </x-button>
                    </div>

                    @error('initial_paraphrases')
                        <p class="text-xs text-acode-danger font-medium">{{ $message }}</p>
                    @enderror

                    <div x-show="showInitialParaphrases" x-cloak x-transition class="space-y-3 pt-1">
                        <template x-for="(para, idx) in initialParaphrases" :key="para.uid">
                            <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-acode-primary">
                                        Paráfrasis #<span x-text="idx + 1"></span>
                                    </span>
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="text-[11px] font-mono"
                                            :class="para.text.length > 480 ? 'text-acode-danger font-bold' : 'text-slate-400'"
                                            x-text="para.text.length + ' / 500'"
                                        ></span>
                                        <x-button type="button" variant="danger" size="sm" @click="removeInitialParaphrase(idx)">
                                            <x-icon name="delete" class="w-3.5 h-3.5 mr-1" />
                                            Quitar
                                        </x-button>
                                    </div>
                                </div>
                                <x-form.textarea
                                    rows="2"
                                    minlength="3"
                                    maxlength="500"
                                    x-model="para.text"
                                    @input="notifyFormChanged()"
                                    placeholder="Redacta una forma equivalente del enunciado (3 a 500 caracteres)…"
                                />
                                <p
                                    x-show="para.text.trim().length > 0 && normalizeText(para.text) === normalizeText(stem)"
                                    x-cloak
                                    class="text-xs text-acode-danger font-medium"
                                >
                                    Esta variante no puede ser idéntica al enunciado principal.
                                </p>
                                <p
                                    x-show="hasFieldError('initial_paraphrases.' + idx)"
                                    x-cloak
                                    x-text="firstFieldError('initial_paraphrases.' + idx)"
                                    class="text-xs text-acode-danger font-medium"
                                ></p>
                            </div>
                        </template>

                        {{-- Inputs ocultos indexados consecutivamente (0..k) solo para variantes no vacías --}}
                        <template x-for="(stemVal, cleanIdx) in activeInitialParaphrases" :key="cleanIdx">
                            <input type="hidden" :name="'initial_paraphrases[' + cleanIdx + ']'" :value="stemVal">
                        </template>

                        <div class="flex items-center justify-between">
                            <x-button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="addInitialParaphrase()"
                                x-bind:disabled="initialParaphrases.length >= 3"
                            >
                                <x-icon name="add" class="w-3.5 h-3.5 mr-1.5" />
                                Agregar variante anti-copia
                            </x-button>
                            <span class="text-[11px] text-slate-400">Máximo 3 al crear (puedes agregar hasta 10 en el detalle).</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-slate-200 bg-acode-surface/60 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-acode-primary">Paráfrasis anti-copia registradas</h3>
                            <x-badge :variant="$question->paraphrases->count() > 0 ? 'success' : 'neutral'">
                                {{ $question->paraphrases->count() }} registradas
                            </x-badge>
                        </div>
                        <p class="text-xs text-slate-500">
                            @if ($isLinkedToExams)
                                Al guardar cambios en un reactivo vinculado se creará una nueva versión; podrás agregar o depurar paráfrasis desde el detalle del reactivo resultante.
                            @else
                                Administra, agrega o elimina variantes anti-copia desde la vista de detalle del reactivo.
                            @endif
                        </p>
                    </div>
                    <x-button :href="route('questions.show', $question)" variant="neutral" size="sm">
                        <x-icon name="view" class="w-3.5 h-3.5 mr-1.5" />
                        Ver detalles
                    </x-button>
                </div>
            @endif
        </div>
    </x-card>
</div>

@once
    @push('scripts')
        <script>
            window.acodeQuestionForm = function (config) {
                let uidCounter = 1;
                const nextUid = () => 'uid-' + (uidCounter++);

                const seedOptions = Array.isArray(config.options) && config.options.length >= 2
                    ? config.options
                    : ['', ''];

                const seedPairs = Array.isArray(config.pairs) && config.pairs.length >= 2
                    ? config.pairs
                    : [{ concept: '', definition: '' }, { concept: '', definition: '' }];

                const seedParaphrases = Array.isArray(config.initialParaphrases)
                    ? config.initialParaphrases
                    : [];

                return {
                    formId: config.formId || 'question-form',
                    type: config.type || 'multiple_choice',
                    title: config.title || '',
                    subjectTag: config.subjectTag || '',
                    stem: config.stem || '',
                    options: seedOptions.map((text) => ({ uid: nextUid(), text: String(text ?? '') })),
                    correctOption: Number(config.correctOption ?? 0),
                    pairs: seedPairs.map((pair) => ({
                        uid: nextUid(),
                        concept: String(pair?.concept ?? ''),
                        definition: String(pair?.definition ?? ''),
                    })),
                    referenceAnswer: config.referenceAnswer || '',
                    evaluationRubric: config.evaluationRubric || '',
                    programmingLanguage: config.programmingLanguage || 'Python',
                    knownLanguages: ['Python', 'JavaScript', 'TypeScript', 'PHP', 'Java', 'SQL', 'C', 'C++', 'C#', 'Go', 'Otro'],
                    isFindError: Boolean(config.isFindError),
                    instructions: config.instructions || '',
                    starterCode: config.starterCode || '',
                    referenceSolution: config.referenceSolution || '',
                    defaultPoints: Number(config.defaultPoints ?? 1),
                    defaultTime: Number(config.defaultTime ?? 60),
                    initialParaphrases: seedParaphrases.map((text) => ({ uid: nextUid(), text: String(text ?? '') })),
                    showInitialParaphrases: seedParaphrases.length > 0 || Boolean(config.hasParaphraseErrors),
                    existingSubjects: Array.isArray(config.existingSubjects) ? config.existingSubjects : [],
                    suggestUrl: config.suggestUrl || '',
                    subjectSuggestions: [],
                    subjectDebounceTimer: null,
                    serverErrors: config.serverErrors || {},
                    previewSelectedOption: null,

                    normalizeText(value) {
                        return String(value ?? '')
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .trim()
                            .replace(/\s+/g, ' ')
                            .toLowerCase();
                    },

                    notifyFormChanged() {
                        const form = document.getElementById(this.formId);
                        if (form) {
                            form.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    },

                    hasFieldError(key) {
                        return Array.isArray(this.serverErrors[key]) && this.serverErrors[key].length > 0;
                    },

                    firstFieldError(key) {
                        return this.hasFieldError(key) ? this.serverErrors[key][0] : '';
                    },

                    addOption() {
                        if (this.options.length < 6) {
                            this.options.push({ uid: nextUid(), text: '' });
                            this.notifyFormChanged();
                        }
                    },

                    removeOption(index) {
                        if (this.options.length > 2) {
                            this.options.splice(index, 1);
                            if (this.correctOption >= this.options.length) {
                                this.correctOption = this.options.length - 1;
                            }
                            this.notifyFormChanged();
                        }
                    },

                    addPair() {
                        if (this.pairs.length < 8) {
                            this.pairs.push({ uid: nextUid(), concept: '', definition: '' });
                            this.notifyFormChanged();
                        }
                    },

                    removePair(index) {
                        if (this.pairs.length > 2) {
                            this.pairs.splice(index, 1);
                            this.notifyFormChanged();
                        }
                    },

                    setTime(seconds) {
                        this.defaultTime = seconds;
                        this.notifyFormChanged();
                    },

                    addInitialParaphrase() {
                        if (this.initialParaphrases.length < 3) {
                            this.initialParaphrases.push({ uid: nextUid(), text: '' });
                            this.showInitialParaphrases = true;
                        }
                    },

                    removeInitialParaphrase(index) {
                        this.initialParaphrases.splice(index, 1);
                        this.notifyFormChanged();
                    },

                    get activeInitialParaphrases() {
                        return this.initialParaphrases
                            .map((item) => String(item.text ?? '').trim())
                            .filter((text) => text.length > 0)
                            .slice(0, 3);
                    },

                    get codeExtension() {
                        const map = {
                            python: 'py',
                            py: 'py',
                            javascript: 'js',
                            js: 'js',
                            typescript: 'ts',
                            ts: 'ts',
                            php: 'php',
                            java: 'java',
                            sql: 'sql',
                            c: 'c',
                            'c++': 'cpp',
                            cpp: 'cpp',
                            'c#': 'cs',
                            csharp: 'cs',
                            go: 'go',
                            golang: 'go',
                        };
                        const normalized = (this.programmingLanguage || '').trim().toLowerCase();
                        return map[normalized] || 'txt';
                    },

                    get formattedTime() {
                        const total = Math.max(0, Number(this.defaultTime) || 0);
                        if (total < 60) {
                            return total + 's';
                        }
                        const mins = Math.floor(total / 60);
                        const rem = total % 60;
                        return rem > 0 ? mins + 'm ' + rem + 's' : mins + 'm';
                    },

                    get formattedPoints() {
                        const pts = Number(this.defaultPoints) || 0;
                        return Number.isInteger(pts) ? String(pts) : pts.toFixed(1);
                    },

                    get previewShuffledDefinitions() {
                        const count = this.pairs.length;
                        if (count === 0) return [];
                        const shift = count === 1 ? 0 : Math.max(1, Math.floor(count / 2));
                        const rotated = [];
                        for (let i = 0; i < count; i++) {
                            const sourceIdx = (i + shift) % count;
                            const text = String(this.pairs[sourceIdx]?.definition ?? '').trim();
                            rotated.push({
                                key: this.pairs[sourceIdx]?.uid || sourceIdx,
                                label: String.fromCharCode(65 + i),
                                text: text || ('Definición ' + (sourceIdx + 1)),
                            });
                        }
                        return rotated;
                    },

                    computeLevenshtein(a, b) {
                        const m = a.length;
                        const n = b.length;
                        if (m === 0) return n;
                        if (n === 0) return m;
                        const dp = Array.from({ length: m + 1 }, () => new Array(n + 1).fill(0));
                        for (let i = 0; i <= m; i++) dp[i][0] = i;
                        for (let j = 0; j <= n; j++) dp[0][j] = j;
                        for (let i = 1; i <= m; i++) {
                            for (let j = 1; j <= n; j++) {
                                const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                                dp[i][j] = Math.min(
                                    dp[i - 1][j] + 1,
                                    dp[i][j - 1] + 1,
                                    dp[i - 1][j - 1] + cost
                                );
                            }
                        }
                        return dp[m][n];
                    },

                    localSubjectSuggestions(rawInput) {
                        const needle = this.normalizeText(rawInput);
                        if (needle.length < 2) return [];

                        return this.existingSubjects
                            .filter((candidate) => {
                                const norm = this.normalizeText(candidate);
                                if (!norm || norm === needle) return false;
                                if (norm.includes(needle) || needle.includes(norm)) return true;
                                const dist = this.computeLevenshtein(needle, norm);
                                return dist <= Math.max(2, Math.floor(Math.max(needle.length, norm.length) * 0.35));
                            })
                            .slice(0, 3)
                            .map((name) => ({ name }));
                    },

                    handleSubjectInput() {
                        const raw = String(this.subjectTag ?? '').trim();
                        if (raw.length < 2) {
                            this.subjectSuggestions = [];
                            if (this.subjectDebounceTimer) clearTimeout(this.subjectDebounceTimer);
                            return;
                        }

                        const localMatches = this.localSubjectSuggestions(raw);
                        this.subjectSuggestions = localMatches;

                        if (!this.suggestUrl) return;
                        if (this.subjectDebounceTimer) clearTimeout(this.subjectDebounceTimer);

                        this.subjectDebounceTimer = setTimeout(async () => {
                            try {
                                const url = this.suggestUrl + '?name=' + encodeURIComponent(raw);
                                const response = await fetch(url, {
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                });
                                if (!response.ok) return;
                                const payload = await response.json();
                                const needle = this.normalizeText(this.subjectTag);
                                const remote = Array.isArray(payload.suggestions) ? payload.suggestions : [];
                                const merged = [];
                                const seen = new Set();

                                [...remote, ...this.localSubjectSuggestions(this.subjectTag)].forEach((item) => {
                                    const name = String(item?.name ?? '').trim();
                                    const norm = this.normalizeText(name);
                                    if (!name || norm === needle || seen.has(norm)) return;
                                    seen.add(norm);
                                    merged.push({ name });
                                });

                                this.subjectSuggestions = merged.slice(0, 3);
                            } catch (e) {
                                // Mantiene sugerencias locales si falla la consulta asíncrona
                            }
                        }, 280);
                    },

                    applySuggestedSubject(name) {
                        this.subjectTag = name;
                        this.subjectSuggestions = [];
                        this.notifyFormChanged();
                    },
                };
            };
        </script>
    @endpush
@endonce
