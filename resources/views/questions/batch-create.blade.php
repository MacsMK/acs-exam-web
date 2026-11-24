{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/batch-create.blade.php (MPW-V3 §7, §8, Tabla 10) --}}
@extends('layouts.app')

@section('title', 'Captura rápida en lote')

@section('content')
    <div
        class="space-y-4 sm:space-y-5"
        x-data="acodeBatchCreator({
            maxItems: @js((int) ($maxItems ?? 30)),
            quotaSummary: @js($quotaSummary ?? ['is_unlimited' => true, 'remaining' => null, 'used' => 0, 'limit' => null]),
            sharedSubjectTag: @js(old('shared_subject_tag', '')),
            oldItems: @js(old('items')),
            suggestUrl: @js(route('questions.subjects.suggestions')),
            serverErrors: @js($errors->toArray()),
        })"
    >
        {{-- Cabecera institucional --}}
        <x-page-header
            title="Captura rápida en lote"
            :institutional="true"
            :breadcrumbs="[
                ['label' => 'Banco de preguntas', 'href' => route('questions.index')],
                ['label' => 'Captura rápida en lote'],
            ]"
        >
            <x-slot:badge>
                <x-badge variant="info">
                    <span x-text="'En lote: ' + rows.length + ' de ' + maxItems"></span>
                </x-badge>
            </x-slot:badge>

            <x-slot:actions>
                <x-button :href="route('questions.index')" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>

                <x-button :href="route('questions.create')" variant="neutral" size="md" data-cancel-form="batch-create-form">
                    <x-icon name="edit" class="w-4 h-4" />
                    Crear reactivo individual
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- Resumen único de errores de validación --}}
        @if ($errors->any())
            <div
                x-show="globalErrors.length || rows.some(row => rowErrorCount(row)) || sharedSubjectError"
                x-init="if (globalErrors.length || rows.some(row => rowErrorCount(row)) || sharedSubjectError) $nextTick(() => $el.focus())"
                x-cloak
                tabindex="-1"
                aria-labelledby="batch-error-title"
                role="region"
                class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-4 text-sm text-acode-danger-dark focus-visible:ring-2 focus-visible:ring-acode-danger"
            >
                <div class="flex items-start gap-2.5">
                    <x-icon name="error" class="w-5 h-5 shrink-0 mt-0.5" />
                    <div>
                        <p id="batch-error-title" class="font-semibold">Revisa los siguientes datos antes de guardar</p>
                        <ul class="mt-1.5 list-disc space-y-0.5 pl-5 text-xs">
                            <template x-for="(message, index) in globalErrors" :key="index">
                                <li x-text="message"></li>
                            </template>
                            <li x-show="sharedSubjectError">
                                <a href="#shared_subject_tag" class="underline" @click.prevent="focusField('shared_subject_tag')">Materia compartida del lote</a>
                            </li>
                            <template x-for="(row, rIdx) in rows" :key="row.uid">
                                <li x-show="rowErrorCount(row)">
                                    <a :href="'#' + row.uid" class="underline" @click.prevent="focusRowError(row)" x-text="'Fila ' + (rIdx + 1) + ': ' + (row.title || typeLabel(row.question_type))"></a>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <form
            id="batch-create-form"
            method="POST"
            action="{{ route('questions.batch.store') }}"
            @submit="submitBatch($event)"
            @pageshow.window="saving = false"
            @keydown.enter="if ($event.target.tagName === 'INPUT' && !['submit', 'button'].includes($event.target.type)) { $event.preventDefault(); }"
            class="space-y-4"
        >
            @csrf

            {{-- Configuración compartida y controles de filas --}}
            <x-card padding="px-4 py-3 sm:px-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <x-form.group class="lg:max-w-xl">
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <label for="shared_subject_tag" class="text-[13px] font-medium text-slate-800">Materia compartida del lote (opcional)</label>
                            <p id="batch-shared-subject-help" class="text-xs text-slate-500">Se aplica a las filas sin materia propia.</p>
                        </div>
                        <x-form.input
                            id="shared_subject_tag"
                            x-bind:name="'shared_subject_tag'"
                            list="batch-subjects-datalist"
                            x-model="sharedSubjectTag"
                            @input="sharedSubjectError = ''; handleSubjectInput()"
                            x-bind:aria-invalid="sharedSubjectError ? 'true' : null"
                            x-bind:aria-describedby="sharedSubjectError ? 'batch-shared-subject-help batch-shared-subject-error' : 'batch-shared-subject-help'"
                            x-bind:class="sharedSubjectError ? '!border-acode-danger' : ''"
                            maxlength="100"
                            placeholder="Ej. Estructura de Datos"
                            class="placeholder:!text-slate-500"
                        />
                        <p id="batch-shared-subject-error" x-show="sharedSubjectError" x-text="sharedSubjectError" x-cloak class="text-xs text-acode-danger"></p>
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
                            <span class="font-medium text-acode-primary">
                                Ya existe una materia similar: «<span x-text="subjectSuggestions[0]?.name"></span>» ¿Usar esa?
                            </span>
                            <x-button
                                type="button"
                                variant="neutral"
                                size="sm"
                                @click="applySuggestedSubject(subjectSuggestions[0].name)"
                            >
                                Aceptar «<span x-text="subjectSuggestions[0]?.name"></span>»
                            </x-button>
                        </div>
                    </x-form.group>

                    <div class="flex shrink-0 flex-wrap items-center gap-2 lg:pt-6">
                        <x-button
                            type="button"
                            variant="secondary"
                            size="md"
                            @click="addRow()"
                            x-bind:disabled="rows.length >= maxItems"
                        >
                            <x-icon name="add" class="w-4 h-4" />
                            Agregar fila
                        </x-button>

                        <x-button
                            type="button"
                            variant="neutral"
                            size="md"
                            @click="duplicateLastRow()"
                            x-bind:disabled="rows.length >= maxItems"
                        >
                            <x-icon name="copy" class="w-4 h-4" />
                            Copiar última fila
                        </x-button>
                    </div>
                </div>
            </x-card>

            {{-- Filas dinámicas --}}
            <div class="space-y-4">
                <template x-for="(row, rIdx) in rows" :key="row.uid">
                    <div
                        class="space-y-3 rounded-xl border bg-white p-4 shadow-sm transition-colors sm:px-5"
                        :id="row.uid"
                        tabindex="-1"
                        :class="rowErrorCount(row) > 0 ? 'border-acode-danger/60 ring-1 ring-acode-danger/20' : 'border-slate-200'"
                    >
                        {{-- Barra superior de la fila --}}
                        <div class="flex flex-wrap items-center justify-between gap-2.5 border-b border-slate-100 pb-3">
                            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2.5">
                                <span aria-hidden="true" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-acode-primary px-1 text-xs font-semibold text-white">
                                    #<span x-text="rIdx + 1"></span>
                                </span>
                                <h2 class="min-w-0 flex-1 truncate text-xl font-semibold leading-7 text-acode-primary" :title="row.title || null">
                                    <span class="sr-only" x-text="'Fila ' + (rIdx + 1) + ': '"></span>
                                    <span x-text="String(row.title).trim() || typeLabel(row.question_type)"></span>
                                </h2>
                                <span x-show="rowErrorCount(row) > 0" x-cloak class="inline-flex items-center gap-1 rounded-full bg-acode-danger/10 px-2.5 py-0.5 text-xs font-semibold text-acode-danger-dark">
                                    <x-icon name="error" class="w-3.5 h-3.5" />
                                    <span x-text="rowErrorCount(row) === 1 ? '1 error' : rowErrorCount(row) + ' errores'"></span>
                                </span>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-button
                                    type="button"
                                    variant="neutral"
                                    size="sm"
                                    @click="duplicateRow(rIdx)"
                                    x-bind:disabled="rows.length >= maxItems"
                                >
                                    <x-icon name="copy" class="w-3.5 h-3.5" />
                                    Copiar
                                </x-button>

                                <x-button
                                    type="button"
                                    variant="danger"
                                    size="sm"
                                    @click="removeRow(row)"
                                    x-bind:disabled="rows.length <= 1"
                                >
                                    <x-icon name="delete" class="w-3.5 h-3.5" />
                                    Eliminar
                                </x-button>
                            </div>
                        </div>

                        <p x-bind="errorAttributes(row, 'row')" x-cloak class="text-xs text-acode-danger"></p>

                        {{-- Identificación del reactivo --}}
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="min-w-0">
                                <label :for="fieldId(row, 'question_type')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Tipo *</label>
                                <x-form.select
                                    x-bind:name="'items[' + rIdx + '][question_type]'"
                                    x-bind="fieldAttributes(row, 'question_type')"
                                    x-model="row.question_type"
                                    @change="clearTypeErrors(row); notifyFormChanged()"
                                >
                                    <option value="multiple_choice">Opción múltiple</option>
                                    <option value="matching">Relación de columnas</option>
                                    <option value="open">Pregunta abierta</option>
                                    <option value="code">Código fuente</option>
                                </x-form.select>
                                <p x-bind="errorAttributes(row, 'question_type')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                            </div>

                            <div class="min-w-0 lg:col-span-2">
                                <label :for="fieldId(row, 'title')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Título interno *</label>
                                <x-form.input
                                    type="text"
                                    x-bind:name="'items[' + rIdx + '][title]'"
                                    x-bind="fieldAttributes(row, 'title')"
                                    x-model="row.title"
                                    maxlength="150"
                                    required
                                    placeholder="Identificador en tu banco"
                                    class="placeholder:!text-slate-500"
                                />
                                <p x-bind="errorAttributes(row, 'title')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                            </div>
                        </div>

                        {{-- Enunciado --}}
                        <div>
                            <label :for="fieldId(row, 'stem')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Enunciado para el estudiante *</label>
                            <x-form.textarea
                                rows="2"
                                minlength="3"
                                maxlength="5000"
                                required
                                x-bind:name="'items[' + rIdx + '][stem]'"
                                x-bind="fieldAttributes(row, 'stem')"
                                x-model="row.stem"
                                placeholder="Escribe el planteamiento exacto que leerá el estudiante…"
                                class="placeholder:!text-slate-500"
                            ></x-form.textarea>
                            <p x-bind="errorAttributes(row, 'stem')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                        </div>

                        {{-- Bloque compacto polimórfico: Opción múltiple --}}
                        <fieldset x-show="row.question_type === 'multiple_choice'" :disabled="row.question_type !== 'multiple_choice'" x-bind="fieldAttributes(row, 'options')" tabindex="-1" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 space-y-2.5">
                            <legend class="sr-only">Opciones de respuesta. Agrega de 2 a 6 y marca la correcta.</legend>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div aria-hidden="true">
                                    <p class="text-sm font-semibold text-acode-primary">Opciones de respuesta</p>
                                    <p class="text-xs text-slate-500">De 2 a 6 opciones. Marca la correcta.</p>
                                </div>
                                <x-button type="button" variant="neutral" size="sm" @click="addOption(rIdx)" x-bind:disabled="row.options.length >= 6">
                                    <x-icon name="add" class="w-3.5 h-3.5" />
                                    Agregar opción
                                </x-button>
                            </div>
                            <p x-bind="errorAttributes(row, 'options')" x-cloak class="text-xs text-acode-danger-dark"></p>
                            <p x-bind="errorAttributes(row, 'correct_option_index')" x-cloak class="text-xs text-acode-danger-dark"></p>
                            <p :id="fieldId(row, 'choose-option')" x-text="row.correct_option_index === null && !hasError(row, 'correct_option_index') ? 'Marca la opción correcta antes de guardar.' : ''" role="status" class="text-xs text-acode-danger-dark empty:sr-only"></p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <template x-for="(opt, oIdx) in row.options" :key="opt.uid">
                                    <div class="flex items-start gap-2 rounded-lg border bg-white px-2.5 py-2" :class="row.correct_option_index === oIdx ? 'border-acode-tech' : 'border-slate-200'">
                                        <label class="flex h-8 shrink-0 cursor-pointer items-center gap-2 text-xs font-bold text-slate-600">
                                            <input
                                                type="radio"
                                                :id="oIdx === 0 ? fieldId(row, 'correct_option_index') : null"
                                                :aria-label="'Marca la opción ' + optionLetter(oIdx) + ' como correcta'"
                                                :aria-invalid="row.correct_option_index === null || hasError(row, 'correct_option_index') ? 'true' : null"
                                                :aria-describedby="hasError(row, 'correct_option_index') ? fieldId(row, 'correct_option_index') + '-error' : (row.correct_option_index === null ? fieldId(row, 'choose-option') : null)"
                                                x-effect="$el.name = 'items[' + rIdx + '][correct_option_index]'; $el.value = oIdx; $el.checked = row.correct_option_index === oIdx"
                                                @change="selectOption(row, oIdx)"
                                                class="h-4 w-4 text-acode-tech focus:ring-acode-tech shrink-0"
                                                required
                                            />
                                            <span aria-hidden="true" class="w-4" x-text="optionLetter(oIdx)"></span>
                                            <x-badge variant="success" x-show="row.correct_option_index === oIdx" x-cloak aria-hidden="true" class="hidden md:inline-flex">Correcta</x-badge>
                                        </label>
                                        <div class="min-w-0 flex-1">
                                            <x-form.input
                                                type="text"
                                                size="sm"
                                                x-bind:name="'items[' + rIdx + '][options][' + oIdx + ']'"
                                                x-bind="fieldAttributes(row, 'options-' + opt.uid)"
                                                x-bind:aria-label="'Texto de la opción ' + optionLetter(oIdx)"
                                                x-model="opt.text"
                                                maxlength="500"
                                                placeholder="Texto de la opción"
                                                class="placeholder:!text-slate-500"
                                                required
                                            />
                                            <p x-bind="errorAttributes(row, 'options-' + opt.uid)" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                                        </div>
                                        <x-button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            :icon-only="true"
                                            x-on:click="removeOption(row, opt)"
                                            x-bind:disabled="row.options.length <= 2"
                                            class="shrink-0"
                                            title="Eliminar opción"
                                            x-bind:aria-label="'Eliminar opción ' + optionLetter(oIdx)"
                                        >
                                            <x-icon name="xMark" class="w-4 h-4" />
                                        </x-button>
                                    </div>
                                </template>
                            </div>
                        </fieldset>

                        {{-- Bloque compacto polimórfico: Relación de columnas --}}
                        <fieldset x-show="row.question_type === 'matching'" :disabled="row.question_type !== 'matching'" :aria-invalid="hasError(row, 'concepts') || hasError(row, 'definitions') ? 'true' : null" :aria-describedby="fieldId(row, 'concepts') + '-error ' + fieldId(row, 'definitions') + '-error'" tabindex="-1" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 space-y-2.5">
                            <legend class="sr-only">Pares Concepto ↔ Definición (2 a 8)</legend>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span aria-hidden="true" class="text-sm font-semibold text-acode-primary">Pares Concepto ↔ Definición (2 a 8)</span>
                                <x-button type="button" variant="neutral" size="sm" @click="addPair(rIdx)" x-bind:disabled="row.pairs.length >= 8">
                                    <x-icon name="add" class="w-3.5 h-3.5" />
                                    Agregar par
                                </x-button>
                            </div>
                            <p x-bind="errorAttributes(row, 'concepts')" x-cloak class="text-xs text-acode-danger-dark"></p>
                            <p x-bind="errorAttributes(row, 'definitions')" x-cloak class="text-xs text-acode-danger-dark"></p>
                            <div class="space-y-2">
                                <template x-for="(pair, pIdx) in row.pairs" :key="pair.uid">
                                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-start">
                                        <div>
                                            <x-form.input
                                                type="text"
                                                size="sm"
                                                x-bind:name="'items[' + rIdx + '][concepts][' + pIdx + ']'"
                                                x-bind="fieldAttributes(row, 'concepts-' + pair.uid)"
                                                x-bind:aria-label="'Concepto ' + (pIdx + 1)"
                                                x-model="pair.concept"
                                                maxlength="500"
                                                placeholder="Concepto"
                                                class="placeholder:!text-slate-500"
                                                required
                                            />
                                            <p x-bind="errorAttributes(row, 'concepts-' + pair.uid)" x-cloak class="mt-1 text-xs text-acode-danger-dark"></p>
                                        </div>
                                        <div>
                                            <x-form.input
                                                type="text"
                                                size="sm"
                                                x-bind:name="'items[' + rIdx + '][definitions][' + pIdx + ']'"
                                                x-bind="fieldAttributes(row, 'definitions-' + pair.uid)"
                                                x-bind:aria-label="'Definición ' + (pIdx + 1)"
                                                x-model="pair.definition"
                                                maxlength="500"
                                                placeholder="Definición"
                                                class="placeholder:!text-slate-500"
                                                required
                                            />
                                            <p x-bind="errorAttributes(row, 'definitions-' + pair.uid)" x-cloak class="mt-1 text-xs text-acode-danger-dark"></p>
                                        </div>
                                        <x-button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            @click="removePair(row, pair)"
                                            x-bind:disabled="row.pairs.length <= 2"
                                        >
                                            Eliminar
                                        </x-button>
                                    </div>
                                </template>
                            </div>
                        </fieldset>

                        {{-- Bloque compacto polimórfico: Pregunta abierta --}}
                        <fieldset x-show="row.question_type === 'open'" :disabled="row.question_type !== 'open'" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <legend class="sr-only">Pregunta abierta</legend>
                            <div>
                                <label :for="fieldId(row, 'reference_answer')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Respuesta modelo canónica *</label>
                                <x-form.textarea
                                    rows="2"
                                    maxlength="10000"
                                    x-bind:name="'items[' + rIdx + '][reference_answer]'"
                                    x-bind="fieldAttributes(row, 'reference_answer')"
                                    x-model="row.reference_answer"
                                    x-bind:required="row.question_type === 'open'"
                                    placeholder="Respuesta ideal de referencia"
                                    class="placeholder:!text-slate-500"
                                ></x-form.textarea>
                                <p x-bind="errorAttributes(row, 'reference_answer')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                            </div>
                            <div>
                                <label :for="fieldId(row, 'evaluation_rubric')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Rúbrica (opcional)</label>
                                <x-form.textarea
                                    rows="2"
                                    maxlength="10000"
                                    x-bind:name="'items[' + rIdx + '][evaluation_rubric]'"
                                    x-bind="fieldAttributes(row, 'evaluation_rubric')"
                                    x-model="row.evaluation_rubric"
                                    placeholder="Criterios clave de calificación"
                                    class="placeholder:!text-slate-500"
                                ></x-form.textarea>
                                <p x-bind="errorAttributes(row, 'evaluation_rubric')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                            </div>
                        </fieldset>

                        {{-- Bloque compacto polimórfico: Código fuente --}}
                        <fieldset x-show="row.question_type === 'code'" :disabled="row.question_type !== 'code'" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 space-y-3">
                            <legend class="sr-only">Código fuente</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                                <div>
                                    <label :for="fieldId(row, 'programming_language')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Lenguaje *</label>
                                    <x-form.select
                                        size="sm"
                                        x-bind:name="'items[' + rIdx + '][programming_language]'"
                                        x-bind="fieldAttributes(row, 'programming_language')"
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
                                    <p x-bind="errorAttributes(row, 'programming_language')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                                </div>
                                <div>
                                    <label class="flex items-center gap-2 text-xs font-medium text-slate-800 pt-4 cursor-pointer">
                                        <input type="hidden" :name="'items[' + rIdx + '][is_find_error]'" value="0">
                                        <input
                                            type="checkbox"
                                            :name="'items[' + rIdx + '][is_find_error]'"
                                            x-bind="fieldAttributes(row, 'is_find_error')"
                                            value="1"
                                            x-model="row.is_find_error"
                                            class="h-4 w-4 rounded-md border-slate-300 text-acode-tech"
                                        />
                                        <span>Modo «Encuentra y corrige el error»</span>
                                    </label>
                                    <p x-bind="errorAttributes(row, 'is_find_error')" x-cloak class="mt-1 text-xs text-acode-danger-dark"></p>
                                </div>
                            </div>

                            <div>
                                <label :for="fieldId(row, 'instructions')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Instrucciones técnicas (opcional)</label>
                                <x-form.textarea
                                    rows="2"
                                    maxlength="5000"
                                    x-bind:name="'items[' + rIdx + '][instructions]'"
                                    x-bind="fieldAttributes(row, 'instructions')"
                                    x-model="row.instructions"
                                    placeholder="Restricciones o formato esperado…"
                                    class="placeholder:!text-slate-500"
                                ></x-form.textarea>
                                <p x-bind="errorAttributes(row, 'instructions')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label :for="fieldId(row, 'starter_code')" class="mb-1.5 block text-[13px] font-medium text-slate-800">
                                        Código inicial <span x-show="row.is_find_error" class="text-acode-danger">*</span>
                                    </label>
                                    <x-form.textarea
                                        rows="3"
                                        :mono="true"
                                        maxlength="10000"
                                        x-bind:name="'items[' + rIdx + '][starter_code]'"
                                        x-bind="fieldAttributes(row, 'starter_code')"
                                        x-model="row.starter_code"
                                        x-bind:required="row.question_type === 'code' && row.is_find_error"
                                        placeholder="// Código base o defectuoso"
                                        class="placeholder:!text-slate-500"
                                    ></x-form.textarea>
                                    <p x-bind="errorAttributes(row, 'starter_code')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                                </div>
                                <div>
                                    <label :for="fieldId(row, 'reference_solution')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Solución canónica *</label>
                                    <x-form.textarea
                                        rows="3"
                                        :mono="true"
                                        maxlength="10000"
                                        x-bind:name="'items[' + rIdx + '][reference_solution]'"
                                        x-bind="fieldAttributes(row, 'reference_solution')"
                                        x-model="row.reference_solution"
                                        x-bind:required="row.question_type === 'code'"
                                        placeholder="// Solución correcta"
                                        class="placeholder:!text-slate-500"
                                    ></x-form.textarea>
                                    <p x-bind="errorAttributes(row, 'reference_solution')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                                </div>
                            </div>
                        </fieldset>

                        {{-- Materia y valores predeterminados de la fila --}}
                        <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-3 sm:grid-cols-4">
                            <div class="col-span-2 min-w-0">
                                <label :for="fieldId(row, 'subject_tag')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Materia (opcional)</label>
                                <x-form.input
                                    type="text"
                                    x-bind:name="'items[' + rIdx + '][subject_tag]'"
                                    x-bind="fieldAttributes(row, 'subject_tag')"
                                    x-model="row.subject_tag"
                                    maxlength="100"
                                    list="batch-subjects-datalist"
                                    x-bind:placeholder="sharedSubjectTag ? 'Usar ' + sharedSubjectTag : 'Sin materia'"
                                    class="placeholder:!text-slate-500"
                                />
                                <p x-bind="errorAttributes(row, 'subject_tag')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                            </div>

                            <div class="min-w-0">
                                <label :for="fieldId(row, 'default_points')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Puntaje *</label>
                                <x-form.input
                                    type="number"
                                    x-bind:name="'items[' + rIdx + '][default_points]'"
                                    x-bind="fieldAttributes(row, 'default_points')"
                                    x-model.number="row.default_points"
                                    step="0.01"
                                    min="0.1"
                                    max="100"
                                    required
                                />
                                <p x-bind="errorAttributes(row, 'default_points')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                            </div>

                            <div class="min-w-0">
                                <label :for="fieldId(row, 'default_time_seconds')" class="mb-1.5 block text-[13px] font-medium text-slate-800">Tiempo sugerido (s) *</label>
                                <x-form.input
                                    type="number"
                                    x-bind:name="'items[' + rIdx + '][default_time_seconds]'"
                                    x-bind="fieldAttributes(row, 'default_time_seconds')"
                                    x-model.number="row.default_time_seconds"
                                    min="5"
                                    max="3600"
                                    step="1"
                                    required
                                />
                                <p x-bind="errorAttributes(row, 'default_time_seconds')" x-cloak class="mt-1 text-xs text-acode-danger"></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Barra inferior de guardado --}}
            <div class="flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:px-5">
                <x-unsaved-changes form-id="batch-create-form" :cancel-url="route('questions.index')" />

                <div class="flex flex-wrap items-center gap-2.5 sm:justify-end">
                    <p id="batch-quota-status" class="text-xs text-slate-600" role="status">
                        <span x-show="quotaSummary.is_unlimited" x-cloak>Plan Ilimitado</span>
                        <span x-show="!quotaSummary.is_unlimited && !exceedsQuota" x-cloak>
                            Cuota disponible: <span x-text="quotaSummary.remaining"></span>
                        </span>
                        <span x-show="exceedsQuota" x-cloak class="font-medium text-acode-danger-dark" x-text="quotaMessage"></span>
                    </p>
                    <x-button
                        type="button"
                        variant="secondary"
                        size="md"
                        @click="addRow()"
                        x-bind:disabled="rows.length >= maxItems"
                    >
                        <x-icon name="add" class="w-4 h-4" />
                        Agregar fila
                    </x-button>

                    <x-button type="submit" variant="primary" size="md" loading-when="saving" ::disabled="saving || exceedsQuota" aria-describedby="batch-quota-status">
                        <x-icon name="save" class="w-4 h-4" x-show="!saving" />
                        <span x-text="saving ? 'Guardando…' : 'Guardar lote (' + rows.length + ')'">Guardar lote</span>
                    </x-button>
                    <span class="sr-only" role="status" x-text="saving ? 'Guardando lote…' : ''"></span>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            window.acodeBatchCreator = function (config) {
                let uidCounter = 1;
                const nextUid = () => 'buid-' + (uidCounter++);

                const makeDefaultRow = (overrides = {}) => {
                    const options = Array.isArray(overrides.options) && overrides.options.length >= 2
                        ? overrides.options.map((t) => ({ uid: nextUid(), text: String(t ?? '') }))
                        : [{ uid: nextUid(), text: '' }, { uid: nextUid(), text: '' }];
                    const rawIndex = overrides.correct_option_index === undefined ? 0 : overrides.correct_option_index;
                    const index = rawIndex === null || rawIndex === '' ? NaN : Number(rawIndex);

                    return {
                        uid: nextUid(),
                        errors: {},
                        question_type: overrides.question_type || 'multiple_choice',
                        title: overrides.title || '',
                        subject_tag: overrides.subject_tag || '',
                        stem: overrides.stem || '',
                        default_points: Number(overrides.default_points ?? 1),
                        default_time_seconds: Number(overrides.default_time_seconds ?? 60),
                        options,
                        correct_option_index: Number.isInteger(index) && index >= 0 && index < options.length ? index : null,
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
                    };
                };

                const seedRows = Array.isArray(config.oldItems) && config.oldItems.length > 0
                    ? config.oldItems.map((item) => {
                        const questionType = item?.question_type || 'multiple_choice';
                        const concepts = Array.isArray(item?.concepts) ? item.concepts : [];
                        const definitions = Array.isArray(item?.definitions) ? item.definitions : [];
                        const pairs = concepts.length >= 2
                            ? concepts.map((c, idx) => ({ concept: c, definition: definitions[idx] ?? '' }))
                            : null;

                        return makeDefaultRow({
                            question_type: questionType,
                            title: item?.title,
                            subject_tag: item?.subject_tag,
                            stem: item?.stem,
                            default_points: item?.default_points,
                            default_time_seconds: item?.default_time_seconds,
                            options: item?.options,
                            correct_option_index: questionType === 'multiple_choice' ? (item?.correct_option_index ?? null) : undefined,
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

                const { shared_subject_tag: subjectErrors = [], ...remainingErrors } = config.serverErrors || {};
                const globalErrors = [];

                for (const [key, messages] of Object.entries(remainingErrors)) {
                    const parts = /^items\.(\d+)(?:\.([^.]+)(?:\.(\d+))?)?$/.exec(key);
                    const row = parts && seedRows[Number(parts[1])];
                    if (!row) {
                        globalErrors.push(...messages);
                        continue;
                    }

                    const field = parts[2] || 'row';
                    const children = field === 'options' ? row.options : row.pairs;
                    const child = parts[3] !== undefined ? children[Number(parts[3])] : null;
                    const fieldKey = child ? field + '-' + child.uid : field;
                    row.errors[fieldKey] = [...(row.errors[fieldKey] || []), ...messages];
                }

                return {
                    maxItems: Number(config.maxItems || 30),
                    quotaSummary: config.quotaSummary || { is_unlimited: true, remaining: null },
                    sharedSubjectTag: config.sharedSubjectTag || '',
                    rows: seedRows,
                    suggestUrl: config.suggestUrl || '',
                    subjectSuggestions: [],
                    debounceTimer: null,
                    sharedSubjectError: subjectErrors[0] || '',
                    globalErrors,
                    saving: false,

                    get exceedsQuota() {
                        if (this.quotaSummary.is_unlimited) return false;
                        return this.rows.length > Number(this.quotaSummary.remaining ?? 0);
                    },

                    get quotaMessage() {
                        const remaining = Number(this.quotaSummary.remaining ?? 0);
                        if (remaining === 0) return 'No tienes cuota disponible para guardar el lote.';
                        return 'Cuota disponible: ' + remaining + '. Reduce el lote a ' + remaining + ' filas como máximo.';
                    },

                    submitBatch(event) {
                        if (this.saving || this.exceedsQuota) {
                            event.preventDefault();
                            return;
                        }
                        this.saving = true;
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

                    optionLetter(index) {
                        return String.fromCharCode(65 + index);
                    },

                    rowErrorCount(row) {
                        return Object.values(row.errors).flat().length;
                    },

                    hasError(row, field) {
                        return Boolean(row.errors[field]?.length);
                    },

                    fieldId(row, field) {
                        return row.uid + '-' + field;
                    },

                    fieldAttributes(row, field) {
                        const id = this.fieldId(row, field);
                        return {
                            ':id': () => id,
                            ':aria-invalid': () => this.hasError(row, field) ? 'true' : null,
                            ':aria-describedby': () => this.hasError(row, field) ? id + '-error' : null,
                            ':class': () => this.hasError(row, field) ? '!border-acode-danger' : '',
                            '@input': () => { delete row.errors[field]; },
                        };
                    },

                    errorAttributes(row, field) {
                        const id = this.fieldId(row, field) + '-error';
                        return {
                            ':id': () => id,
                            'x-show': () => this.hasError(row, field),
                            'x-text': () => row.errors[field]?.join(' · ') || '',
                        };
                    },

                    focusField(id) {
                        document.getElementById(id)?.focus();
                    },

                    focusRowError(row) {
                        const rowElement = document.getElementById(row.uid);
                        const firstInvalid = Array.from(rowElement.querySelectorAll('[aria-invalid="true"]'))
                            .find((field) => field.getClientRects().length);
                        (firstInvalid || rowElement).focus();
                    },

                    selectOption(row, index) {
                        row.correct_option_index = index;
                        delete row.errors.correct_option_index;
                    },

                    clearTypeErrors(row) {
                        const commonFields = ['title', 'subject_tag', 'stem', 'default_points', 'default_time_seconds', 'row'];
                        Object.keys(row.errors).filter((field) => !commonFields.includes(field)).forEach((field) => { delete row.errors[field]; });
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

                    async confirmRemoval(title, text, action) {
                        const result = await window.AcodeAlert?.confirm({
                            title,
                            text,
                            confirmButtonText: action,
                            isDanger: true,
                        });
                        return result?.isConfirmed;
                    },

                    async removeRow(row) {
                        if (this.rows.length <= 1) return;
                        const hasContent = [row.title, row.stem, row.subject_tag, row.reference_answer, row.evaluation_rubric,
                            row.instructions, row.starter_code, row.reference_solution, ...row.options.map((option) => option.text),
                            ...row.pairs.flatMap((pair) => [pair.concept, pair.definition])].some((text) => String(text).trim());
                        const hasCustomSettings = row.default_points !== 1 || row.default_time_seconds !== 60
                            || row.correct_option_index !== 0 || row.is_find_error || row.programming_language !== 'Python';
                        if ((hasContent || hasCustomSettings) && !await this.confirmRemoval(
                            '¿Eliminar esta fila?', 'Se perderán los datos capturados en esta fila.', 'Eliminar fila'
                        )) return;

                        const index = this.rows.findIndex((candidate) => candidate.uid === row.uid);
                        if (index < 0 || this.rows.length <= 1) return;
                        this.rows.splice(index, 1);
                        this.notifyFormChanged();
                    },

                    addOption(rowIndex) {
                        const row = this.rows[rowIndex];
                        if (row && row.options.length < 6) {
                            row.options.push({ uid: nextUid(), text: '' });
                            delete row.errors.options;
                            this.notifyFormChanged();
                        }
                    },

                    async removeOption(row, option) {
                        if (row.options.length <= 2) return;
                        if (option.text.trim() && !await this.confirmRemoval(
                            '¿Eliminar esta opción?', 'Se perderá el texto de esta opción.', 'Eliminar opción'
                        )) return;

                        const index = row.options.findIndex((candidate) => candidate.uid === option.uid);
                        if (index < 0 || row.options.length <= 2) return;
                        const correctUid = row.options[row.correct_option_index]?.uid;
                        row.options.splice(index, 1);
                        const correctIndex = row.options.findIndex((candidate) => candidate.uid === correctUid);
                        row.correct_option_index = correctIndex < 0 ? null : correctIndex;
                        delete row.errors['options-' + option.uid];
                        delete row.errors.options;
                        delete row.errors.correct_option_index;
                        this.notifyFormChanged();
                    },

                    addPair(rowIndex) {
                        const row = this.rows[rowIndex];
                        if (row && row.pairs.length < 8) {
                            row.pairs.push({ uid: nextUid(), concept: '', definition: '' });
                            delete row.errors.concepts;
                            delete row.errors.definitions;
                            this.notifyFormChanged();
                        }
                    },

                    async removePair(row, pair) {
                        if (row.pairs.length <= 2) return;
                        if ((pair.concept.trim() || pair.definition.trim()) && !await this.confirmRemoval(
                            '¿Eliminar este par?', 'Se perderán el concepto y la definición de este par.', 'Eliminar par'
                        )) return;

                        const index = row.pairs.findIndex((candidate) => candidate.uid === pair.uid);
                        if (index < 0 || row.pairs.length <= 2) return;
                        row.pairs.splice(index, 1);
                        delete row.errors['concepts-' + pair.uid];
                        delete row.errors['definitions-' + pair.uid];
                        delete row.errors.concepts;
                        delete row.errors.definitions;
                        this.notifyFormChanged();
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
                        this.sharedSubjectError = '';
                        this.subjectSuggestions = [];
                        this.notifyFormChanged();
                    },
                };
            };
        </script>
    @endpush
@endsection
