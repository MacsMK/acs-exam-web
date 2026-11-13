{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/batch-create.blade.php (MPW-V2 §7, §8, Tabla 20) --}}
@extends('layouts.app')

@section('title', 'Captura rápida en lote')

@section('content')
    <div
        class="mx-auto max-w-6xl space-y-6"
        x-data="acodeBatchCreator({
            maxItems: @js((int) ($maxItems ?? 30)),
            quotaSummary: @js($quotaSummary ?? ['is_unlimited' => true, 'remaining' => null, 'used' => 0, 'limit' => null]),
            sharedSubjectTag: @js(old('shared_subject_tag', '')),
            oldItems: @js(old('items')),
            existingSubjects: @js($subjects->values()),
            suggestUrl: @js(route('questions.subjects.suggestions')),
            serverErrors: @js($errors->toArray()),
        })"
    >
        {{-- Navegación superior --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <nav class="flex items-center gap-2 text-xs text-slate-500 select-none" aria-label="Migas de pan">
                <a href="{{ route('questions.index') }}" class="hover:text-acode-tech transition flex items-center gap-1 font-medium">
                    <x-icon name="arrowLeft" class="w-3.5 h-3.5" />
                    <span>Banco de preguntas</span>
                </a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-700">Captura rápida en lote</span>
            </nav>

            <x-button :href="route('questions.create')" variant="neutral" size="sm">
                <x-icon name="edit" class="w-3.5 h-3.5 mr-1.5" />
                Crear reactivo individual
            </x-button>
        </div>

        {{-- Encabezado --}}
        <header class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Captura rápida en lote</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Registra hasta {{ $maxItems ?? 30 }} reactivos en una sola operación y compárteles la misma materia base.
                </p>
            </div>

            {{-- Indicador dinámico de cuota disponible --}}
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 flex items-center gap-3 shadow-xs">
                <div class="text-xs">
                    <span class="block text-slate-500 font-medium">Impacto en cuota</span>
                    <span class="font-bold text-acode-primary" x-show="quotaSummary.is_unlimited">
                        <span x-text="rows.length"></span> reactivos por crear · Plan Ilimitado
                    </span>
                    <span class="font-bold" :class="exceedsQuota ? 'text-acode-danger' : 'text-acode-primary'" x-show="!quotaSummary.is_unlimited">
                        <span x-text="rows.length"></span> en lote / <span x-text="quotaSummary.remaining ?? 0"></span> disponibles
                    </span>
                </div>
                <x-badge x-show="exceedsQuota" x-cloak variant="danger">Excede cuota</x-badge>
                <x-badge x-show="!exceedsQuota" variant="info">
                    <span x-text="rows.length + ' / ' + maxItems"></span>
                </x-badge>
            </div>
        </header>

        {{-- Resumen único de errores de validación --}}
        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-4 text-sm text-acode-danger" role="alert">
                <div class="flex items-start gap-2.5">
                    <x-icon name="error" class="w-5 h-5 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-semibold">Se encontraron errores en la captura en lote:</p>
                        <ul class="mt-1.5 list-disc space-y-0.5 pl-5 text-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <form
            id="batch-create-form"
            method="POST"
            action="{{ route('questions.batch.store') }}"
            @keydown.enter="if ($event.target.tagName === 'INPUT' && !['submit', 'button'].includes($event.target.type)) { $event.preventDefault(); }"
            class="space-y-6"
        >
            @csrf

            {{-- Configuración compartida y controles de filas --}}
            <x-card
                title="Materia compartida y controles del lote"
                subtitle="Los reactivos sin materia individual heredarán automáticamente esta materia compartida."
            >
                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_auto] gap-4 items-start">
                    <x-form.group
                        name="shared_subject_tag"
                        label="Materia compartida del lote (opcional)"
                        help-text="Útil para capturar toda una unidad o parcial de la misma asignatura."
                    >
                        <x-form.input
                            name="shared_subject_tag"
                            list="batch-subjects-datalist"
                            x-model="sharedSubjectTag"
                            @input="handleSubjectInput()"
                            maxlength="100"
                            placeholder="Ej. Estructura de Datos"
                        />
                        <datalist id="batch-subjects-datalist">
                            @foreach ($subjects as $subjectOption)
                                <option value="{{ $subjectOption }}"></option>
                            @endforeach
                        </datalist>

                        <div
                            x-show="subjectSuggestions.length > 0"
                            x-cloak
                            x-transition
                            class="mt-2 rounded-xl border border-acode-warning/60 bg-acode-warning/15 px-3 py-2 text-xs text-slate-800 flex flex-wrap items-center justify-between gap-2"
                        >
                            <span class="font-medium text-amber-950">
                                Ya existe una materia similar: «<span x-text="subjectSuggestions[0]?.name"></span>» ¿Usar esa?
                            </span>
                            <button
                                type="button"
                                @click="applySuggestedSubject(subjectSuggestions[0].name)"
                                class="rounded-lg border border-amber-400 bg-white px-2.5 py-1 text-xs font-semibold text-amber-950 hover:bg-amber-50 transition"
                            >
                                Usar «<span x-text="subjectSuggestions[0]?.name"></span>»
                            </button>
                        </div>
                    </x-form.group>

                    <div class="flex flex-wrap items-center gap-2 lg:pt-6">
                        <x-button
                            type="button"
                            variant="secondary"
                            size="md"
                            @click="addRow()"
                            x-bind:disabled="rows.length >= maxItems"
                        >
                            <x-icon name="add" class="w-4 h-4 mr-1.5" />
                            + Agregar fila
                        </x-button>

                        <x-button
                            type="button"
                            variant="neutral"
                            size="md"
                            @click="duplicateLastRow()"
                            x-bind:disabled="rows.length >= maxItems"
                        >
                            <x-icon name="copy" class="w-4 h-4 mr-1.5" />
                            Duplicar fila anterior
                        </x-button>
                    </div>
                </div>
            </x-card>

            {{-- Filas dinámicas --}}
            <div class="space-y-4">
                <template x-for="(row, rIdx) in rows" :key="row.uid">
                    <div
                        class="rounded-xl border bg-white p-5 shadow-xs space-y-4 transition-colors"
                        :class="rowErrors(rIdx).length > 0 ? 'border-acode-danger/60 ring-1 ring-acode-danger/20' : 'border-slate-200'"
                    >
                        {{-- Barra superior de la fila --}}
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                            <div class="flex items-center gap-2.5">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-acode-primary text-white font-mono text-xs font-bold">
                                    #<span x-text="rIdx + 1"></span>
                                </span>
                                <span class="text-sm font-bold text-acode-primary" x-text="typeLabel(row.question_type)"></span>
                                <span x-show="rowErrors(rIdx).length > 0" x-cloak class="inline-flex items-center gap-1 rounded-full bg-acode-danger/10 px-2.5 py-0.5 text-xs font-semibold text-acode-danger">
                                    <x-icon name="error" class="w-3.5 h-3.5" />
                                    <span x-text="rowErrors(rIdx).length + ' error(es)'"></span>
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-button
                                    type="button"
                                    variant="neutral"
                                    size="sm"
                                    @click="duplicateRow(rIdx)"
                                    x-bind:disabled="rows.length >= maxItems"
                                >
                                    <x-icon name="copy" class="w-3.5 h-3.5 mr-1" />
                                    Copiar
                                </x-button>

                                <x-button
                                    type="button"
                                    variant="danger"
                                    size="sm"
                                    @click="removeRow(rIdx)"
                                    x-bind:disabled="rows.length <= 1"
                                >
                                    <x-icon name="delete" class="w-3.5 h-3.5 mr-1" />
                                    Quitar
                                </x-button>
                            </div>
                        </div>

                        {{-- Errores propios de esta fila --}}
                        <div
                            x-show="rowErrors(rIdx).length > 0"
                            x-cloak
                            class="rounded-lg border border-acode-danger/30 bg-acode-danger/10 px-3 py-2 text-xs text-acode-danger space-y-0.5"
                        >
                            <template x-for="(errMsg, eIdx) in rowErrors(rIdx)" :key="eIdx">
                                <p class="font-medium">• <span x-text="errMsg"></span></p>
                            </template>
                        </div>

                        {{-- Fila 1: Tipo, Título, Puntos, Tiempo y Materia individual opcional --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                            <div class="lg:col-span-3">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tipo *</label>
                                <x-form.select
                                    x-bind:name="'items[' + rIdx + '][question_type]'"
                                    x-model="row.question_type"
                                    @change="notifyFormChanged()"
                                >
                                    <option value="multiple_choice">Opción múltiple</option>
                                    <option value="matching">Relación de columnas</option>
                                    <option value="open">Pregunta abierta</option>
                                    <option value="code">Código fuente</option>
                                </x-form.select>
                            </div>

                            <div class="lg:col-span-4">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Título interno *</label>
                                <x-form.input
                                    type="text"
                                    x-bind:name="'items[' + rIdx + '][title]'"
                                    x-model="row.title"
                                    maxlength="150"
                                    required
                                    placeholder="Identificador en tu banco"
                                    x-bind:class="hasFieldError('items.' + rIdx + '.title') ? '!border-acode-danger' : ''"
                                />
                            </div>

                            <div class="lg:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Materia (opc.)</label>
                                <x-form.input
                                    type="text"
                                    x-bind:name="'items[' + rIdx + '][subject_tag]'"
                                    x-model="row.subject_tag"
                                    maxlength="100"
                                    list="batch-subjects-datalist"
                                    placeholder="Heredar compartida"
                                />
                            </div>

                            <div class="lg:col-span-1">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Pts *</label>
                                <x-form.input
                                    type="number"
                                    x-bind:name="'items[' + rIdx + '][default_points]'"
                                    x-model.number="row.default_points"
                                    step="0.01"
                                    min="0.1"
                                    max="100"
                                    required
                                />
                            </div>

                            <div class="lg:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tiempo (s) *</label>
                                <x-form.input
                                    type="number"
                                    x-bind:name="'items[' + rIdx + '][default_time_seconds]'"
                                    x-model.number="row.default_time_seconds"
                                    min="5"
                                    max="3600"
                                    step="1"
                                    required
                                />
                            </div>
                        </div>

                        {{-- Enunciado --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Enunciado para el estudiante *</label>
                            <x-form.textarea
                                rows="2"
                                minlength="3"
                                maxlength="5000"
                                required
                                x-bind:name="'items[' + rIdx + '][stem]'"
                                x-model="row.stem"
                                placeholder="Escribe el planteamiento exacto que leerá el estudiante…"
                                x-bind:class="hasFieldError('items.' + rIdx + '.stem') ? '!border-acode-danger' : ''"
                            ></x-form.textarea>
                        </div>

                        {{-- Bloque compacto polimórfico: Opción múltiple --}}
                        <fieldset x-show="row.question_type === 'multiple_choice'" :disabled="row.question_type !== 'multiple_choice'" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-acode-primary">Opciones de respuesta (marca la correcta)</span>
                                <x-button type="button" variant="neutral" size="sm" @click="addOption(rIdx)" x-bind:disabled="row.options.length >= 6">
                                    <x-icon name="add" class="w-3.5 h-3.5 mr-1" />
                                    Opción
                                </x-button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <template x-for="(opt, oIdx) in row.options" :key="opt.uid">
                                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-2">
                                        <input
                                            type="radio"
                                            :name="'items[' + rIdx + '][correct_option_index]'"
                                            :value="oIdx"
                                            x-model.number="row.correct_option_index"
                                            class="h-4 w-4 text-acode-accent focus:ring-acode-tech shrink-0"
                                        />
                                        <span class="text-xs font-bold text-slate-500 w-4 shrink-0" x-text="String.fromCharCode(65 + oIdx)"></span>
                                        <x-form.input
                                            type="text"
                                            size="sm"
                                            x-bind:name="'items[' + rIdx + '][options][' + oIdx + ']'"
                                            x-model="opt.text"
                                            maxlength="500"
                                            placeholder="Texto de la opción"
                                            required
                                        />
                                        <button
                                            type="button"
                                            @click="removeOption(rIdx, oIdx)"
                                            :disabled="row.options.length <= 2"
                                            class="text-acode-danger hover:opacity-75 disabled:opacity-30 p-1"
                                            aria-label="Quitar opción"
                                        >
                                            <x-icon name="xMark" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </fieldset>

                        {{-- Bloque compacto polimórfico: Relación de columnas --}}
                        <fieldset x-show="row.question_type === 'matching'" :disabled="row.question_type !== 'matching'" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-acode-primary">Pares Concepto ↔ Definición (2 a 8)</span>
                                <x-button type="button" variant="neutral" size="sm" @click="addPair(rIdx)" x-bind:disabled="row.pairs.length >= 8">
                                    <x-icon name="add" class="w-3.5 h-3.5 mr-1" />
                                    Par
                                </x-button>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(pair, pIdx) in row.pairs" :key="pair.uid">
                                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-center">
                                        <x-form.input
                                            type="text"
                                            size="sm"
                                            x-bind:name="'items[' + rIdx + '][concepts][' + pIdx + ']'"
                                            x-model="pair.concept"
                                            maxlength="500"
                                            placeholder="Concepto"
                                            required
                                        />
                                        <x-form.input
                                            type="text"
                                            size="sm"
                                            x-bind:name="'items[' + rIdx + '][definitions][' + pIdx + ']'"
                                            x-model="pair.definition"
                                            maxlength="500"
                                            placeholder="Definición"
                                            required
                                        />
                                        <x-button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            @click="removePair(rIdx, pIdx)"
                                            x-bind:disabled="row.pairs.length <= 2"
                                        >
                                            Quitar
                                        </x-button>
                                    </div>
                                </template>
                            </div>
                        </fieldset>

                        {{-- Bloque compacto polimórfico: Pregunta abierta --}}
                        <fieldset x-show="row.question_type === 'open'" :disabled="row.question_type !== 'open'" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Respuesta modelo canónica *</label>
                                <x-form.textarea
                                    rows="2"
                                    maxlength="10000"
                                    x-bind:name="'items[' + rIdx + '][reference_answer]'"
                                    x-model="row.reference_answer"
                                    x-bind:required="row.question_type === 'open'"
                                    placeholder="Respuesta ideal de referencia"
                                ></x-form.textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Rúbrica (opcional)</label>
                                <x-form.textarea
                                    rows="2"
                                    maxlength="10000"
                                    x-bind:name="'items[' + rIdx + '][evaluation_rubric]'"
                                    x-model="row.evaluation_rubric"
                                    placeholder="Criterios clave de calificación"
                                ></x-form.textarea>
                            </div>
                        </fieldset>

                        {{-- Bloque compacto polimórfico: Código fuente --}}
                        <fieldset x-show="row.question_type === 'code'" :disabled="row.question_type !== 'code'" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Lenguaje *</label>
                                    <x-form.select
                                        size="sm"
                                        x-bind:name="'items[' + rIdx + '][programming_language]'"
                                        x-model="row.programming_language"
                                        x-bind:required="row.question_type === 'code'"
                                    >
                                        <option value="Python">Python</option>
                                        <option value="JavaScript">JavaScript</option>
                                        <option value="TypeScript">TypeScript</option>
                                        <option value="PHP">PHP</option>
                                        <option value="Java">Java</option>
                                        <option value="SQL">SQL</option>
                                        <option value="C">C</option>
                                        <option value="C++">C++</option>
                                        <option value="C#">C#</option>
                                        <option value="Go">Go</option>
                                    </x-form.select>
                                </div>
                                <label class="flex items-center gap-2 text-xs font-semibold text-acode-primary pt-4 cursor-pointer">
                                    <input type="hidden" :name="'items[' + rIdx + '][is_find_error]'" value="0">
                                    <input
                                        type="checkbox"
                                        :name="'items[' + rIdx + '][is_find_error]'"
                                        value="1"
                                        x-model="row.is_find_error"
                                        class="h-4 w-4 rounded border-slate-300 text-acode-tech"
                                    />
                                    <span>Modo «Encuentra y corrige el error»</span>
                                </label>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Instrucciones técnicas (opcional)</label>
                                <x-form.textarea
                                    rows="2"
                                    maxlength="5000"
                                    x-bind:name="'items[' + rIdx + '][instructions]'"
                                    x-model="row.instructions"
                                    placeholder="Restricciones o formato esperado…"
                                ></x-form.textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                                        Código inicial <span x-show="row.is_find_error" class="text-acode-danger">*</span>
                                    </label>
                                    <x-form.textarea
                                        rows="3"
                                        :mono="true"
                                        maxlength="10000"
                                        x-bind:name="'items[' + rIdx + '][starter_code]'"
                                        x-model="row.starter_code"
                                        x-bind:required="row.question_type === 'code' && row.is_find_error"
                                        placeholder="// Código base o defectuoso"
                                    ></x-form.textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Solución canónica *</label>
                                    <x-form.textarea
                                        rows="3"
                                        :mono="true"
                                        maxlength="10000"
                                        x-bind:name="'items[' + rIdx + '][reference_solution]'"
                                        x-model="row.reference_solution"
                                        x-bind:required="row.question_type === 'code'"
                                        placeholder="// Solución correcta"
                                    ></x-form.textarea>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </template>
            </div>

            {{-- Barra inferior de guardado --}}
            <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-xs">
                <x-unsaved-changes form-id="batch-create-form" :cancel-url="route('questions.index')" />

                <div class="flex flex-wrap items-center justify-end gap-2.5">
                    <x-button
                        type="button"
                        variant="secondary"
                        size="md"
                        @click="addRow()"
                        x-bind:disabled="rows.length >= maxItems"
                    >
                        <x-icon name="add" class="w-4 h-4 mr-1.5" />
                        + Agregar fila
                    </x-button>

                    <x-button type="submit" variant="primary" size="md">
                        <x-icon name="save" class="w-4 h-4 mr-1.5" />
                        Guardar lote (<span x-text="rows.length"></span>)
                    </x-button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            window.acodeBatchCreator = function (config) {
                let uidCounter = 1;
                const nextUid = () => 'buid-' + (uidCounter++);

                const makeDefaultRow = (overrides = {}) => ({
                    uid: nextUid(),
                    question_type: overrides.question_type || 'multiple_choice',
                    title: overrides.title || '',
                    subject_tag: overrides.subject_tag || '',
                    stem: overrides.stem || '',
                    default_points: Number(overrides.default_points ?? 1),
                    default_time_seconds: Number(overrides.default_time_seconds ?? 60),
                    options: Array.isArray(overrides.options) && overrides.options.length >= 2
                        ? overrides.options.map((t) => ({ uid: nextUid(), text: String(t ?? '') }))
                        : [{ uid: nextUid(), text: '' }, { uid: nextUid(), text: '' }],
                    correct_option_index: Number(overrides.correct_option_index ?? 0),
                    pairs: Array.isArray(overrides.pairs) && overrides.pairs.length >= 2
                        ? overrides.pairs.map((p) => ({
                            uid: nextUid(),
                            concept: String(p?.concept ?? ''),
                            definition: String(p?.definition ?? ''),
                        }))
                        : [
                            { uid: nextUid(), concept: '', definition: '' },
                            { uid: nextUid(), concept: '', definition: '' },
                        ],
                    reference_answer: overrides.reference_answer || '',
                    evaluation_rubric: overrides.evaluation_rubric || '',
                    programming_language: overrides.programming_language || 'Python',
                    is_find_error: Boolean(overrides.is_find_error),
                    instructions: overrides.instructions || '',
                    starter_code: overrides.starter_code || '',
                    reference_solution: overrides.reference_solution || '',
                });

                const seedRows = Array.isArray(config.oldItems) && config.oldItems.length > 0
                    ? config.oldItems.map((item) => {
                        const concepts = Array.isArray(item?.concepts) ? item.concepts : [];
                        const definitions = Array.isArray(item?.definitions) ? item.definitions : [];
                        const pairs = concepts.length >= 2
                            ? concepts.map((c, idx) => ({ concept: c, definition: definitions[idx] ?? '' }))
                            : null;

                        return makeDefaultRow({
                            question_type: item?.question_type,
                            title: item?.title,
                            subject_tag: item?.subject_tag,
                            stem: item?.stem,
                            default_points: item?.default_points,
                            default_time_seconds: item?.default_time_seconds,
                            options: item?.options,
                            correct_option_index: item?.correct_option_index,
                            pairs,
                            reference_answer: item?.reference_answer,
                            evaluation_rubric: item?.evaluation_rubric,
                            programming_language: item?.programming_language,
                            is_find_error: item?.is_find_error === '1' || item?.is_find_error === true || item?.is_find_error === 1,
                            instructions: item?.instructions,
                            starter_code: item?.starter_code,
                            reference_solution: item?.reference_solution,
                        });
                    })
                    : [makeDefaultRow()];

                return {
                    maxItems: Number(config.maxItems || 30),
                    quotaSummary: config.quotaSummary || { is_unlimited: true, remaining: null },
                    sharedSubjectTag: config.sharedSubjectTag || '',
                    rows: seedRows,
                    existingSubjects: Array.isArray(config.existingSubjects) ? config.existingSubjects : [],
                    suggestUrl: config.suggestUrl || '',
                    subjectSuggestions: [],
                    debounceTimer: null,
                    serverErrors: config.serverErrors || {},

                    get exceedsQuota() {
                        if (this.quotaSummary.is_unlimited) return false;
                        return this.rows.length > Number(this.quotaSummary.remaining ?? 0);
                    },

                    notifyFormChanged() {
                        const form = document.getElementById('batch-create-form');
                        if (form) {
                            form.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    },

                    typeLabel(type) {
                        return {
                            multiple_choice: 'Opción múltiple',
                            matching: 'Relación de columnas',
                            open: 'Pregunta abierta',
                            code: 'Código fuente',
                        }[type] || type;
                    },

                    hasFieldError(key) {
                        return Array.isArray(this.serverErrors[key]) && this.serverErrors[key].length > 0;
                    },

                    rowErrors(rowIndex) {
                        const prefix = 'items.' + rowIndex + '.';
                        const list = [];
                        Object.keys(this.serverErrors).forEach((key) => {
                            if (key.startsWith(prefix) && Array.isArray(this.serverErrors[key])) {
                                this.serverErrors[key].forEach((msg) => list.push(msg));
                            }
                        });
                        return list;
                    },

                    addRow() {
                        if (this.rows.length < this.maxItems) {
                            this.rows.push(makeDefaultRow({
                                question_type: this.rows[this.rows.length - 1]?.question_type || 'multiple_choice',
                            }));
                            this.notifyFormChanged();
                        }
                    },

                    duplicateRow(index) {
                        if (this.rows.length >= this.maxItems || !this.rows[index]) return;
                        const src = this.rows[index];
                        this.rows.push(makeDefaultRow({
                            question_type: src.question_type,
                            title: src.title ? src.title + ' (copia)' : '',
                            subject_tag: src.subject_tag,
                            stem: src.stem,
                            default_points: src.default_points,
                            default_time_seconds: src.default_time_seconds,
                            options: src.options.map((o) => o.text),
                            correct_option_index: src.correct_option_index,
                            pairs: src.pairs.map((p) => ({ concept: p.concept, definition: p.definition })),
                            reference_answer: src.reference_answer,
                            evaluation_rubric: src.evaluation_rubric,
                            programming_language: src.programming_language,
                            is_find_error: src.is_find_error,
                            instructions: src.instructions,
                            starter_code: src.starter_code,
                            reference_solution: src.reference_solution,
                        }));
                        this.notifyFormChanged();
                    },

                    duplicateLastRow() {
                        this.duplicateRow(this.rows.length - 1);
                    },

                    removeRow(index) {
                        if (this.rows.length > 1) {
                            this.rows.splice(index, 1);
                            this.notifyFormChanged();
                        }
                    },

                    addOption(rowIndex) {
                        const row = this.rows[rowIndex];
                        if (row && row.options.length < 6) {
                            row.options.push({ uid: nextUid(), text: '' });
                            this.notifyFormChanged();
                        }
                    },

                    removeOption(rowIndex, optIndex) {
                        const row = this.rows[rowIndex];
                        if (row && row.options.length > 2) {
                            row.options.splice(optIndex, 1);
                            if (row.correct_option_index >= row.options.length) {
                                row.correct_option_index = row.options.length - 1;
                            }
                            this.notifyFormChanged();
                        }
                    },

                    addPair(rowIndex) {
                        const row = this.rows[rowIndex];
                        if (row && row.pairs.length < 8) {
                            row.pairs.push({ uid: nextUid(), concept: '', definition: '' });
                            this.notifyFormChanged();
                        }
                    },

                    removePair(rowIndex, pairIndex) {
                        const row = this.rows[rowIndex];
                        if (row && row.pairs.length > 2) {
                            row.pairs.splice(pairIndex, 1);
                            this.notifyFormChanged();
                        }
                    },

                    normalizeText(val) {
                        return String(val ?? '')
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .trim()
                            .replace(/\s+/g, ' ')
                            .toLowerCase();
                    },

                    handleSubjectInput() {
                        const raw = String(this.sharedSubjectTag ?? '').trim();
                        if (raw.length < 2) {
                            this.subjectSuggestions = [];
                            if (this.debounceTimer) clearTimeout(this.debounceTimer);
                            return;
                        }
                        if (this.debounceTimer) clearTimeout(this.debounceTimer);
                        this.debounceTimer = setTimeout(async () => {
                            try {
                                const res = await fetch(this.suggestUrl + '?name=' + encodeURIComponent(raw), {
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                });
                                if (!res.ok) return;
                                const payload = await res.json();
                                const needle = this.normalizeText(this.sharedSubjectTag);
                                this.subjectSuggestions = (payload.suggestions || [])
                                    .filter((s) => this.normalizeText(s.name) !== needle)
                                    .slice(0, 3);
                            } catch (e) {
                                // Ignora fallo de red en autocompletado
                            }
                        }, 280);
                    },

                    applySuggestedSubject(name) {
                        this.sharedSubjectTag = name;
                        this.subjectSuggestions = [];
                        this.notifyFormChanged();
                    },
                };
            };
        </script>
    @endpush
@endsection
