{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/show.blade.php (MPW-V3 §2.4, §5.2.2, §6.3, §7.3, §7.8, §8.4, §8.7, Tabla 10, RF-BAN-007, RF-BAN-010) --}}
@extends('layouts.app')

@section('title', 'Detalle del Reactivo · ' . $question->title)

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
        $formattedTime = $question->default_time_seconds >= 60
            ? floor($question->default_time_seconds / 60) . 'm' . ($question->default_time_seconds % 60 > 0 ? ' ' . ($question->default_time_seconds % 60) . 's' : '')
            : $question->default_time_seconds . 's';
    @endphp

    <div class="space-y-6">
        {{-- Cabecera Institucional del Reactivo --}}
        <x-page-header
            :title="$question->title"
            :breadcrumbs="[
                ['label' => 'Banco de preguntas', 'href' => route('questions.index')],
                ['label' => $question->title],
            ]"
        >
            <x-slot:badge>
                <x-badge :variant="$typeVariants[$question->question_type] ?? 'neutral'">
                    {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                </x-badge>

                @if ($isLinkedToExams)
                    <x-badge variant="warning" class="gap-1">
                        <x-icon name="lock" class="w-3.5 h-3.5" />
                        En exámenes activos/históricos
                    </x-badge>
                @else
                    <x-badge variant="neutral">
                        Sin vincular a exámenes
                    </x-badge>
                @endif

                @if ($question->children->isNotEmpty())
                    <x-badge variant="accent" class="gap-1">
                        <x-icon name="copy" class="w-3.5 h-3.5" />
                        {{ $question->children->count() }} {{ $question->children->count() === 1 ? 'versión derivada' : 'versiones derivadas' }}
                    </x-badge>
                @endif
            </x-slot:badge>

            <x-slot:meta>
                <span class="inline-flex items-center gap-1.5 font-medium text-slate-600">
                    <x-icon name="tag" class="w-3.5 h-3.5 text-slate-400" />
                    {{ $question->subject_tag ?? 'Materia General' }}
                </span>

                <span class="inline-flex items-center gap-1 font-mono font-semibold text-acode-primary">
                    {{ number_format((float) $question->default_points, 1) }} pts
                </span>

                <span class="inline-flex items-center gap-1 font-mono text-acode-tech font-semibold">
                    <x-icon name="clock" class="w-3.5 h-3.5" />
                    {{ $formattedTime }}
                </span>

                @if ($question->parent)
                    <a
                        href="{{ route('questions.show', $question->parent) }}"
                        class="inline-flex items-center gap-1 font-semibold text-acode-tech hover:underline"
                        title="Consultar la versión previa original"
                    >
                        <x-icon name="view" class="w-3.5 h-3.5" />
                        Versión previa: #{{ $question->parent->id }}
                    </a>
                @endif
            </x-slot:meta>

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

        {{-- Enunciado Principal (Stem) --}}
        <x-card>
            <div class="space-y-2">
                <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Enunciado o Planteamiento Principal
                </span>
                {{-- whitespace-pre-wrap conserva espacios y sangrías del código en el enunciado (RF-BAN-014) --}}
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 text-sm leading-6 whitespace-pre-wrap break-words">{{ $question->stem }}</div>
            </div>
        </x-card>

        {{-- Trazabilidad de Versionado Copy-on-Write --}}
        @if ($question->parent || $question->children->isNotEmpty())
            <x-card title="Historial de Versiones (Copy-on-Write)" subtitle="Trazabilidad de versiones previas y derivadas para preservar exámenes históricos.">
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
                                <x-button :href="route('questions.show', $question->parent)" variant="neutral" size="sm">
                                    <x-icon name="view" class="w-3.5 h-3.5" />
                                    Abrir
                                </x-button>
                            </div>
                        @else
                            <p class="text-xs text-slate-500 italic">Este reactivo es raíz original (sin versión previa).</p>
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
                                    <x-button :href="route('questions.show', $childVersion)" variant="neutral" size="sm">
                                        <x-icon name="view" class="w-3.5 h-3.5" />
                                        Abrir
                                    </x-button>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500 italic">Aún no se han generado versiones derivadas a partir de este reactivo.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </x-card>
        @endif

        {{-- Desglose Polimórfico Específico según question_type --}}
        @switch($question->question_type)
            {{-- 1. Opción Múltiple --}}
            @case('multiple_choice')
                <x-card>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h2 class="text-base font-semibold text-acode-primary leading-6">Alternativas de Respuesta</h2>
                                <p class="text-xs text-slate-500">Se resalta la opción canónica designada como correcta.</p>
                            </div>
                            <x-badge variant="info">Respuesta única</x-badge>
                        </div>

                        <div class="space-y-2.5">
                            @forelse ($question->questionable->options_payload ?? [] as $index => $option)
                                @php
                                    $optText = is_array($option) ? ($option['text'] ?? '') : (string) $option;
                                    $optIndex = is_array($option) ? ($option['index'] ?? $index) : $index;
                                    $isCorrect = (int) ($question->questionable->correct_option_index ?? -1) === (int) $optIndex;
                                @endphp
                                <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl border transition {{ $isCorrect ? 'bg-acode-accent/10 border-acode-accent text-acode-primary font-semibold shadow-sm' : 'bg-white border-slate-200 text-slate-700' }}">
                                    <div class="flex items-center gap-3">
                                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold shrink-0 {{ $isCorrect ? 'bg-acode-accent text-white shadow-sm' : 'bg-slate-100 text-slate-600' }}">
                                            {{ chr(65 + $loop->index) }}
                                        </span>
                                        <span class="text-sm leading-5">{{ $optText }}</span>
                                    </div>
                                    @if ($isCorrect)
                                        <x-badge variant="success" class="gap-1.5 shrink-0">
                                            <x-icon name="check" class="w-3.5 h-3.5 text-acode-accent" />
                                            <span>Respuesta Correcta</span>
                                        </x-badge>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-slate-500 italic py-3 text-center">No hay opciones registradas para este reactivo.</p>
                            @endforelse
                        </div>
                    </div>
                </x-card>
                @break

            {{-- 2. Relación de Columnas --}}
            @case('matching')
                <x-card>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h2 class="text-base font-semibold text-acode-primary leading-6">Correspondencia Biyectiva (Concepto ↔ Definición)</h2>
                                <p class="text-xs text-slate-500">Pares solución canónicos definidos para la evaluación.</p>
                            </div>
                            <x-badge variant="accent">Emparejamiento</x-badge>
                        </div>

                        <x-table>
                            <x-slot:header>
                                <x-table.th class="w-5/12">Columna A: Concepto / Premisa</x-table.th>
                                <x-table.th align="center" class="w-1/12">Vínculo</x-table.th>
                                <x-table.th class="w-6/12">Columna B: Definición / Respuesta</x-table.th>
                            </x-slot:header>

                            @forelse ($question->questionable->pairs_payload ?? [] as $pair)
                                <tr>
                                    <x-table.td class="align-top">
                                        <div class="flex items-start gap-2">
                                            <span class="font-mono text-xs font-semibold text-acode-primary bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200 shrink-0">
                                                {{ $pair['left_id'] ?? 'L'.($loop->iteration) }}
                                            </span>
                                            <span class="text-slate-800 font-semibold text-sm leading-5">
                                                {{ data_get(collect($question->questionable->left_column_payload ?? [])->firstWhere('id', $pair['left_id'] ?? null), 'text', '') }}
                                            </span>
                                        </div>
                                    </x-table.td>
                                    <x-table.td align="center" class="align-middle text-acode-accent font-bold select-none">
                                        ↔
                                    </x-table.td>
                                    <x-table.td class="align-top">
                                        <div class="flex items-start gap-2">
                                            <span class="font-mono text-xs font-semibold text-acode-primary bg-acode-accent/15 px-2 py-0.5 rounded-md border border-acode-accent/40 shrink-0">
                                                {{ $pair['right_id'] ?? 'R'.($loop->iteration) }}
                                            </span>
                                            <span class="text-slate-700 text-sm leading-5">
                                                {{ data_get(collect($question->questionable->right_column_payload ?? [])->firstWhere('id', $pair['right_id'] ?? null), 'text', '') }}
                                            </span>
                                        </div>
                                    </x-table.td>
                                </tr>
                            @empty
                                <tr>
                                    <x-table.td colspan="3" align="center" class="py-6 text-slate-500 italic">
                                        No hay pares registrados para este reactivo.
                                    </x-table.td>
                                </tr>
                            @endforelse
                        </x-table>
                    </div>
                </x-card>
                @break

            {{-- 3. Pregunta Abierta --}}
            @case('open')
                <div class="grid grid-cols-1 gap-6">
                    {{-- Respuesta Modelo Canónica --}}
                    <x-card>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-acode-warning/20 text-acode-primary flex items-center justify-center">
                                        <x-icon name="check" class="w-4 h-4" />
                                    </div>
                                    <h2 class="text-base font-semibold text-acode-primary leading-6">Respuesta Modelo Canónica</h2>
                                </div>
                                <x-badge variant="warning">Patrón de Cotejo IA</x-badge>
                            </div>
                            <p class="text-xs text-slate-500">
                                Solución de referencia empleada por el evaluador asistido para calcular la similitud semántica.
                            </p>
                            <div class="p-4 rounded-xl bg-acode-warning/10 border border-acode-warning/40 text-slate-800 text-sm leading-5 whitespace-pre-line">
                                {{ $question->questionable->reference_answer ?? 'No registrada' }}
                            </div>
                        </div>
                    </x-card>

                    {{-- Rúbrica de Evaluación --}}
                    @if (!empty($question->questionable->evaluation_rubric))
                        <x-card>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                    <h3 class="text-base font-semibold text-acode-primary leading-6">Rúbrica de Evaluación Pedagógica</h3>
                                    <x-badge variant="neutral">Criterios Cualitativos</x-badge>
                                </div>
                                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 text-sm leading-5 whitespace-pre-line">
                                    {{ $question->questionable->evaluation_rubric }}
                                </div>
                            </div>
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
                                <p class="text-xs text-slate-500">Parámetros del editor en línea y solución canónica para la prueba técnica.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-md bg-acode-tech/10 text-acode-tech border border-acode-tech/30">
                                    {{ $question->questionable->programming_language ?? 'General' }}
                                </span>
                                @if ($question->questionable->is_find_error)
                                    <x-badge variant="warning">Depuración / Find Error</x-badge>
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
                                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 leading-5 whitespace-pre-wrap">{{ $question->questionable->instructions }}</div>
                            </div>
                        @endif

                        {{-- Criterios de evaluación (opcionales, RF-BAN-004) --}}
                        @if (filled($question->questionable->evaluation_rubric))
                            <div>
                                <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                                    Criterios de Evaluación
                                </span>
                                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 leading-5 whitespace-pre-wrap">{{ $question->questionable->evaluation_rubric }}</div>
                            </div>
                        @endif

                        {{-- Código inicial si aplica --}}
                        @if (!empty($question->questionable->starter_code))
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="block text-sm font-medium text-slate-800">
                                        {{ $question->questionable->is_find_error ? 'Código Defectuoso para Auditoría (Starter Code)' : 'Código Base / Plantilla Inicial' }}
                                    </span>
                                    <span class="text-xs font-mono text-slate-500">Editor Alumno</span>
                                </div>
                                <div class="rounded-lg overflow-hidden border border-slate-300 border-l-4 border-l-acode-primary bg-slate-100">
                                    <div class="bg-white/80 px-3.5 py-1.5 border-b border-slate-200 text-xs font-mono text-slate-600 flex items-center justify-between">
                                        <span>starter_code.{{ $question->questionable->fileExtension() }}</span>
                                        <span class="uppercase tracking-wider text-slate-500 font-sans font-semibold">Sólo lectura</span>
                                    </div>
                                    <pre class="p-3.5 text-xs font-mono text-acode-ink overflow-x-auto leading-5"><code>{{ $question->questionable->starter_code }}</code></pre>
                                </div>
                            </div>
                        @endif

                        {{-- Solución de Referencia --}}
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="block text-sm font-medium text-slate-800">
                                    Solución Canónica de Referencia
                                </span>
                                <span class="text-xs font-mono text-acode-accent font-semibold">Patrón Evaluación</span>
                            </div>
                            <div class="rounded-lg overflow-hidden border border-slate-300 border-l-4 border-l-acode-accent bg-slate-100">
                                <div class="bg-white/80 px-3.5 py-1.5 border-b border-slate-200 text-xs font-mono text-slate-600 flex items-center justify-between">
                                    <span>solution.{{ $question->questionable->fileExtension() }}</span>
                                    <span class="uppercase tracking-wider text-acode-accent font-sans font-semibold">Respuesta Correcta</span>
                                </div>
                                <pre class="p-3.5 text-xs font-mono text-acode-ink overflow-x-auto leading-5"><code>{{ $question->questionable->reference_solution }}</code></pre>
                            </div>
                        </div>
                    </div>
                </x-card>
                @break
        @endswitch

        {{-- Panel de Paráfrasis Anti-Copia (RF-BAN-010) --}}
        <x-card>
            <div
                class="space-y-5"
                x-data="{
                    openNewForm: @js($errors->paraphrase->any() || $errors->has('stem')),
                    paraphraseStem: @js(old('stem', '')),
                    originalStem: @js($question->stem),
                    get matchesOriginal() {
                        const a = this.paraphraseStem.trim().replace(/\s+/g, ' ').toLowerCase();
                        const b = (this.originalStem || '').trim().replace(/\s+/g, ' ').toLowerCase();
                        return a.length > 0 && a === b;
                    }
                }"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-acode-primary leading-6">Blindaje Anti-Copia (Paráfrasis)</h2>
                            <x-badge :variant="$question->paraphrases->count() > 0 ? 'success' : 'neutral'" class="font-mono">
                                {{ $question->paraphrases->count() }} registradas
                            </x-badge>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Variantes de redacción pedagógicamente equivalentes: durante el examen, el sistema alterna de forma equitativa entre el enunciado original y las paráfrasis registradas entre distintos alumnos, sin cambiar las opciones ni la respuesta correcta.
                        </p>
                    </div>

                    <x-button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="openNewForm = !openNewForm"
                    >
                        <x-icon name="add" class="w-4 h-4" />
                        <span x-text="openNewForm ? 'Cerrar formulario' : 'Agregar paráfrasis'"></span>
                    </x-button>
                </div>

                @if ($isLinkedToExams)
                    <div class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 px-3.5 py-2.5 text-xs text-acode-primary flex items-start gap-2">
                        <x-icon name="warning" class="w-4 h-4 text-acode-warning-dark shrink-0 mt-0.5" />
                        <span>
                            <strong>Reactivo vinculado a exámenes:</strong> las nuevas paráfrasis se incorporan sin alterar la versión ni las respuestas de intentos previos o en curso.
                        </span>
                    </div>
                @endif

                {{-- Formulario Integrado para Crear Paráfrasis --}}
                <div
                    x-show="openNewForm"
                    x-cloak
                    x-transition
                    class="p-4 rounded-xl bg-acode-surface/70 border border-acode-tech/30 space-y-3"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-acode-primary">Nueva Variante de Redacción</h3>
                            <p class="text-xs text-slate-600">Conserva la misma respuesta correcta pero formula el problema con diferente redacción o contexto (3 a 500 caracteres).</p>
                        </div>
                        <x-button
                            type="button"
                            variant="neutral"
                            size="sm"
                            :icon-only="true"
                            x-on:click="openNewForm = false"
                            title="Cerrar formulario de paráfrasis"
                            aria-label="Cerrar formulario de paráfrasis"
                        >
                            <x-icon name="xMark" class="w-4 h-4" />
                        </x-button>
                    </div>

                    {{-- Enunciado original de referencia --}}
                    <div class="p-3 bg-white rounded-lg border border-slate-200 text-sm">
                        <span class="font-semibold text-slate-500 block text-xs uppercase tracking-wider mb-1">Enunciado Original de Referencia</span>
                        <p class="text-slate-700 whitespace-pre-wrap break-words leading-5">"{{ $question->stem }}"</p>
                    </div>

                    @if ($errors->paraphrase->any() || $errors->has('stem'))
                        <div class="rounded-lg border border-acode-danger/30 bg-acode-danger/10 px-3 py-2 text-xs text-acode-danger">
                            {{ $errors->paraphrase->first() ?: $errors->first('stem') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('questions.paraphrase.store', $question) }}" class="space-y-3">
                        @csrf
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="paraphrase_stem" class="block text-sm font-medium text-slate-800">
                                    Redacción alternativa de la pregunta
                                </label>
                                <span
                                    class="text-xs font-mono"
                                    :class="paraphraseStem.length > 480 ? 'text-acode-danger font-bold' : 'text-slate-500'"
                                    x-text="paraphraseStem.length + ' / 500'"
                                ></span>
                            </div>
                            <x-form.textarea
                                name="stem"
                                id="paraphrase_stem"
                                rows="3"
                                minlength="3"
                                maxlength="500"
                                required
                                x-model="paraphraseStem"
                                :hasError="$errors->paraphrase->any() || $errors->has('stem')"
                                placeholder="Escribe aquí el enunciado equivalente para alternar entre alumnos…"
                            />
                            <p x-show="matchesOriginal" x-cloak class="mt-1 text-xs font-medium text-acode-danger">
                                La paráfrasis debe redactarse de forma distinta al enunciado original.
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <x-button type="button" variant="neutral" size="sm" @click="openNewForm = false">
                                Cancelar
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm" x-bind:disabled="matchesOriginal">
                                <x-icon name="save" class="w-3.5 h-3.5" />
                                Guardar paráfrasis
                            </x-button>
                        </div>
                    </form>
                </div>

                {{-- Listado de Paráfrasis Existentes --}}
                <div class="space-y-2.5">
                    @forelse ($question->paraphrases as $paraphrase)
                        <div class="p-3.5 rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition flex items-start justify-between gap-3">
                            <div class="space-y-1.5 flex-1">
                                <div class="flex items-center gap-2">
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
                                    aria-label="Eliminar paráfrasis"
                                >
                                    <x-icon name="delete" class="w-4 h-4" />
                                </x-button>
                            </form>
                        </div>
                    @empty
                        <x-empty-state
                            icon="edit"
                            title="Sin paráfrasis registradas"
                            message="Todos los alumnos recibirán el enunciado principal idéntico a menos que agregues variantes."
                        />
                    @endforelse
                </div>
            </div>
        </x-card>

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
                    Eliminar
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
