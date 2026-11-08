{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/show.blade.php (MPW-V2 §2.4, §5.2.2, §6.3, §7.3, §7.8, §8.4, §8.7, Tabla 20, Tabla 31, RF-BAN-007, RF-BAN-010) --}}
@extends('layouts.app')

@section('title', 'Detalle del Reactivo · ' . $question->title)

@section('content')
    <div class="space-y-6 max-w-5xl mx-auto">
        {{-- Barra de Navegación y Breadcrumb Institucional --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <nav class="flex items-center gap-2 text-xs text-slate-500 select-none" aria-label="Migas de pan">
                <a href="{{ route('questions.index') }}" class="hover:text-acode-tech transition flex items-center gap-1 font-medium">
                    <x-icon name="arrowLeft" class="w-3.5 h-3.5" />
                    <span>Banco de reactivos</span>
                </a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-700 truncate max-w-xs sm:max-w-md">Detalle del reactivo</span>
            </nav>

            <div class="flex items-center gap-2">
                <x-button :href="route('questions.index')" variant="neutral" size="sm">
                    <x-icon name="arrowLeft" class="w-4 h-4 mr-1.5" />
                    Volver al banco
                </x-button>

                <x-button :href="route('questions.edit', $question)" variant="primary" size="sm">
                    <x-icon name="edit" class="w-4 h-4 mr-1.5" />
                    Editar reactivo
                </x-button>
            </div>
        </div>

        {{-- Cabecera Principal del Reactivo --}}
        <x-card>
            <div class="space-y-4">
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div class="space-y-2 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            {{-- Badge de Tipo de Reactivo --}}
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

                            {{-- Badge de Materia --}}
                            <span class="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $question->subject_tag ?? 'Materia General' }}
                            </span>

                            {{-- Estado de Vinculación a Exámenes (Integridad Histórica) --}}
                            @if ($isLinkedToExams)
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                                    <x-icon name="lock" class="w-3.5 h-3.5 text-amber-600" />
                                    En exámenes activos/históricos
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-50 text-slate-600 border border-slate-200">
                                    Sin vincular a exámenes
                                </span>
                            @endif

                            {{-- Enlace a Versión Previa / Derivada --}}
                            @if ($question->parent)
                                <a
                                    href="{{ route('questions.show', $question->parent) }}"
                                    class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-50 text-acode-tech border border-blue-200 hover:bg-blue-100 transition"
                                    title="Consultar la versión previa original"
                                >
                                    <x-icon name="view" class="w-3.5 h-3.5" />
                                    Versión previa: #{{ $question->parent->id }}
                                </a>
                            @endif
                        </div>

                        <h1 class="text-xl sm:text-2xl font-bold text-acode-primary tracking-tight">
                            {{ $question->title }}
                        </h1>
                    </div>

                    {{-- Métricas de Evaluación: Puntos y Tiempo --}}
                    <div class="flex items-center gap-3 shrink-0 bg-acode-surface/80 p-3 rounded-xl border border-slate-200">
                        <div class="text-center px-2">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Puntos</span>
                            <span class="font-mono text-lg font-bold text-acode-primary">{{ number_format((float) $question->default_points, 1) }}</span>
                        </div>
                        <div class="h-8 w-px bg-slate-200"></div>
                        <div class="text-center px-2">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Tiempo</span>
                            <span class="font-mono text-lg font-bold text-acode-tech">
                                @if ($question->default_time_seconds >= 60)
                                    {{ floor($question->default_time_seconds / 60) }}m {{ $question->default_time_seconds % 60 > 0 ? ($question->default_time_seconds % 60).'s' : '' }}
                                @else
                                    {{ $question->default_time_seconds }}s
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Enunciado Principal (Stem) --}}
                <div class="pt-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                        Enunciado o Planteamiento Principal
                    </label>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 text-sm sm:text-base leading-relaxed whitespace-pre-line font-medium">
                        {{ $question->stem }}
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Desglose Polimórfico Específico según question_type --}}
        @switch($question->question_type)
            {{-- 1. Opción Múltiple --}}
            @case('multiple_choice')
                <x-card>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h2 class="text-base font-bold text-acode-primary">Alternativas de Respuesta</h2>
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
                                <div class="flex items-center justify-between p-3.5 rounded-xl border transition {{ $isCorrect ? 'bg-emerald-50 border-emerald-300 text-emerald-900 font-semibold shadow-xs' : 'bg-white border-slate-200 text-slate-700' }}">
                                    <div class="flex items-center gap-3">
                                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold {{ $isCorrect ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600' }}">
                                            {{ chr(65 + $loop->index) }}
                                        </span>
                                        <span class="text-sm leading-snug">{{ $optText }}</span>
                                    </div>
                                    @if ($isCorrect)
                                        <div class="flex items-center gap-1.5 text-xs text-emerald-800 bg-emerald-100/90 px-3 py-1 rounded-full font-bold">
                                            <x-icon name="check" class="w-3.5 h-3.5 text-emerald-700" />
                                            <span>Respuesta Correcta</span>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 italic py-3 text-center">No hay opciones registradas para este reactivo.</p>
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
                                <h2 class="text-base font-bold text-acode-primary">Correspondencia Biyectiva (Concepto ↔ Definición)</h2>
                                <p class="text-xs text-slate-500">Pares solución canónicos definidos para la evaluación.</p>
                            </div>
                            <x-badge variant="accent">Emparejamiento</x-badge>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="py-3 px-4 w-5/12">Columna A: Concepto / Premisa</th>
                                        <th class="py-3 px-2 w-1/12 text-center">Vínculo</th>
                                        <th class="py-3 px-4 w-6/12">Columna B: Definición / Respuesta</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($question->questionable->pairs_payload ?? [] as $pair)
                                        @php
                                            $left = collect($question->questionable->left_column_payload ?? [])->firstWhere('id', $pair['left_id'] ?? null);
                                            $right = collect($question->questionable->right_column_payload ?? [])->firstWhere('id', $pair['right_id'] ?? null);
                                        @endphp
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="py-3 px-4 align-top">
                                                <div class="flex items-start gap-2">
                                                    <span class="font-mono text-[10px] font-bold text-acode-primary bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 shrink-0">
                                                        {{ $pair['left_id'] ?? 'L'.($loop->iteration) }}
                                                    </span>
                                                    <span class="text-slate-800 font-semibold text-xs leading-relaxed">
                                                        {{ is_array($left) ? ($left['text'] ?? '') : (string) $left }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="py-3 px-2 align-middle text-center text-acode-accent font-bold select-none">
                                                ↔
                                            </td>
                                            <td class="py-3 px-4 align-top">
                                                <div class="flex items-start gap-2">
                                                    <span class="font-mono text-[10px] font-bold text-teal-800 bg-teal-50 px-1.5 py-0.5 rounded border border-teal-200 shrink-0">
                                                        {{ $pair['right_id'] ?? 'R'.($loop->iteration) }}
                                                    </span>
                                                    <span class="text-slate-700 text-xs leading-relaxed">
                                                        {{ is_array($right) ? ($right['text'] ?? '') : (string) $right }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="py-4 text-center text-slate-400 italic">No hay pares registrados para este reactivo.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
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
                                    <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                                        <x-icon name="check" class="w-4 h-4" />
                                    </div>
                                    <h2 class="text-base font-bold text-acode-primary">Respuesta Modelo Canónica</h2>
                                </div>
                                <x-badge variant="warning">Patrón de Cotejo IA</x-badge>
                            </div>
                            <p class="text-xs text-slate-500">
                                Solución de referencia empleada por el evaluador asistido para calcular la similitud semántica.
                            </p>
                            <div class="p-4 rounded-xl bg-amber-50/60 border border-amber-200 text-slate-800 text-sm leading-relaxed whitespace-pre-line font-medium">
                                {{ $question->questionable->reference_answer ?? 'No registrada' }}
                            </div>
                        </div>
                    </x-card>

                    {{-- Rúbrica de Evaluación --}}
                    @if (!empty($question->questionable->evaluation_rubric))
                        <x-card>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-acode-primary">Rúbrica de Evaluación Pedagógica</h3>
                                    <x-badge variant="neutral">Criterios Cualitativos</x-badge>
                                </div>
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 text-xs leading-relaxed whitespace-pre-line">
                                    {{ $question->questionable->evaluation_rubric }}
                                </div>
                            </div>
                        </x-card>
                    @endif
                </div>
                @break

            {{-- 4. Código Fuente --}}
            @case('code')
                <x-card>
                    <div class="space-y-5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                            <div>
                                <h2 class="text-base font-bold text-acode-primary">Configuración del Entorno de Código</h2>
                                <p class="text-xs text-slate-500">Parámetros del editor en línea y solución canónica para la prueba técnica.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    {{ $question->questionable->programming_language ?? 'General' }}
                                </span>
                                @if ($question->questionable->is_find_error)
                                    <x-badge variant="warning">Depuración / Find Error</x-badge>
                                @else
                                    <x-badge variant="info">Desarrollo desde cero</x-badge>
                                @endif
                            </div>
                        </div>

                        {{-- Instrucciones técnicas --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">
                                Instrucciones Técnicas
                            </label>
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 leading-relaxed">
                                {{ $question->questionable->instructions }}
                            </div>
                        </div>

                        {{-- Código inicial si aplica --}}
                        @if (!empty($question->questionable->starter_code))
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-bold text-slate-700">
                                        {{ $question->questionable->is_find_error ? 'Código Defectuoso para Auditoría (Starter Code)' : 'Código Base / Plantilla Inicial' }}
                                    </label>
                                    <span class="text-[10px] font-mono text-slate-400">Editor Alumno</span>
                                </div>
                                <div class="rounded-xl overflow-hidden border border-slate-700 bg-slate-900 shadow-sm">
                                    <div class="bg-slate-800 px-3.5 py-1.5 border-b border-slate-700 text-[11px] font-mono text-slate-300 flex items-center justify-between">
                                        <span>starter_code.{{ strtolower($question->questionable->programming_language ?? 'txt') }}</span>
                                        <span class="text-[9px] uppercase tracking-wider text-slate-400">Sólo lectura</span>
                                    </div>
                                    <pre class="p-3.5 text-xs font-mono text-slate-100 overflow-x-auto leading-relaxed"><code>{{ $question->questionable->starter_code }}</code></pre>
                                </div>
                            </div>
                        @endif

                        {{-- Solución de Referencia --}}
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-700">
                                    Solución Canónica de Referencia
                                </label>
                                <span class="text-[10px] font-mono text-emerald-600 font-semibold">Patrón Evaluación</span>
                            </div>
                            <div class="rounded-xl overflow-hidden border border-slate-700 bg-slate-900 shadow-sm">
                                <div class="bg-slate-800 px-3.5 py-1.5 border-b border-slate-700 text-[11px] font-mono text-slate-300 flex items-center justify-between">
                                    <span>solution.{{ strtolower($question->questionable->programming_language ?? 'txt') }}</span>
                                    <span class="text-[9px] uppercase tracking-wider text-emerald-400 font-semibold">Respuesta Correcta</span>
                                </div>
                                <pre class="p-3.5 text-xs font-mono text-emerald-300 overflow-x-auto leading-relaxed"><code>{{ $question->questionable->reference_solution }}</code></pre>
                            </div>
                        </div>
                    </div>
                </x-card>
                @break
        @endswitch

        {{-- Panel de Paráfrasis Anti-Copia (RF-BAN-010) --}}
        <x-card>
            <div class="space-y-5" x-data="{ openNewForm: false }">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-acode-primary">Blindaje Anti-Copia (Paráfrasis)</h2>
                            <span class="inline-flex items-center font-mono text-xs font-semibold px-2 py-0.5 rounded-full {{ $question->paraphrases->count() > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600' }}">
                                {{ $question->paraphrases->count() }} registradas
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Variantes de redacción pedagógicamente equivalentes sorteadas aleatoriamente para evitar copias entre alumnos.
                        </p>
                    </div>

                    <x-button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="openNewForm = !openNewForm"
                    >
                        <x-icon name="add" class="w-4 h-4 mr-1.5" />
                        <span x-text="openNewForm ? 'Ocultar formulario' : 'Agregar paráfrasis'"></span>
                    </x-button>
                </div>

                {{-- Formulario Integrado para Crear Paráfrasis --}}
                <div
                    x-show="openNewForm"
                    x-cloak
                    x-transition
                    class="p-4 rounded-xl bg-acode-surface/70 border border-acode-tech/30 space-y-3"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-xs font-bold text-acode-primary uppercase tracking-wider">Nueva Variante de Redacción</h3>
                            <p class="text-[11px] text-slate-500">Conserva la misma respuesta correcta pero formula el problema con diferente redacción o contexto.</p>
                        </div>
                        <button
                            type="button"
                            @click="openNewForm = false"
                            class="text-slate-400 hover:text-slate-600 p-1"
                        >
                            <x-icon name="xMark" class="w-4 h-4" />
                        </button>
                    </div>

                    {{-- Enunciado original de referencia --}}
                    <div class="p-2.5 bg-white rounded-lg border border-slate-200 text-xs">
                        <span class="font-bold text-slate-400 block text-[10px] uppercase">Enunciado Original de Referencia:</span>
                        <p class="text-slate-700 mt-0.5 italic">"{{ $question->stem }}"</p>
                    </div>

                    <form method="POST" action="{{ route('questions.paraphrase.store', $question) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="paraphrase_stem" class="block text-xs font-semibold text-slate-700 mb-1">
                                Redacción alternativa de la pregunta
                            </label>
                            <textarea
                                name="stem"
                                id="paraphrase_stem"
                                rows="3"
                                required
                                placeholder="Escribe aquí el enunciado equivalente para sortear en el examen…"
                                class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:outline-none focus:ring-2 focus:ring-acode-tech bg-white"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <x-button type="button" variant="neutral" size="sm" @click="openNewForm = false">
                                Cancelar
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm">
                                <x-icon name="save" class="w-3.5 h-3.5 mr-1" />
                                Guardar paráfrasis
                            </x-button>
                        </div>
                    </form>
                </div>

                {{-- Listado de Paráfrasis Existentes --}}
                <div class="space-y-2.5">
                    @forelse ($question->paraphrases as $paraphrase)
                        <div class="p-3.5 rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition flex items-start justify-between gap-3">
                            <div class="space-y-1 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-acode-tech bg-blue-50 px-2 py-0.5 rounded border border-blue-100">
                                        Variante #{{ $loop->iteration }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">
                                        Registrada {{ $paraphrase->created_at?->diffForHumans() ?? 'recientemente' }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-800 leading-relaxed font-medium">
                                    {{ $paraphrase->stem }}
                                </p>
                            </div>

                            <form
                                id="delete-paraphrase-form-{{ $paraphrase->id }}"
                                method="POST"
                                action="{{ route('questions.paraphrase.destroy', [$question, $paraphrase]) }}"
                                class="shrink-0"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="button"
                                    onclick="confirmDeleteParaphrase({{ $paraphrase->id }})"
                                    class="p-1.5 text-slate-400 hover:text-acode-danger hover:bg-red-50 rounded-lg transition"
                                    title="Eliminar paráfrasis"
                                    aria-label="Eliminar paráfrasis"
                                >
                                    <x-icon name="delete" class="w-4 h-4" />
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="py-6 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                            <x-icon name="edit" class="w-8 h-8 text-slate-300 mx-auto mb-1.5" />
                            <p class="text-xs font-semibold text-slate-600">Sin paráfrasis registradas</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                Todos los alumnos recibirán el enunciado principal idéntico a menos que agregues variantes.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </x-card>

        {{-- Barra Inferior de Acciones Normativas --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
            <x-button :href="route('questions.index')" variant="neutral" size="md">
                <x-icon name="arrowLeft" class="w-4 h-4 mr-1.5" />
                Volver al banco
            </x-button>

            <div class="flex items-center gap-2">
                {{-- Formulario de Duplicación --}}
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
                        <x-icon name="copy" class="w-4 h-4 mr-1.5" />
                        Duplicar
                    </x-button>
                </form>

                {{-- Botón Editar --}}
                <x-button :href="route('questions.edit', $question)" variant="primary" size="md">
                    <x-icon name="edit" class="w-4 h-4 mr-1.5" />
                    Editar reactivo
                </x-button>

                {{-- Formulario de Eliminación --}}
                <form
                    id="delete-bottom-form"
                    method="POST"
                    action="{{ route('questions.destroy', $question) }}"
                    class="inline"
                >
                    @csrf
                    @method('DELETE')
                    <x-button
                        type="button"
                        variant="danger"
                        size="md"
                        onclick="confirmDeleteBottom()"
                    >
                        <x-icon name="delete" class="w-4 h-4 mr-1.5" />
                        Eliminar
                    </x-button>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function confirmDeleteParaphrase(paraphraseId) {
                if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.confirm({
                        title: '¿Eliminar variante de paráfrasis?',
                        text: 'Esta redacción alternativa ya no será sorteada en los exámenes futuros.',
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar',
                        isDanger: true,
                        onConfirm: function() {
                            document.getElementById('delete-paraphrase-form-' + paraphraseId).submit();
                        }
                    });
                    return;
                }

                if (confirm('¿Eliminar esta redacción alternativa?')) {
                    document.getElementById('delete-paraphrase-form-' + paraphraseId).submit();
                }
            }

            function confirmDuplicateBottom() {
                if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.confirm({
                        title: '¿Duplicar reactivo?',
                        text: 'Se creará una copia idéntica del reactivo "{{ addslashes($question->title) }}" para edición libre.',
                        confirmButtonText: 'Duplicar',
                        cancelButtonText: 'Cancelar',
                        isDanger: false,
                        onConfirm: function() {
                            document.getElementById('duplicate-bottom-form').submit();
                        }
                    });
                    return;
                }

                if (confirm('¿Deseas duplicar este reactivo?')) {
                    document.getElementById('duplicate-bottom-form').submit();
                }
            }

            function confirmDeleteBottom() {
                if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.confirm({
                        title: '¿Eliminar reactivo?',
                        text: 'El reactivo "{{ addslashes($question->title) }}" será retirado de tu catálogo activo.',
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar',
                        isDanger: true,
                        onConfirm: function() {
                            document.getElementById('delete-bottom-form').submit();
                        }
                    });
                    return;
                }

                if (confirm('¿Deseas eliminar este reactivo?')) {
                    document.getElementById('delete-bottom-form').submit();
                }
            }
        </script>
    @endpush
@endsection
