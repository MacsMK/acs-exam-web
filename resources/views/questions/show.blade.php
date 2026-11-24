{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/show.blade.php (MPW-V3 §2.4, §5.2.2, §6.3, §7.3, §7.8, §8.4, §8.7, Tabla 10, RF-BAN-007, RF-BAN-010) --}}
@extends('layouts.app')

@section('title', 'Detalle del reactivo · ' . $question->title)

@section('content')
    @php
        $typeLabels = [
            'multiple_choice' => 'Opción múltiple',
            'matching' => 'Relación de columnas',
            'open' => 'Pregunta abierta',
            'code' => 'Código fuente',
        ];
        $typeVariants = [
            'multiple_choice' => 'info',
            'matching' => 'accent',
            'open' => 'warning',
            'code' => 'primary',
        ];
        $minutes = intdiv((int) $question->default_time_seconds, 60);
        $seconds = (int) $question->default_time_seconds % 60;
        $formattedTime = $minutes === 0 ? "$seconds s" : "$minutes min" . ($seconds > 0 ? " $seconds s" : '');
        $paraphraseCount = $question->paraphrases->count();
        $paraphraseHasErrors = $errors->paraphrase->any() || $errors->has('stem');
    @endphp

    <div class="space-y-6">
        {{-- Cabecera Institucional del Reactivo --}}
        <x-page-header
            :title="$question->title"
            :institutional="true"
            class="[&_h1]:break-words"
            :breadcrumbs="[
                ['label' => 'Banco de preguntas', 'href' => route('questions.index')],
                ['label' => 'Detalle'],
            ]"
        >
            <x-slot:badge>
                <x-badge :variant="$typeVariants[$question->question_type] ?? 'neutral'">
                    {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                </x-badge>
            </x-slot:badge>

            <x-slot:actions>
                <x-button :href="route('questions.index')" variant="neutral" size="md">
                    <x-icon name="arrowLeft" class="w-4 h-4" />
                    Volver
                </x-button>

                <form
                    id="duplicate-bottom-form"
                    method="POST"
                    action="{{ route('questions.duplicate', $question) }}"
                    class="inline"
                >
                    @csrf
                    <x-button
                        type="button"
                        variant="secondary"
                        size="md"
                        onclick="confirmDuplicateBottom()"
                    >
                        <x-icon name="copy" class="w-4 h-4" />
                        Copiar
                    </x-button>
                </form>

                <x-button :href="route('questions.edit', $question)" variant="primary" size="md">
                    <x-icon name="edit" class="w-4 h-4" />
                    Editar reactivo
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Enunciado del reactivo">
            <div class="space-y-3">
                {{-- whitespace-pre-wrap conserva espacios y sangrías del código en el enunciado (RF-BAN-014) --}}
                <p class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">{{ $question->stem }}</p>
                <x-question.files :question-id="$question->id" :has-image="$question->image_path !== null" :has-pdf="$question->pdf_path !== null" />
            </div>
        </x-card>

        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="min-w-0">
                {{-- Desglose Polimórfico Específico según question_type --}}
                @switch($question->question_type)
                    {{-- 1. Opción Múltiple --}}
                    @case('multiple_choice')
                        <x-card title="Alternativas de Respuesta">
                            <ol class="space-y-2" role="list">
                                @forelse ($question->questionable->options_payload ?? [] as $index => $option)
                                    @php
                                        $optText = is_array($option) ? ($option['text'] ?? '') : (string) $option;
                                        $optIndex = is_array($option) ? ($option['index'] ?? $index) : $index;
                                        $isCorrect = (int) ($question->questionable->correct_option_index ?? -1) === (int) $optIndex;
                                    @endphp
                                    <li class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border px-3 py-2.5 {{ $isCorrect ? 'border-acode-tech bg-acode-tech/5 font-semibold text-acode-primary' : 'border-slate-200 bg-white text-slate-700' }}">
                                        <div class="flex min-w-0 flex-1 items-center gap-2.5">
                                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold {{ $isCorrect ? 'bg-acode-tech/10 text-acode-primary' : 'bg-slate-100 text-slate-600' }}">
                                                {{ chr(65 + $loop->index) }}
                                            </span>
                                            <span class="min-w-0 break-words text-sm leading-5">{{ $optText }}</span>
                                        </div>
                                        @if ($isCorrect)
                                            <x-badge variant="success" class="gap-1.5 shrink-0">
                                                <x-icon name="check" class="h-3.5 w-3.5 text-acode-primary" />
                                                <span>Respuesta Correcta</span>
                                            </x-badge>
                                        @endif
                                    </li>
                                @empty
                                    <li class="py-3 text-center text-sm text-slate-600">No hay opciones registradas para este reactivo.</li>
                                @endforelse
                            </ol>
                        </x-card>
                        @break

                    {{-- 2. Relación de Columnas --}}
                    @case('matching')
                        <x-card title="Correspondencia Biyectiva">
                            <x-table scroll-label="Pares de relación de columnas">
                                <x-slot:header>
                                    <x-table.th class="w-1/2">Columna A: Concepto / Premisa</x-table.th>
                                    <x-table.th class="w-1/2">Columna B: Definición / Respuesta</x-table.th>
                                </x-slot:header>

                                @forelse ($question->questionable->pairs_payload ?? [] as $pair)
                                    <tr>
                                        <x-table.td class="break-words align-top text-sm font-semibold text-slate-800">
                                            {{ data_get(collect($question->questionable->left_column_payload ?? [])->firstWhere('id', $pair['left_id'] ?? null), 'text', '') }}
                                        </x-table.td>
                                        <x-table.td class="break-words align-top text-sm text-slate-700">
                                            {{ data_get(collect($question->questionable->right_column_payload ?? [])->firstWhere('id', $pair['right_id'] ?? null), 'text', '') }}
                                        </x-table.td>
                                    </tr>
                                @empty
                                    <tr>
                                        <x-table.td colspan="2" align="center" class="py-6 text-slate-600">
                                            No hay pares registrados para este reactivo.
                                        </x-table.td>
                                    </tr>
                                @endforelse
                            </x-table>
                        </x-card>
                        @break

                    {{-- 3. Pregunta Abierta --}}
                    @case('open')
                        <div class="space-y-6">
                            <x-card title="Respuesta Modelo Canónica">
                                <p class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">{{ $question->questionable->reference_answer ?? 'No registrada' }}</p>
                            </x-card>

                            {{-- Rúbrica de Evaluación --}}
                            @if (!empty($question->questionable->evaluation_rubric))
                                <x-card title="Rúbrica de Evaluación">
                                    <p class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{{ $question->questionable->evaluation_rubric }}</p>
                                </x-card>
                            @endif
                        </div>
                        @break

                    {{-- 4. Código Fuente (MPW-V3 Tabla 14 & MC-V3 Tabla 4) --}}
                    @case('code')
                        <x-card>
                            <div class="space-y-5">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                                    <div>
                                        <h2 class="text-base font-semibold text-acode-primary leading-6">Configuración del Entorno de Código</h2>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-md bg-acode-tech/10 text-acode-tech border border-acode-tech/30">
                                            {{ $question->questionable->programming_language ?? 'General' }}
                                        </span>
                                        @if ($question->questionable->is_find_error)
                                            <x-badge variant="warning">Depuración</x-badge>
                                        @else
                                            <x-badge variant="info">Desarrollo desde cero</x-badge>
                                        @endif
                                    </div>
                                </div>

                                {{-- Instrucciones técnicas (opcionales: solo se muestran si existen) --}}
                                @if (filled($question->questionable->instructions))
                                    <div>
                                        <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                                            Instrucciones Técnicas
                                        </span>
                                        <div class="whitespace-pre-wrap break-words rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-sm leading-5 text-slate-700">{{ $question->questionable->instructions }}</div>
                                    </div>
                                @endif

                                {{-- Criterios de evaluación (opcionales, RF-BAN-004) --}}
                                @if (filled($question->questionable->evaluation_rubric))
                                    <div>
                                        <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                                            Criterios de Evaluación
                                        </span>
                                        <div class="whitespace-pre-wrap break-words rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-sm leading-5 text-slate-700">{{ $question->questionable->evaluation_rubric }}</div>
                                    </div>
                                @endif

                                {{-- Código inicial si aplica --}}
                                @if (!empty($question->questionable->starter_code))
                                    <div class="space-y-1.5">
                                        <div class="flex flex-wrap items-center justify-between gap-1.5">
                                            <span class="block text-sm font-medium text-slate-800">
                                                {{ $question->questionable->is_find_error ? 'Código con error' : 'Código inicial' }}
                                            </span>
                                            <span class="text-xs font-mono text-slate-500">Editor Alumno</span>
                                        </div>
                                        <div class="rounded-lg overflow-hidden border border-slate-300 border-l-4 border-l-acode-primary bg-slate-100">
                                            <div class="bg-white/80 px-3.5 py-1.5 border-b border-slate-200 text-xs font-mono text-slate-600 flex items-center justify-between">
                                                <span>starter_code.{{ $question->questionable->fileExtension() }}</span>
                                                <span class="uppercase tracking-wider text-slate-500 font-sans font-semibold">Sólo lectura</span>
                                            </div>
                                            <pre tabindex="0" aria-label="Código inicial" class="overflow-x-auto p-3.5 font-mono text-[13px] leading-5 text-acode-ink focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-acode-tech"><code>{{ $question->questionable->starter_code }}</code></pre>
                                        </div>
                                    </div>
                                @endif

                                {{-- Solución de Referencia --}}
                                <div class="space-y-1.5">
                                    <div>
                                        <span class="block text-sm font-medium text-slate-800">
                                            Solución Canónica de Referencia
                                        </span>
                                    </div>
                                    <div class="rounded-lg overflow-hidden border border-slate-300 border-l-4 border-l-acode-accent bg-slate-100">
                                        <div class="bg-white/80 px-3.5 py-1.5 border-b border-slate-200 text-xs font-mono text-slate-600 flex items-center justify-between">
                                            <span>solution.{{ $question->questionable->fileExtension() }}</span>
                                            <span class="font-sans font-semibold text-acode-primary">Respuesta Correcta</span>
                                        </div>
                                        <pre tabindex="0" aria-label="Solución canónica de referencia" class="overflow-x-auto p-3.5 font-mono text-[13px] leading-5 text-acode-ink focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-acode-tech"><code>{{ $question->questionable->reference_solution }}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </x-card>
                        @break
                @endswitch
            </div>

            <x-card title="Datos del reactivo">
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 pb-2.5">
                        <dt class="font-medium text-slate-600">Materia</dt>
                        <dd class="min-w-0 break-words font-semibold text-acode-primary">{{ $question->subject_tag ?? 'Sin materia' }}</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 py-2.5">
                        <dt class="font-medium text-slate-600">Puntaje</dt>
                        <dd class="font-semibold tabular-nums text-acode-primary">{{ \App\Support\AcademicNumber::forInput($question->default_points) }} pts</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 py-2.5">
                        <dt class="font-medium text-slate-600">Tiempo sugerido</dt>
                        <dd class="font-semibold tabular-nums text-acode-primary">{{ $formattedTime }}</dd>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 pt-2.5">
                        <dt class="font-medium text-slate-600">Uso en exámenes</dt>
                        <dd>
                            <x-badge :variant="$isLinkedToExams ? 'warning' : 'neutral'">
                                {{ $isLinkedToExams ? 'Vinculado' : 'Sin vincular' }}
                            </x-badge>
                        </dd>
                    </div>
                </dl>
            </x-card>
        </div>

        {{-- Panel de Paráfrasis Anti-Copia (RF-BAN-010) --}}
        <x-card>
            <div
                class="space-y-4"
                x-data="{
                    openNewForm: @js($paraphraseHasErrors),
                    saving: false,
                    paraphraseStem: @js(old('stem', '')),
                    originalStem: @js($question->stem),
                    toggleForm(open) {
                        this.openNewForm = open;
                        this.$nextTick(() => (open ? this.$refs.paraphraseInput : this.$refs.paraphraseToggle)?.focus());
                    },
                    get matchesOriginal() {
                        const a = this.paraphraseStem.trim().replace(/\s+/g, ' ').toLowerCase();
                        const b = (this.originalStem || '').trim().replace(/\s+/g, ' ').toLowerCase();
                        return a.length > 0 && a === b;
                    }
                }"
            >
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold leading-6 text-acode-primary">Paráfrasis anti-copia</h2>
                            @if ($paraphraseCount > 0)
                                <x-badge variant="success">{{ $paraphraseCount }} {{ $paraphraseCount === 1 ? 'registrada' : 'registradas' }}</x-badge>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-slate-600">
                            Durante el examen, el sistema alterna el enunciado con sus variantes y conserva la clave.
                        </p>
                    </div>

                    <x-button
                        type="button"
                        variant="secondary"
                        size="sm"
                        x-ref="paraphraseToggle"
                        @click="toggleForm(!openNewForm)"
                        x-bind:aria-expanded="openNewForm.toString()"
                        aria-controls="question-paraphrase-form"
                    >
                        <x-icon name="add" class="h-4 w-4" x-show="!openNewForm" />
                        <span x-text="openNewForm ? 'Cerrar formulario' : 'Agregar paráfrasis'">Agregar paráfrasis</span>
                    </x-button>
                </div>

                @if ($isLinkedToExams)
                    <div class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 px-3.5 py-2.5 text-xs text-acode-primary flex items-start gap-2">
                        <x-icon name="warning" class="w-4 h-4 text-acode-warning-dark shrink-0 mt-0.5" />
                        <span>
                            Los intentos en curso y anteriores conservan el enunciado que ya recibieron.
                        </span>
                    </div>
                @endif

                {{-- Formulario Integrado para Crear Paráfrasis --}}
                <div
                    id="question-paraphrase-form"
                    x-bind:hidden="!openNewForm"
                    x-cloak
                    @keydown.escape.stop="toggleForm(false)"
                    class="p-4 rounded-xl bg-acode-surface/70 border border-acode-tech/30 space-y-3"
                >
                    {{-- Enunciado original de referencia --}}
                    <div class="p-3 bg-white rounded-lg border border-slate-200 text-sm">
                        <p class="mb-1 text-xs font-semibold text-slate-600">Enunciado original</p>
                        <p class="whitespace-pre-wrap break-words leading-5 text-slate-700">{{ $question->stem }}</p>
                    </div>

                    <form method="POST" action="{{ route('questions.paraphrase.store', $question) }}" class="space-y-3"
                        @submit="if (saving || matchesOriginal) { $event.preventDefault() } else { saving = true }"
                        @pageshow.window="saving = false"
                    >
                        @csrf
                        <div>
                            <label for="stem" class="mb-1.5 block text-sm font-medium text-slate-800">Redacción alternativa</label>
                            <x-form.textarea
                                name="stem"
                                rows="3"
                                minlength="3"
                                maxlength="500"
                                required
                                x-ref="paraphraseInput"
                                x-model="paraphraseStem"
                                :hasError="$paraphraseHasErrors"
                                :aria-invalid="$paraphraseHasErrors ? 'true' : null"
                                :aria-describedby="$paraphraseHasErrors ? 'paraphrase-help paraphrase-error' : 'paraphrase-help'"
                                placeholder="Redacta una variante con la misma respuesta correcta…"
                                class="placeholder:!text-slate-500"
                            />
                            <div class="mt-1 flex flex-wrap justify-between gap-x-3 text-xs text-slate-700">
                                <p id="paraphrase-help">De 3 a 500 caracteres.</p>
                                <span class="font-mono" aria-hidden="true" x-text="paraphraseStem.length + ' / 500'"></span>
                            </div>
                            @if ($paraphraseHasErrors)
                                <p id="paraphrase-error" class="mt-1 text-xs font-medium text-acode-danger-dark">
                                    {{ $errors->paraphrase->first() ?: $errors->first('stem') }}
                                </p>
                            @endif
                            <p x-show="matchesOriginal" x-cloak class="mt-1 text-xs font-medium text-acode-danger-dark" role="status">
                                La paráfrasis debe redactarse de forma distinta al enunciado original.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <x-button type="button" variant="neutral" size="sm" @click="toggleForm(false)">
                                Cancelar
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm" loading-when="saving" ::disabled="matchesOriginal || saving">
                                <x-icon name="save" class="h-3.5 w-3.5" x-show="!saving" />
                                <span x-text="saving ? 'Guardando…' : 'Guardar paráfrasis'">Guardar paráfrasis</span>
                            </x-button>
                            <p class="sr-only" role="status" x-text="saving ? 'Guardando paráfrasis…' : ''"></p>
                        </div>
                    </form>
                </div>

                {{-- Listado de Paráfrasis Existentes --}}
                <div class="space-y-2.5">
                    @error('paraphrase')
                        <p class="text-sm text-acode-danger-dark" role="alert">{{ $message }}</p>
                    @enderror
                    @forelse ($question->paraphrases as $paraphrase)
                        <div class="p-3.5 rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-badge variant="info">
                                        Variante #{{ $loop->iteration }}
                                    </x-badge>
                                    <span class="text-xs text-slate-500">
                                        Registrada {{ $paraphrase->created_at?->diffForHumans() ?? 'recientemente' }}
                                    </span>
                                </div>
                                <p class="text-sm text-slate-700 leading-5 whitespace-pre-wrap break-words">{{ $paraphrase->stem }}</p>
                            </div>

                            <form
                                id="delete-paraphrase-form-{{ $paraphrase->id }}"
                                method="POST"
                                action="{{ route('questions.paraphrase.destroy', [$question, $paraphrase]) }}"
                                class="shrink-0"
                            >
                                @csrf
                                @method('DELETE')
                                <x-button
                                    type="button"
                                    variant="danger"
                                    size="sm"
                                    :icon-only="true"
                                    onclick="confirmDeleteParaphrase({{ $paraphrase->id }})"
                                    title="Eliminar paráfrasis"
                                    aria-label="Eliminar paráfrasis {{ $loop->iteration }}"
                                >
                                    <x-icon name="delete" class="w-4 h-4" />
                                </x-button>
                            </form>
                        </div>
                    @empty
                        <x-empty-state
                            :icon="null"
                            message="Sin paráfrasis registradas."
                            class="!items-start !p-0 !text-left !border-0 !shadow-none"
                        />
                    @endforelse
                </div>
            </div>
        </x-card>

        {{-- Trazabilidad de Versionado Copy-on-Write --}}
        @if ($question->parent || $question->children->isNotEmpty())
            <x-card title="Historial de versiones">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Versión de origen</p>
                        @if ($question->parent)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-acode-primary truncate">
                                        #{{ $question->parent->id }} · {{ $question->parent->title }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        Creada {{ $question->parent->created_at?->format('d/m/Y H:i') ?? 'previamente' }}
                                    </p>
                                </div>
                                <x-button :href="route('questions.show', $question->parent)" variant="neutral" size="sm" aria-label="Abrir versión de origen «{{ $question->parent->title }}»">
                                    <x-icon name="view" class="w-3.5 h-3.5" />
                                    Abrir
                                </x-button>
                            </div>
                        @else
                            <p class="text-xs text-slate-600">Este reactivo es la versión de origen.</p>
                        @endif
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Versiones derivadas ({{ $question->children->count() }})
                        </p>
                        <div class="space-y-2">
                            @forelse ($question->children as $childVersion)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-acode-primary truncate">
                                            #{{ $childVersion->id }} · {{ $childVersion->title }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            Derivada {{ $childVersion->created_at?->format('d/m/Y H:i') ?? 'recientemente' }}
                                        </p>
                                    </div>
                                    <x-button :href="route('questions.show', $childVersion)" variant="neutral" size="sm" aria-label="Abrir versión derivada «{{ $childVersion->title }}»">
                                        <x-icon name="view" class="w-3.5 h-3.5" />
                                        Abrir
                                    </x-button>
                                </div>
                            @empty
                                <p class="text-xs text-slate-600">Aún no hay versiones derivadas.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </x-card>
        @endif

        {{-- Barra Inferior de Gestión del Reactivo --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            <p class="text-xs text-slate-500">
                Retirar este reactivo lo ocultará de tu catálogo activo manteniendo la integridad de exámenes históricos.
            </p>

            <form
                id="delete-bottom-form"
                method="POST"
                action="{{ route('questions.destroy', $question) }}"
                class="inline shrink-0"
            >
                @csrf
                @method('DELETE')
                <x-button
                    type="button"
                    variant="danger"
                    size="md"
                    onclick="confirmDeleteBottom()"
                >
                    <x-icon name="delete" class="w-4 h-4" />
                    Eliminar reactivo
                </x-button>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            const questionTitleLabel = @js($question->title);

            function confirmDeleteParaphrase(paraphraseId) {
                AcodeAlert.confirm({
                    title: '¿Eliminar variante de paráfrasis?',
                    text: 'Esta redacción alternativa ya no será alternada en exámenes futuros.',
                    confirmButtonText: 'Eliminar',
                    cancelButtonText: 'Cancelar',
                    isDanger: true,
                    onConfirm: function () {
                        document.getElementById('delete-paraphrase-form-' + paraphraseId).submit();
                    }
                });
            }

            function confirmDuplicateBottom() {
                AcodeAlert.confirm({
                    title: '¿Copiar reactivo?',
                    text: 'Se creará una copia independiente del reactivo "' + questionTitleLabel + '" para edición libre.',
                    confirmButtonText: 'Copiar',
                    cancelButtonText: 'Cancelar',
                    isDanger: false,
                    onConfirm: function () {
                        document.getElementById('duplicate-bottom-form').submit();
                    }
                });
            }

            function confirmDeleteBottom() {
                AcodeAlert.confirm({
                    title: '¿Eliminar reactivo?',
                    text: 'El reactivo "' + questionTitleLabel + '" será retirado de tu banco. Los exámenes donde ya se usó lo conservarán.',
                    confirmButtonText: 'Eliminar',
                    cancelButtonText: 'Cancelar',
                    isDanger: true,
                    onConfirm: function () {
                        document.getElementById('delete-bottom-form').submit();
                    }
                });
            }
        </script>
    @endpush
@endsection
