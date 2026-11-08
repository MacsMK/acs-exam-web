{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/index.blade.php (MPW-V2 §2.4, §5.2.2, §6.3, §7.3, §7.8.2, §8.4, §8.7, §8.8, Tabla 20, Tabla 31) --}}
@extends('layouts.app')

@section('title', 'Banco de Reactivos')

@section('content')
    {{-- Cabecera Superior Institucional con Acciones Primarias --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-acode-primary tracking-tight">Banco de Reactivos</h1>
            <p class="text-sm text-slate-500 mt-0.5">Repositorio institucional de preguntas polimórficas con soporte de paráfrasis anti-copia.</p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            {{-- Botón secundario: Importar CSV --}}
            <x-button
                type="button"
                variant="neutral"
                size="md"
                x-data
                @click="$dispatch('open-modal', 'import-csv-modal')"
            >
                <x-icon name="upload" class="w-4 h-4 mr-1.5 text-acode-tech" />
                Importar CSV
            </x-button>

            {{-- Botón primario: Crear reactivo --}}
            <x-button :href="route('questions.create')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4 mr-1.5" />
                Crear reactivo
            </x-button>
        </div>
    </div>

    {{-- Modal Alpine.js para Importación Masiva CSV (MPW-V2 §8.6) --}}
    <x-modal name="import-csv-modal" title="Importar reactivos desde archivo CSV" maxWidth="lg">
        <form
            id="import-csv-form"
            method="POST"
            action="{{ route('questions.import') }}"
            enctype="multipart/form-data"
            class="space-y-4"
        >
            @csrf

            <p class="text-xs text-slate-600 leading-relaxed">
                Sube un archivo delimitado por comas (<span class="font-mono font-semibold text-slate-700">.csv</span>)
                con la estructura institucional requerida para dar de alta reactivos de forma masiva en tu catálogo docente.
            </p>

            {{-- Enlace a la plantilla CSV oficial --}}
            <div class="p-3 bg-acode-surface/80 rounded-xl border border-slate-200 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-acode-tech/15 text-acode-tech flex items-center justify-center shrink-0">
                        <x-icon name="document" class="w-4 h-4" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">Plantilla CSV institucional</p>
                        <p class="text-[11px] text-slate-500">Incluye ejemplos de los 4 tipos de reactivos</p>
                    </div>
                </div>
                <a
                    href="{{ route('questions.template.csv') }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 hover:text-acode-tech transition shadow-xs"
                >
                    <x-icon name="downloadReport" class="w-3.5 h-3.5 text-acode-tech" />
                    Descargar
                </a>
            </div>

            {{-- Campo selector de archivo --}}
            <x-form.group
                name="csv_file"
                label="Seleccionar archivo CSV"
                helpText="Tamaño máximo permitido: 2 MB. Codificación UTF-8 recomendada."
                required
            >
                <input
                    type="file"
                    name="csv_file"
                    id="csv_file"
                    accept=".csv,text/csv,text/plain"
                    required
                    class="block w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-acode-primary file:text-white hover:file:bg-acode-primary-dark cursor-pointer border border-slate-300 rounded-lg p-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-acode-tech"
                />
            </x-form.group>
        </form>

        <x-slot:footer>
            <x-button
                type="button"
                variant="neutral"
                size="md"
                x-data
                @click="$dispatch('close-modal', 'import-csv-modal')"
            >
                Cancelar
            </x-button>

            <x-button
                type="submit"
                form="import-csv-form"
                variant="primary"
                size="md"
            >
                <x-icon name="upload" class="w-4 h-4 mr-1.5" />
                Importar reactivos
            </x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Barra de Control, Búsqueda Predictiva y Filtros Múltiples (MPW-V2 §8.8) --}}
    <x-search-filter
        :action="route('questions.index')"
        :clearUrl="route('questions.index')"
        placeholder="Buscar reactivo por título, enunciado o materia…"
    >
        <x-slot:filters>
            {{-- Filtro: Tipo de Reactivo --}}
            <div class="w-full sm:w-auto">
                <select
                    name="question_type"
                    onchange="this.form.submit()"
                    class="h-10 text-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech w-full"
                    aria-label="Filtrar por tipo de reactivo"
                >
                    <option value="">Todos los tipos</option>
                    <option value="multiple_choice" {{ request('question_type') === 'multiple_choice' ? 'selected' : '' }}>Opción múltiple</option>
                    <option value="matching" {{ request('question_type') === 'matching' ? 'selected' : '' }}>Relación de columnas</option>
                    <option value="open" {{ request('question_type') === 'open' ? 'selected' : '' }}>Pregunta abierta</option>
                    <option value="code" {{ request('question_type') === 'code' ? 'selected' : '' }}>Código fuente</option>
                </select>
            </div>

            {{-- Filtro: Materia / Categoría temática --}}
            <div class="w-full sm:w-auto">
                <select
                    name="subject_tag"
                    onchange="this.form.submit()"
                    class="h-10 text-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech w-full"
                    aria-label="Filtrar por materia"
                >
                    <option value="">Todas las materias</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject }}" {{ request('subject_tag') === $subject ? 'selected' : '' }}>
                            {{ $subject }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filtro: Blindaje Anti-Copia (Paráfrasis) --}}
            <div class="w-full sm:w-auto">
                <select
                    name="paraphrase_status"
                    onchange="this.form.submit()"
                    class="h-10 text-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech w-full"
                    aria-label="Filtrar por blindaje anti-copia"
                >
                    <option value="">Todas las preguntas</option>
                    <option value="with" {{ request('paraphrase_status') === 'with' ? 'selected' : '' }}>Con paráfrasis</option>
                    <option value="without" {{ request('paraphrase_status') === 'without' ? 'selected' : '' }}>Sin paráfrasis</option>
                </select>
            </div>

            {{-- Filtro: Criterio de Orden --}}
            <div class="w-full sm:w-auto">
                <select
                    name="sort_by"
                    onchange="this.form.submit()"
                    class="h-10 text-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech w-full"
                    aria-label="Ordenar por criterio"
                >
                    <option value="recent" {{ request('sort_by', 'recent') === 'recent' ? 'selected' : '' }}>Más recientes</option>
                    <option value="points_desc" {{ request('sort_by') === 'points_desc' ? 'selected' : '' }}>Puntos (Mayor a menor)</option>
                    <option value="points_asc" {{ request('sort_by') === 'points_asc' ? 'selected' : '' }}>Puntos (Menor a mayor)</option>
                    <option value="title_asc" {{ request('sort_by') === 'title_asc' ? 'selected' : '' }}>Título (A-Z)</option>
                </select>
            </div>

            {{-- Filtro: Paginación por página --}}
            <div class="w-full sm:w-auto">
                <select
                    name="per_page"
                    onchange="this.form.submit()"
                    class="h-10 text-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech w-full"
                    aria-label="Registros por página"
                >
                    <option value="10" {{ (int) request('per_page', $perPage) === 10 ? 'selected' : '' }}>10 por pág.</option>
                    <option value="20" {{ (int) request('per_page', $perPage) === 20 ? 'selected' : '' }}>20 por pág.</option>
                    <option value="50" {{ (int) request('per_page', $perPage) === 50 ? 'selected' : '' }}>50 por pág.</option>
                </select>
            </div>
        </x-slot:filters>
    </x-search-filter>

    {{-- Tabla de Reactivos del Banco --}}
    <x-table>
        <x-slot:header>
            <x-table.th>Título / Enunciado</x-table.th>
            <x-table.th>Tipo de Reactivo</x-table.th>
            <x-table.th>Materia</x-table.th>
            <x-table.th align="center">Puntos</x-table.th>
            <x-table.th align="center">Paráfrasis</x-table.th>
            <x-table.th align="right">Acciones</x-table.th>
        </x-slot:header>

        @forelse ($questions as $question)
            <tr class="hover:bg-slate-50/60 transition-colors">
                <x-table.td>
                    <div class="flex items-center gap-2">
                        <a
                            href="{{ route('questions.show', $question) }}"
                            class="font-bold text-slate-800 hover:text-acode-tech transition leading-snug"
                        >
                            {{ $question->title }}
                        </a>

                        @if ($question->parent_question_id)
                            <span class="inline-flex items-center text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200" title="Reactivo derivado de versión histórica">
                                v2 (derivada)
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 line-clamp-1 mt-0.5">{{ $question->stem }}</p>
                </x-table.td>

                <x-table.td>
                    @php
                        $qType = $question->question_type ?? $question->type;
                    @endphp
                    @switch($qType)
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
                            <x-badge variant="neutral">{{ $qType }}</x-badge>
                    @endswitch
                </x-table.td>

                <x-table.td>
                    <span class="text-xs text-slate-600 font-medium">{{ $question->subject_tag ?? 'General' }}</span>
                </x-table.td>

                <x-table.td align="center">
                    <span class="font-mono font-semibold text-slate-800">{{ number_format((float) $question->default_points, 1) }}</span>
                </x-table.td>

                <x-table.td align="center">
                    @php
                        $pCount = $question->paraphrases_count ?? ($question->relationLoaded('paraphrases') ? $question->paraphrases->count() : 0);
                    @endphp
                    <span
                        class="inline-flex items-center gap-1 font-mono text-xs font-semibold px-2 py-0.5 rounded-full {{ $pCount > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600' }}"
                        title="{{ $pCount }} variantes de redacción anti-copia"
                    >
                        {{ $pCount }}
                    </span>
                </x-table.td>

                <x-table.td align="right">
                    <div class="flex items-center justify-end gap-1.5">
                        {{-- Botón: Ver detalles --}}
                        <a
                            href="{{ route('questions.show', $question) }}"
                            class="p-1.5 text-slate-500 hover:text-acode-tech hover:bg-slate-100 rounded-lg transition"
                            title="Ver detalles del reactivo"
                            aria-label="Ver detalles"
                        >
                            <x-icon name="view" class="w-4 h-4" />
                        </a>

                        {{-- Botón: Editar --}}
                        <a
                            href="{{ route('questions.edit', $question) }}"
                            class="p-1.5 text-slate-500 hover:text-acode-primary hover:bg-slate-100 rounded-lg transition"
                            title="Editar reactivo"
                            aria-label="Editar"
                        >
                            <x-icon name="edit" class="w-4 h-4" />
                        </a>

                        {{-- Botón: Duplicar --}}
                        <form
                            id="duplicate-form-{{ $question->id }}"
                            method="POST"
                            action="{{ route('questions.duplicate', $question) }}"
                            class="inline"
                        >
                            @csrf
                            <button
                                type="button"
                                onclick="confirmDuplicate({{ $question->id }}, '{{ addslashes($question->title) }}')"
                                class="p-1.5 text-slate-500 hover:text-acode-accent hover:bg-slate-100 rounded-lg transition"
                                title="Duplicar reactivo"
                                aria-label="Duplicar"
                            >
                                <x-icon name="copy" class="w-4 h-4" />
                            </button>
                        </form>

                        {{-- Botón: Eliminar --}}
                        <form
                            id="delete-form-{{ $question->id }}"
                            method="POST"
                            action="{{ route('questions.destroy', $question) }}"
                            class="inline"
                        >
                            @csrf
                            @method('DELETE')
                            <button
                                type="button"
                                onclick="confirmDelete({{ $question->id }}, '{{ addslashes($question->title) }}')"
                                class="p-1.5 text-slate-400 hover:text-acode-danger hover:bg-red-50 rounded-lg transition"
                                title="Eliminar reactivo"
                                aria-label="Eliminar"
                            >
                                <x-icon name="delete" class="w-4 h-4" />
                            </button>
                        </form>
                    </div>
                </x-table.td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="p-0">
                    <x-empty-state
                        class="border-0 shadow-none rounded-none py-10"
                        icon="edit"
                        title="Banco de reactivos vacío"
                        message="Todavía no cuentas con reactivos registrados que coincidan con los criterios seleccionados. Da de alta tu primer reactivo o importa un archivo CSV."
                    >
                        <div class="flex items-center gap-2 justify-center">
                            <x-button :href="route('questions.create')" variant="primary" size="sm">
                                <x-icon name="add" class="w-4 h-4 mr-1.5" />
                                Crear reactivo
                            </x-button>
                            <x-button
                                type="button"
                                variant="neutral"
                                size="sm"
                                x-data
                                @click="$dispatch('open-modal', 'import-csv-modal')"
                            >
                                <x-icon name="upload" class="w-4 h-4 mr-1.5 text-acode-tech" />
                                Importar CSV
                            </x-button>
                        </div>
                    </x-empty-state>
                </td>
            </tr>
        @endforelse
    </x-table>

    {{-- Paginación con persistencia de query string --}}
    <div class="mt-4">
        {{ $questions->withQueryString()->links() }}
    </div>

    @push('scripts')
        <script>
            function confirmDuplicate(questionId, title) {
                if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.confirm({
                        title: '¿Duplicar reactivo?',
                        text: 'Se creará una copia idéntica del reactivo "' + title + '" en tu banco docente para edición independiente.',
                        confirmButtonText: 'Duplicar',
                        cancelButtonText: 'Cancelar',
                        isDanger: false,
                        onConfirm: function() {
                            document.getElementById('duplicate-form-' + questionId).submit();
                        }
                    });
                    return;
                }

                if (confirm('¿Deseas duplicar el reactivo "' + title + '"?')) {
                    document.getElementById('duplicate-form-' + questionId).submit();
                }
            }

            function confirmDelete(questionId, title) {
                if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.confirm({
                        title: '¿Eliminar reactivo?',
                        text: 'El reactivo "' + title + '" será retirado de tu catálogo activo. Los exámenes históricos mantendrán sus registros protegidos.',
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar',
                        isDanger: true,
                        onConfirm: function() {
                            document.getElementById('delete-form-' + questionId).submit();
                        }
                    });
                    return;
                }

                if (confirm('¿Deseas eliminar el reactivo "' + title + '"?')) {
                    document.getElementById('delete-form-' + questionId).submit();
                }
            }
        </script>
    @endpush
@endsection
