{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Parcial: questions/_partials/form-fields.blade.php (MPW-V3 §7, §8, Tabla 10, Tabla 18) --}}
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
        'multiple_choice' => 'De 2 a 6 opciones, una correcta.',
        'matching' => 'De 2 a 8 pares con definiciones mezcladas.',
        'open' => 'Respuesta escrita con rúbrica opcional.',
        'code' => 'Reto de programación o depuración.',
    ];
    $typeIcons = [
        'multiple_choice' => 'listBullet',
        'matching' => 'table',
        'open' => 'document',
        'code' => 'code',
    ];
@endphp

<div class="space-y-6">
    <x-card title="Paso 1: Datos del reactivo">
        <div class="space-y-5">
            {{-- Tipo de reactivo --}}
            @if ($mode === 'create')
                <fieldset class="min-w-0 space-y-2">
                    <legend class="block pb-2 text-[13px] font-medium text-slate-800">
                        Tipo de reactivo <span class="text-acode-danger font-bold ml-0.5" aria-hidden="true">*</span>
                    </legend>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        @foreach ($typeLabels as $typeKey => $typeLabel)
                            <label
                                class="relative flex cursor-pointer items-start gap-2.5 rounded-xl border p-3 transition-colors focus-within:ring-2 focus-within:ring-acode-tech focus-within:ring-offset-2"
                                :class="type === '{{ $typeKey }}'
                                    ? 'border-acode-tech bg-acode-tech/5'
                                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/70'"
                            >
                                <input
                                    type="radio"
                                    name="question_type"
                                    value="{{ $typeKey }}"
                                    x-model="type"
                                    @change="notifyFormChanged()"
                                    class="sr-only"
                                    required
                                />
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors"
                                    :class="type === '{{ $typeKey }}'
                                        ? 'bg-acode-tech text-white'
                                        : 'bg-slate-100 text-slate-500'"
                                >
                                    <x-icon :name="$typeIcons[$typeKey]" class="w-4 h-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-acode-primary">{{ $typeLabel }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-600">{{ $typeDescriptions[$typeKey] }}</span>
                                </span>
                                <x-icon name="check" class="mt-0.5 h-4 w-4 text-acode-tech" x-show="type === '{{ $typeKey }}'" x-cloak />
                            </label>
                        @endforeach
                    </div>
                    @error('question_type')
                        <p class="text-xs text-acode-danger mt-1 flex items-center gap-1 font-medium">
                            <x-icon name="error" class="w-3.5 h-3.5 shrink-0" />
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </fieldset>
            @else
                <input type="hidden" name="question_type" value="{{ $question->question_type }}">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-slate-200 bg-acode-surface/60 px-3.5 py-2">
                    <div class="flex items-center gap-2 text-sm text-acode-primary">
                        <x-icon name="lock" class="h-4 w-4 shrink-0" />
                        <span class="font-semibold">{{ $typeLabels[$question->question_type] ?? $question->question_type }}</span>
                    </div>
                    <p class="text-xs text-slate-700">El tipo se fija al crear el reactivo.</p>
                </div>
            @endif

            {{-- Título interno --}}
            <x-form.group
                name="title"
                label="Título interno del reactivo"
                :required="true"
                help-text="Identifica el reactivo en tu banco de preguntas."
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

            @include('questions._partials.stem-field')

            {{-- Combobox inteligente de materia --}}
            <x-form.group
                name="subject_tag"
                label="Materia (opcional)"
            >
                <x-form.input
                    name="subject_tag"
                    list="teacher-subjects-datalist"
                    x-model="subjectTag"
                    @input="handleSubjectInput()"
                    maxlength="100"
                    placeholder="Ej. Bases de Datos"
                    value="{{ old('subject_tag', $question?->subject_tag ?? ($defaultValues['subject_tag'] ?? '')) }}"
                />
                <datalist id="teacher-subjects-datalist">
                    @foreach ($subjects as $subjectOption)
                        <option value="{{ $subjectOption }}"></option>
                    @endforeach
                </datalist>

                @if ($subjects->isNotEmpty())
                    <div class="mt-2 flex flex-wrap items-center gap-1.5" role="group" aria-label="Materias registradas">
                        <span class="mr-1 text-xs text-slate-500" aria-hidden="true">Registradas</span>
                        @foreach ($subjects->take(8) as $quickSubject)
                            <x-button
                                type="button"
                                variant="neutral"
                                size="sm"
                                data-subject="{{ $quickSubject }}"
                                @click="applySuggestedSubject($el.dataset.subject)"
                                x-bind:aria-pressed="normalizeText(subjectTag) === normalizeText($el.dataset.subject)"
                                class="aria-pressed:border-acode-tech aria-pressed:bg-acode-tech/5 aria-pressed:text-acode-primary"
                            >
                                {{ $quickSubject }}
                            </x-button>
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
                        <x-icon name="warning" class="w-4 h-4 text-acode-warning-dark shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <p class="font-semibold text-acode-primary">
                                Ya existe una materia similar: «<span x-text="subjectSuggestions[0]?.name"></span>» ¿Usar esa?
                            </p>
                            <p class="mt-0.5 text-xs text-slate-700">Usa una materia registrada para evitar duplicados.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5 pl-6">
                        <template x-for="suggestion in subjectSuggestions" :key="suggestion.name">
                            <x-button
                                type="button"
                                variant="neutral"
                                size="sm"
                                @click="applySuggestedSubject(suggestion.name)"
                            >
                                <x-icon name="check" class="w-3.5 h-3.5 text-acode-accent" />
                                <span>Aceptar «<span x-text="suggestion.name"></span>»</span>
                            </x-button>
                        </template>
                    </div>
                </div>
            </x-form.group>

            {{-- Imagen y PDF opcionales de la pregunta, uno de cada tipo (RF-BAN-012, RF-BAN-013) --}}
            @php
                $questionFileFields = [
                    'image' => ['label' => 'Imagen (opcional, JPG o PNG de hasta 5 MB)', 'accept' => '.jpg,.jpeg,.png,image/jpeg,image/png', 'column' => 'image_path', 'kind' => 'imagen', 'current' => 'Ver imagen actual', 'remove' => 'Quitar la imagen'],
                    'pdf' => ['label' => 'PDF de apoyo (opcional, hasta 5 MB)', 'accept' => '.pdf,application/pdf', 'column' => 'pdf_path', 'kind' => 'pdf', 'current' => 'Ver PDF actual', 'remove' => 'Quitar el PDF'],
                ];
            @endphp
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($questionFileFields as $fileField => $fileConfig)
                    <x-form.group :name="$fileField" :label="$fileConfig['label']">
                        <input
                            id="{{ $fileField }}"
                            type="file"
                            name="{{ $fileField }}"
                            accept="{{ $fileConfig['accept'] }}"
                            class="block w-full h-10 text-sm text-slate-700 file:mr-3 file:h-full file:px-3.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-acode-primary file:text-white hover:file:bg-acode-tech border border-slate-300 rounded-lg bg-white p-1"
                        />
                        @if ($mode === 'edit' && $question?->{$fileConfig['column']})
                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                <a href="{{ route('questions.file', ['question' => $question->id, 'kind' => $fileConfig['kind']]) }}" target="_blank" rel="noopener" class="font-medium text-acode-tech hover:underline">{{ $fileConfig['current'] }}</a>
                                <label class="inline-flex items-center gap-1.5 text-slate-600">
                                    <input type="checkbox" name="remove_{{ $fileField }}" value="1" class="rounded border-slate-300 text-acode-tech focus:ring-acode-tech">
                                    {{ $fileConfig['remove'] }}
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Si eliges un archivo nuevo, reemplaza al actual.</p>
                        @endif
                    </x-form.group>
                @endforeach
            </div>
        </div>
    </x-card>

    <x-card title="Paso 2: Contenido y respuesta">
        {{-- 2A. Opción múltiple --}}
        <fieldset x-show="type === 'multiple_choice'" :disabled="type !== 'multiple_choice'" class="space-y-4">
            <legend class="sr-only">Alternativas de respuesta</legend>
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-semibold text-acode-primary leading-6">Alternativas de respuesta</h3>
                    <p class="text-xs text-slate-500">Agrega entre 2 y 6 opciones distintas y marca la correcta.</p>
                    <p
                        id="correct-option-status"
                        role="status"
                        class="text-xs font-medium text-acode-danger"
                        x-text="correctOption < 0 ? 'Marca la opción correcta antes de guardar.' : ''"
                    ></p>
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
                            <label class="inline-flex min-h-8 shrink-0 cursor-pointer items-center gap-2">
                                {{-- Actualiza el valor cuando Alpine reutiliza una opción con un índice distinto. --}}
                                <input
                                    type="radio"
                                    name="correct_option_index"
                                    x-effect="$el.value = i"
                                    :checked="correctOption === i"
                                    @change="correctOption = i; notifyFormChanged()"
                                    :aria-label="'Marcar opción ' + String.fromCharCode(65 + i) + ' como correcta'"
                                    aria-describedby="correct-option-status"
                                    class="h-4 w-4 shrink-0 cursor-pointer text-acode-accent focus:ring-acode-tech"
                                    required
                                />
                                <span
                                    class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold shrink-0"
                                    :class="correctOption === i ? 'bg-acode-accent/15 text-acode-primary' : 'bg-slate-100 text-slate-600'"
                                    x-text="String.fromCharCode(65 + i)"
                                    aria-hidden="true"
                                ></span>
                            </label>
                            <div class="min-w-0 flex-1">
                                <label
                                    :for="formId + '-option-' + opt.uid"
                                    class="sr-only"
                                    x-text="'Texto de la opción ' + String.fromCharCode(65 + i)"
                                ></label>
                                <x-form.input
                                    type="text"
                                    x-bind:id="formId + '-option-' + opt.uid"
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
                                class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-acode-accent/15 text-acode-primary border border-acode-accent/40 shrink-0"
                            >
                                Correcta
                            </span>
                            <x-button
                                type="button"
                                variant="danger"
                                size="sm"
                                @click="removeOption(i)"
                                x-bind:disabled="options.length <= 2"
                                x-bind:aria-label="'Eliminar opción ' + String.fromCharCode(65 + i)"
                            >
                                <x-icon name="delete" class="w-3.5 h-3.5" />
                                <span class="hidden sm:inline">Eliminar</span>
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

            <div class="pt-1">
                <x-button
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="addOption()"
                    x-bind:disabled="options.length >= 6"
                >
                    <x-icon name="add" class="w-3.5 h-3.5" />
                    Agregar opción
                </x-button>
            </div>
        </fieldset>

        {{-- 2B. Relación de columnas --}}
        <fieldset x-show="type === 'matching'" :disabled="type !== 'matching'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-semibold text-acode-primary leading-6">Pares de concepto y definición</h3>
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
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Par #<span x-text="i + 1"></span>
                            </span>
                            <x-button
                                type="button"
                                variant="danger"
                                size="sm"
                                @click="removePair(i)"
                                x-bind:disabled="pairs.length <= 2"
                            >
                                <x-icon name="delete" class="w-3.5 h-3.5" />
                                Eliminar
                            </x-button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label :for="formId + '-concept-' + pair.uid" class="block text-sm font-medium text-slate-800 mb-1">
                                    Concepto / Premisa <span class="sr-only" x-text="'del par ' + (i + 1)"></span>
                                </label>
                                <x-form.input
                                    type="text"
                                    x-bind:id="formId + '-concept-' + pair.uid"
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
                                <label :for="formId + '-definition-' + pair.uid" class="block text-sm font-medium text-slate-800 mb-1">
                                    Definición / Respuesta <span class="sr-only" x-text="'del par ' + (i + 1)"></span>
                                </label>
                                <x-form.input
                                    type="text"
                                    x-bind:id="formId + '-definition-' + pair.uid"
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
                    <x-icon name="add" class="w-3.5 h-3.5" />
                    Agregar par
                </x-button>
                <span class="text-xs text-slate-500">Todos los conceptos y definiciones deben ser únicos entre sí.</span>
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
                            class="mt-0.5 h-4 w-4 rounded-md border-slate-300 text-acode-tech focus:ring-acode-tech cursor-pointer"
                        />
                        <label for="is_find_error" class="cursor-pointer select-none">
                            <span class="block text-sm font-medium text-slate-800">Modo «Encuentra y corrige el error»</span>
                            <span class="block text-xs text-slate-500">Exige código inicial defectuoso para que el alumno lo depure.</span>
                        </label>
                    </div>
                    <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-slate-200 text-slate-700 shrink-0" x-text="'.' + codeExtension"></span>
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

            {{-- Criterios de evaluación opcionales (RF-BAN-004); se envían a la evaluación asistida por IA (RF-IAR-003) --}}
            <x-form.group
                name="evaluation_rubric"
                label="Criterios de evaluación (opcional)"
                help-text="Aspectos que deben cumplirse para calificar la solución: lógica, casos límite, buenas prácticas o puntaje por criterio."
            >
                <x-form.textarea
                    name="evaluation_rubric"
                    x-model="evaluationRubric"
                    rows="3"
                    maxlength="10000"
                    placeholder="Ej. Usa un ciclo, maneja la lista vacía y devuelve el resultado sin imprimirlo…"
                >{{ old('evaluation_rubric', $question?->questionable?->evaluation_rubric ?? '') }}</x-form.textarea>
            </x-form.group>

            <x-form.group
                name="starter_code"
                label="Código base / Código con error"
                :required="false"
                help-text="Obligatorio cuando activas «Encuentra y corrige el error»; opcional como plantilla base."
            >
                <div class="flex items-center justify-between text-xs font-mono text-slate-500 mb-1">
                    <span>Archivo inicial: <strong class="text-acode-primary" x-text="'starter_code.' + codeExtension"></strong></span>
                    <span x-show="isFindError" class="text-acode-warning-dark font-semibold">* Requerido en modo depuración</span>
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
                <div class="flex items-center justify-between text-xs font-mono text-slate-500 mb-1">
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
    <x-card title="Paso 3: Puntaje, tiempo y blindaje anti-copia">
        <div class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-form.group
                    name="default_points"
                    label="Puntaje predeterminado"
                    :required="true"
                    help-text="De 0.10 a 100 puntos. Puedes ajustarlo al armar el examen."
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
                    help-text="De 5 a 3,600 segundos (60 minutos)."
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
                    <div class="mt-2 flex flex-wrap gap-1.5" role="group" aria-label="Tiempos sugeridos">
                        @foreach ([30 => '30 s', 60 => '60 s', 90 => '90 s', 120 => '2 min', 180 => '3 min', 300 => '5 min'] as $seconds => $presetLabel)
                            <x-button
                                type="button"
                                variant="neutral"
                                size="sm"
                                @click="setTime({{ $seconds }})"
                                x-bind:aria-pressed="Number(defaultTime) === {{ $seconds }}"
                                class="aria-pressed:border-acode-tech aria-pressed:bg-acode-tech/5 aria-pressed:text-acode-primary"
                            >
                                {{ $presetLabel }}
                            </x-button>
                        @endforeach
                    </div>
                </x-form.group>
            </div>

            {{-- Blindaje anti-copia --}}
            @if ($mode === 'create')
                <div class="rounded-xl border border-slate-200 bg-acode-surface/60 p-4 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-acode-primary leading-6">Paráfrasis anti-copia iniciales (opcional)</h3>
                                <x-badge variant="info" x-text="activeInitialParaphrases.length + ' / 3'"></x-badge>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-700">
                                Redacta variantes del enunciado que conserven la misma respuesta correcta.
                            </p>
                        </div>

                        <x-button
                            type="button"
                            variant="neutral"
                            size="sm"
                            @click="showInitialParaphrases = !showInitialParaphrases"
                            x-bind:aria-expanded="showInitialParaphrases"
                            aria-controls="initial-paraphrases-editor"
                            class="shrink-0 whitespace-nowrap"
                        >
                            <span x-text="showInitialParaphrases ? 'Cerrar variantes' : 'Abrir variantes'"></span>
                        </x-button>
                    </div>

                    @error('initial_paraphrases')
                        <p class="text-xs font-medium text-acode-danger-dark">{{ $message }}</p>
                    @enderror

                    <div id="initial-paraphrases-editor" x-show="showInitialParaphrases" x-cloak x-transition class="space-y-3 pt-1">
                        <template x-for="(para, idx) in initialParaphrases" :key="para.uid">
                            <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <label :for="formId + '-paraphrase-' + para.uid" class="text-xs font-semibold text-acode-primary">
                                        Paráfrasis #<span x-text="idx + 1"></span>
                                    </label>
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="text-xs font-mono"
                                            :class="para.text.length > 480 ? 'text-acode-danger font-bold' : 'text-slate-500'"
                                            x-text="para.text.length + ' / 500'"
                                        ></span>
                                        <x-button type="button" variant="danger" size="sm" @click="removeInitialParaphrase(idx)"
                                            x-bind:aria-label="'Eliminar paráfrasis ' + (idx + 1)">
                                            <x-icon name="delete" class="w-3.5 h-3.5" />
                                            Eliminar
                                        </x-button>
                                    </div>
                                </div>
                                <x-form.textarea
                                    rows="2"
                                    x-bind:id="formId + '-paraphrase-' + para.uid"
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

                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <x-button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="addInitialParaphrase()"
                                x-bind:disabled="initialParaphrases.length >= 3"
                            >
                                <x-icon name="add" class="w-3.5 h-3.5" />
                                Agregar variante anti-copia
                            </x-button>
                            <span class="text-xs text-slate-700">Puedes agregar hasta 10 en el detalle.</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-slate-200 bg-acode-surface/60 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-semibold text-acode-primary leading-6">Paráfrasis anti-copia</h3>
                            <x-badge :variant="$question->paraphrases->count() > 0 ? 'success' : 'neutral'">
                                {{ $question->paraphrases->count() }} registradas
                            </x-badge>
                        </div>
                        <p class="text-xs text-slate-700">
                            @if ($isLinkedToExams)
                                Después de guardar, administra las paráfrasis desde el detalle de la nueva versión.
                            @else
                                Administra las variantes desde el detalle del reactivo.
                            @endif
                        </p>
                    </div>
                    <x-button :href="route('questions.show', $question)" variant="neutral" size="sm" class="shrink-0" data-cancel-form="edit-question-form">
                        <x-icon name="view" class="w-3.5 h-3.5" />
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
                        if (this.options.length <= 2) return;

                        const correctUid = this.options[this.correctOption]?.uid;
                        const previewUid = this.options[this.previewSelectedOption]?.uid;
                        this.options.splice(index, 1);
                        this.correctOption = this.options.findIndex((option) => option.uid === correctUid);

                        const previewIndex = this.options.findIndex((option) => option.uid === previewUid);
                        this.previewSelectedOption = previewIndex < 0 ? null : previewIndex;
                        this.notifyFormChanged();
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
                        const map = @js(\App\Models\CodeItem::FILE_EXTENSIONS);
                        const normalized = (this.programmingLanguage || '').trim().toLowerCase();
                        return map[normalized] || 'txt';
                    },

                    get formattedTime() {
                        const total = Math.max(0, Number(this.defaultTime) || 0);
                        if (total < 60) {
                            return total + ' s';
                        }
                        const mins = Math.floor(total / 60);
                        const rem = total % 60;
                        return rem > 0 ? mins + ' min ' + rem + ' s' : mins + ' min';
                    },

                    get formattedPoints() {
                        const pts = Number(this.defaultPoints) || 0;
                        return String(Number(pts.toFixed(2)));
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
