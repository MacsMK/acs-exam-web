@extends('layouts.app')

@section('title', 'Nueva pregunta')

@section('content')
<div class="mx-auto max-w-6xl space-y-6" x-data="questionCreator()">
    <a href="{{ route('questions.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-acode-tech hover:underline">
        <span aria-hidden="true">←</span> Volver al banco de preguntas
    </a>

    <header>
        <h1 class="mt-1 text-2xl font-bold text-acode-primary">Nueva pregunta</h1>
        <p class="mt-2 text-sm text-slate-500">Completa los datos y revisa cómo se presentará la pregunta al estudiante.</p>
    </header>

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#DC2626]" role="alert">
            <p class="font-semibold">Revisa los siguientes datos:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="create-question-form" method="POST" action="{{ route('questions.store') }}" class="space-y-6">
        @csrf
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0 space-y-6">
                <section class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-acode-primary">Datos generales</h2>
                    <div class="mt-5 space-y-4">
                        <div>
                            <label for="question-title" class="mb-2 block text-sm font-medium text-slate-800">Título interno</label>
                            <input id="question-title" type="text" name="title" x-model="title" required maxlength="150" placeholder="Identificador de la pregunta en tu banco" class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                            <p class="mt-1 text-xs text-slate-500">Este título es para el docente; no se muestra al estudiante.</p>
                        </div>
                        <div>
                            <label for="question-stem" class="mb-2 block text-sm font-medium text-slate-800">Enunciado</label>
                            <textarea id="question-stem" name="stem" x-model="stem" required rows="4" placeholder="Escribe la pregunta que verá el estudiante" class="w-full rounded-lg border border-slate-300 p-3 text-sm focus:ring-2 focus:ring-acode-tech"></textarea>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label for="subject-tag" class="mb-2 block text-sm font-medium text-slate-800">Materia (opcional)</label>
                                <input id="subject-tag" type="text" name="subject_tag" value="{{ old('subject_tag') }}" maxlength="100" class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                            </div>
                            <div>
                                <label for="default-points" class="mb-2 block text-sm font-medium text-slate-800">Puntos</label>
                                <input id="default-points" type="number" name="default_points" value="{{ old('default_points', 1) }}" step="0.01" min="0" required class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                            </div>
                            <div>
                                <label for="default-time" class="mb-2 block text-sm font-medium text-slate-800">Tiempo (seg.)</label>
                                <input id="default-time" type="number" name="default_time_seconds" value="{{ old('default_time_seconds', 60) }}" min="1" step="1" required class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                            </div>
                        </div>
                        <div>
                            <label for="question-type" class="mb-2 block text-sm font-medium text-slate-800">Tipo de pregunta</label>
                            <select id="question-type" name="question_type" x-model="type" required class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                                <option value="multiple_choice">Opción múltiple</option>
                                <option value="matching">Relación de columnas</option>
                                <option value="open">Pregunta abierta</option>
                                <option value="code">Programación / Código</option>
                            </select>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                            <button type="button" disabled class="cursor-not-allowed rounded-lg border border-slate-300 bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-500 opacity-75">Adjuntar imagen o PDF</button>
                            <span class="text-xs text-slate-500">Próximamente · Función aún no disponible</span>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold text-acode-primary">Contenido del pregunta</h2>
                    <p class="mt-2 text-sm text-slate-500">Los campos cambian según el tipo de pregunta seleccionado.</p>

                    <fieldset x-show="type === 'multiple_choice'" :disabled="type !== 'multiple_choice'" class="mt-5 space-y-3">
                        <legend class="mb-3 text-sm font-semibold text-slate-800">Opciones de respuesta · Selecciona la correcta</legend>
                        <template x-for="(option, i) in options" :key="option.id">
                            <div class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                                <input type="radio" name="correct_option_index" :value="i" x-model.number="correctIndex" class="shrink-0 text-acode-tech focus:ring-acode-tech" :aria-label="'Marcar opción ' + (i + 1) + ' como correcta'">
                                <input type="text" :name="'options[' + i + ']'" x-model="option.text" :placeholder="'Opción ' + (i + 1)" class="h-10 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                                <button type="button" @click="removeOption(i)" :disabled="options.length <= 2" class="text-xs font-semibold text-[#DC2626] disabled:cursor-not-allowed disabled:opacity-40">Quitar</button>
                            </div>
                        </template>
                        <button type="button" @click="addOption()" class="text-sm font-semibold text-acode-tech hover:underline">+ Agregar opción</button>
                    </fieldset>

                    <fieldset x-show="type === 'matching'" :disabled="type !== 'matching'" class="mt-5 space-y-3">
                        <legend class="mb-3 text-sm font-semibold text-slate-800">Pares de concepto y definición</legend>
                        <template x-for="(pair, i) in pairs" :key="pair.id">
                            <div class="grid grid-cols-1 items-center gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[1fr_1fr_auto]">
                                <input type="text" :name="'concepts[' + i + ']'" x-model="pair.concept" placeholder="Concepto" class="h-10 min-w-0 rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                                <input type="text" :name="'definitions[' + i + ']'" x-model="pair.definition" placeholder="Definición" class="h-10 min-w-0 rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                                <button type="button" @click="removePair(i)" :disabled="pairs.length <= 2" class="text-xs font-semibold text-[#DC2626] disabled:opacity-40">Quitar</button>
                            </div>
                        </template>
                        <button type="button" @click="addPair()" class="text-sm font-semibold text-acode-tech hover:underline">+ Agregar par</button>
                        <p class="text-xs text-slate-500">Las definiciones se mostrarán mezcladas al estudiante durante el examen.</p>
                    </fieldset>

                    <fieldset x-show="type === 'open'" :disabled="type !== 'open'" class="mt-5 space-y-4">
                        <legend class="mb-3 text-sm font-semibold text-slate-800">Criterios de respuesta</legend>
                        <div>
                            <label for="reference-answer" class="mb-2 block text-sm font-medium text-slate-800">Respuesta de referencia</label>
                            <textarea id="reference-answer" name="reference_answer" rows="4" class="w-full rounded-lg border border-slate-300 p-3 text-sm focus:ring-2 focus:ring-acode-tech">{{ old('reference_answer') }}</textarea>
                        </div>
                        <div>
                            <label for="evaluation-rubric" class="mb-2 block text-sm font-medium text-slate-800">Rúbrica (opcional)</label>
                            <textarea id="evaluation-rubric" name="evaluation_rubric" rows="3" class="w-full rounded-lg border border-slate-300 p-3 text-sm focus:ring-2 focus:ring-acode-tech">{{ old('evaluation_rubric') }}</textarea>
                        </div>
                    </fieldset>

                    <fieldset x-show="type === 'code'" :disabled="type !== 'code'" class="mt-5 space-y-4">
                        <legend class="mb-3 text-sm font-semibold text-slate-800">Configuración de programación</legend>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="programming-language" class="mb-2 block text-sm font-medium text-slate-800">Lenguaje</label>
                                <input id="programming-language" type="text" name="programming_language" x-model="language" maxlength="40" class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:ring-2 focus:ring-acode-tech">
                            </div>
                            <label class="flex items-center gap-2 pt-0 text-sm text-slate-700 sm:pt-7">
                                <input type="checkbox" name="is_find_error" value="1" {{ old('is_find_error') ? 'checked' : '' }} class="rounded border-slate-300 text-acode-tech">
                                Es de «encontrar el error»
                            </label>
                        </div>
                        <div>
                            <label for="starter-code" class="mb-2 block text-sm font-medium text-slate-800">Código inicial (opcional)</label>
                            <textarea id="starter-code" name="starter_code" x-model="starterCode" rows="5" spellcheck="false" class="w-full rounded-lg border border-slate-300 bg-slate-50 p-3 font-mono text-sm focus:ring-2 focus:ring-acode-tech"></textarea>
                        </div>
                        <div>
                            <label for="code-instructions" class="mb-2 block text-sm font-medium text-slate-800">Instrucciones</label>
                            <textarea id="code-instructions" name="instructions" x-model="instructions" rows="3" class="w-full rounded-lg border border-slate-300 p-3 text-sm focus:ring-2 focus:ring-acode-tech"></textarea>
                        </div>
                        <div>
                            <label for="reference-solution" class="mb-2 block text-sm font-medium text-slate-800">Solución de referencia</label>
                            <textarea id="reference-solution" name="reference_solution" rows="5" spellcheck="false" class="w-full rounded-lg border border-slate-300 bg-slate-50 p-3 font-mono text-sm focus:ring-2 focus:ring-acode-tech">{{ old('reference_solution') }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">La solución no aparece en la vista del estudiante.</p>
                        </div>
                    </fieldset>
                </section>
            </div>

            <aside class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 lg:sticky lg:top-6">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-acode-primary">Vista previa del estudiante</h2>
                    <span class="rounded-full bg-teal-50 px-2 py-1 text-xs font-semibold text-teal-700">Simulación</span>
                </div>
                <p class="mt-2 text-xs text-slate-500">Así se presentaría la pregunta. No guarda respuestas.</p>
                <div class="mt-5 space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-acode-tech" x-text="typeLabel()"></p>
                    <p class="whitespace-pre-wrap break-words text-sm font-semibold text-acode-primary" x-text="stem.trim() || 'Aquí aparecerá el enunciado de la pregunta.'"></p>
                    <div x-show="type === 'multiple_choice'" class="space-y-2">
                        <template x-for="(option, i) in options" :key="option.id">
                            <div class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-700">
                                <span class="mt-0.5 inline-block h-4 w-4 shrink-0 rounded-full border border-slate-400"></span>
                                <span class="break-words" x-text="option.text.trim() || 'Opción ' + (i + 1)"></span>
                            </div>
                        </template>
                    </div>
                    <div x-show="type === 'matching'" class="space-y-3">
                        <p class="text-xs text-slate-500">Relaciona cada concepto con su definición.</p>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="space-y-2">
                                <template x-for="(pair, i) in pairs" :key="pair.id"><div class="min-h-12 rounded-lg border border-slate-200 bg-white p-2 break-words" x-text="pair.concept.trim() || 'Concepto ' + (i + 1)"></div></template>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(pair, i) in previewDefinitions()" :key="pair.id"><div class="min-h-12 rounded-lg border border-slate-200 bg-white p-2 break-words" x-text="pair.definition.trim() || 'Definición ' + (i + 1)"></div></template>
                            </div>
                        </div>
                    </div>
                    <div x-show="type === 'open'">
                        <p class="mb-2 text-xs text-slate-500">Tu respuesta</p>
                        <div class="min-h-28 rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-400">Escribe aquí tu respuesta...</div>
                    </div>
                    <div x-show="type === 'code'" class="space-y-3">
                        <p class="text-xs font-medium text-slate-600" x-text="language || 'Lenguaje de programación'"></p>
                        <p class="whitespace-pre-wrap break-words text-sm text-slate-700" x-text="instructions.trim() || 'Aquí aparecerán las instrucciones.'"></p>
                        <pre x-show="starterCode.trim()" class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><code x-text="starterCode"></code></pre>
                        <div class="min-h-28 rounded-lg border border-slate-200 bg-white p-3 font-mono text-xs text-slate-400">// Espacio para escribir código</div>
                    </div>
                </div>
                <p class="mt-4 text-xs text-slate-500">La presentación final puede variar según la interfaz móvil y web de aplicación.</p>
            </aside>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-5">
            <x-unsaved-changes form-id="create-question-form" :cancel-url="route('questions.index')" />
            <button type="submit" class="rounded-lg bg-acode-primary px-5 py-3 text-sm font-semibold text-white hover:opacity-90">Guardar pregunta</button>
        </div>
    </form>
</div>

<script>
function questionCreator() {
    let nextId = 0;
    const uniqueId = () => ++nextId;
    const initialOptions = {{ \Illuminate\Support\Js::from(old('options', ['', ''])) }};
    const initialConcepts = {{ \Illuminate\Support\Js::from(old('concepts', ['', ''])) }};
    const initialDefinitions = {{ \Illuminate\Support\Js::from(old('definitions', ['', ''])) }};
    const optionTexts = Array.isArray(initialOptions) ? initialOptions : Object.values(initialOptions);
    const conceptTexts = Array.isArray(initialConcepts) ? initialConcepts : Object.values(initialConcepts);
    const definitionTexts = Array.isArray(initialDefinitions) ? initialDefinitions : Object.values(initialDefinitions);
    return {
        type: {{ \Illuminate\Support\Js::from(old('question_type', 'multiple_choice')) }},
        title: {{ \Illuminate\Support\Js::from(old('title', '')) }},
        stem: {{ \Illuminate\Support\Js::from(old('stem', '')) }},
        language: {{ \Illuminate\Support\Js::from(old('programming_language', 'Java')) }},
        starterCode: {{ \Illuminate\Support\Js::from(old('starter_code', '')) }},
        instructions: {{ \Illuminate\Support\Js::from(old('instructions', '')) }},
        options: (optionTexts.length >= 2 ? optionTexts : ['', '']).map(text => ({ id: uniqueId(), text })),
        pairs: Array.from({ length: Math.max(2, conceptTexts.length, definitionTexts.length) }, (_, i) => ({
            id: uniqueId(), concept: conceptTexts[i] || '', definition: definitionTexts[i] || ''
        })),
        correctIndex: Number({{ \Illuminate\Support\Js::from(old('correct_option_index', 0)) }}),
        addOption() { this.options.push({ id: uniqueId(), text: '' }); },
        removeOption(i) {
            if (this.options.length <= 2) return;
            this.options.splice(i, 1);
            if (this.correctIndex === i) this.correctIndex = 0;
            else if (this.correctIndex > i) this.correctIndex--;
        },
        addPair() { this.pairs.push({ id: uniqueId(), concept: '', definition: '' }); },
        removePair(i) { if (this.pairs.length > 2) this.pairs.splice(i, 1); },
        previewDefinitions() { return [...this.pairs].reverse(); },
        typeLabel() {
            return { multiple_choice: 'Opción múltiple', matching: 'Relación de columnas', open: 'Pregunta abierta', code: 'Programación / Código' }[this.type] || '';
        }
    };
}
</script>
@endsection