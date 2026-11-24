{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/index.blade.php (MPW-V3 §7, §8, Tabla 10, Tabla 18, RF-BAN-001..012) --}}
@extends('layouts.app')

@section('title', 'Banco de preguntas')

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
        $hasResultFilters = filled($filters['search'] ?? null)
            || filled($filters['question_type'] ?? null)
            || filled($filters['subject_tag'] ?? null)
            || filled($filters['subject_id'] ?? null)
            || filled($filters['paraphrase_status'] ?? null);
        $hasActiveFilters = $hasResultFilters
            || (($filters['sort_by'] ?? 'latest') !== 'latest')
            || ((int) ($perPage ?? 20) !== 20);
        $activeSubjectName = $subjectsStats->firstWhere('id', $filters['subject_id'] ?? null)?->name;

        $quotaUsed = (int) ($quotaSummary['used'] ?? ($summaryMetrics['total_active'] ?? 0));
        $quotaLimit = $quotaSummary['limit'] ?? null;
        $quotaUnlimited = (bool) ($quotaSummary['is_unlimited'] ?? ($quotaLimit === null));
        $quotaPercent = $quotaUnlimited
            ? 100
            : min(100, (int) round(($quotaUsed / max(1, (int) $quotaLimit)) * 100));

        $duplicateSubjectGroups = (int) ($summaryMetrics['duplicate_subject_groups_count'] ?? 0);
        $withParaphrases = (int) ($summaryMetrics['with_paraphrases'] ?? 0);
        $withoutParaphrases = (int) ($summaryMetrics['without_paraphrases'] ?? 0);
        $byType = $summaryMetrics['by_type'] ?? [];
    @endphp

    <div
        class="space-y-6"
        x-data="acodeQuestionBank({
            previewUrl: @js(route('questions.import.preview')),
        })"
    >
        {{-- Banner contextual opcional tras duplicar / copiar un reactivo --}}
        @if (session('duplicated_question_id'))
            <div class="rounded-xl border border-acode-accent/40 bg-acode-accent/10 px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 text-sm text-acode-primary">
                    <x-icon name="copy" class="w-4 h-4 text-acode-accent shrink-0" />
                    <span class="font-medium">La copia del reactivo quedó lista en tu banco. ¿Deseas ajustarla ahora?</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <x-button :href="route('questions.edit', session('duplicated_question_id'))" variant="primary" size="sm">
                        <x-icon name="edit" class="w-4 h-4" />
                        Editar copia
                    </x-button>
                    <x-button :href="route('questions.show', session('duplicated_question_id'))" variant="neutral" size="sm">
                        <x-icon name="view" class="w-4 h-4" />
                        Abrir
                    </x-button>
                </div>
            </div>
        @endif

        {{-- Nivel 1: Cabecera Institucional y Acciones Clave (MPW-V3 §7.3, §7.8.2, §8.3) --}}
        <x-page-header title="Banco de preguntas" :institutional="true">
            <x-slot:badge>
                <x-badge variant="info">
                    Plan {{ $quotaSummary['package_name'] ?? 'Activo' }}
                </x-badge>
            </x-slot:badge>

            <x-slot:actions>
                <x-button
                    type="button"
                    variant="neutral"
                    size="md"
                    @click="$dispatch('open-modal', 'subjects-modal')"
                >
                    <x-icon name="tag" class="w-4 h-4 text-slate-600" />
                    <span>Abrir materias</span>
                    @if ($duplicateSubjectGroups > 0)
                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-xs font-bold bg-acode-warning text-acode-primary">
                            {{ $duplicateSubjectGroups }}<span class="sr-only"> grupos con posibles duplicados</span>
                        </span>
                    @endif
                </x-button>

                <div
                    class="relative w-full sm:w-auto"
                    x-data="{ batchMenuOpen: false }"
                    @click.outside="batchMenuOpen = false"
                    @focusout="if (!$el.contains($event.relatedTarget)) batchMenuOpen = false"
                    @keydown.escape="if (batchMenuOpen) { $event.stopPropagation(); batchMenuOpen = false; $refs.batchMenuTrigger.focus() }"
                >
                    <x-button
                        type="button"
                        variant="secondary"
                        size="md"
                        x-ref="batchMenuTrigger"
                        @click="batchMenuOpen = !batchMenuOpen"
                        x-bind:aria-expanded="batchMenuOpen.toString()"
                        aria-controls="question-batch-actions"
                    >
                        <x-icon name="add" class="w-4 h-4" />
                        <span>Agregar en lote</span>
                        <x-icon name="chevronDown" class="w-4 h-4 transition-transform duration-200" ::class="batchMenuOpen ? 'rotate-180' : ''" />
                    </x-button>

                    <div
                        id="question-batch-actions"
                        x-show="batchMenuOpen"
                        x-cloak
                        x-transition
                        class="absolute left-0 sm:left-auto sm:right-0 z-20 mt-2 w-full max-w-64 rounded-lg border border-slate-200 bg-white py-1.5 shadow-sm sm:w-64"
                    >
                        <a
                            href="{{ route('questions.batch.create') }}"
                            class="flex w-full items-start gap-3 px-4 py-2.5 text-left transition hover:bg-acode-surface/60 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-acode-tech"
                        >
                            <x-icon name="add" class="w-4 h-4 text-acode-tech mt-0.5" />
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-acode-primary">Crear en lote</span>
                                <span class="block text-xs text-slate-600">Captura varios reactivos en un formulario.</span>
                            </span>
                        </a>

                        <button
                            type="button"
                            @click="batchMenuOpen = false; $refs.batchMenuTrigger.dispatchEvent(new CustomEvent('open-modal', { bubbles: true, detail: 'bulk-import-modal' }))"
                            class="flex w-full items-start gap-3 px-4 py-2.5 text-left transition hover:bg-acode-surface/60 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-acode-tech"
                        >
                            <x-icon name="upload" class="w-4 h-4 text-acode-tech mt-0.5" />
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-acode-primary">Importar CSV o Excel</span>
                                <span class="block text-xs text-slate-600">Revisa el archivo antes de guardar.</span>
                            </span>
                        </button>
                    </div>
                </div>

                <x-button :href="route('questions.create')" variant="primary" size="md">
                    <x-icon name="add" class="w-4 h-4" />
                    Crear reactivo
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- El resumen mantiene visibles los datos principales y amplía el resto a demanda. --}}
        <x-card padding="p-0">
            <details class="group">
                <summary class="flex min-h-10 cursor-pointer list-none flex-wrap items-center gap-x-4 gap-y-1 rounded-xl px-4 py-2.5 text-xs text-slate-600 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-acode-tech [&::-webkit-details-marker]:hidden">
                    <span class="text-sm font-semibold text-acode-primary">Indicadores del banco</span>
                    <span class="group-open:hidden">{{ $summaryMetrics['total_active'] ?? 0 }} reactivos activos</span>
                    <span @class(['group-open:hidden', 'font-medium text-acode-danger-dark' => ! $quotaUnlimited && $quotaPercent >= 90])>
                        Cuota del plan
                        @if ($quotaUnlimited)
                            ilimitada
                        @else
                            {{ number_format($quotaUsed) }} / {{ number_format((int) $quotaLimit) }}
                        @endif
                    </span>
                    <x-icon name="chevronDown" class="ml-auto h-4 w-4 text-acode-tech transition-transform group-open:rotate-180 motion-reduce:transition-none" />
                </summary>

                <x-summary-list class="border-t border-slate-200 p-4">
                    <x-summary-item label="Reactivos activos" :value="$summaryMetrics['total_active'] ?? 0" icon="document">
                        @if ($quotaUnlimited)
                            <p class="text-xs text-slate-600">Cuota del plan ilimitada</p>
                        @else
                            <div class="space-y-1.5">
                                <div class="flex flex-wrap items-center justify-between gap-x-2 text-xs">
                                    <span class="text-slate-600">Cuota del plan</span>
                                    <span @class(['font-semibold', 'text-acode-danger-dark' => $quotaPercent >= 90, 'text-acode-primary' => $quotaPercent < 90])>
                                        {{ number_format($quotaUsed) }} / {{ number_format((int) $quotaLimit) }}
                                    </span>
                                </div>
                                <x-progress-bar :value="$quotaPercent" :variant="$quotaPercent >= 95 ? 'danger' : 'tech'" size="sm" label="Cuota del plan utilizada" />
                            </div>
                        @endif
                    </x-summary-item>

                    <x-summary-item label="Blindaje anti-copia" :value="$withParaphrases" note="Con paráfrasis" icon="shield">
                        <div class="flex flex-wrap items-center justify-between gap-x-2 text-xs">
                            <span class="text-slate-600">{{ $withoutParaphrases }} sin paráfrasis</span>
                            @if ($withoutParaphrases > 0)
                                <a
                                    href="{{ route('questions.index', ['paraphrase_status' => 'without']) }}"
                                    class="inline-flex min-h-8 items-center rounded-md font-semibold text-acode-tech hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech"
                                >
                                    Ver sin paráfrasis
                                </a>
                            @elseif ($withParaphrases > 0)
                                <x-badge variant="success">100% con paráfrasis</x-badge>
                            @endif
                        </div>
                    </x-summary-item>

                    <x-summary-item label="Materias registradas" :value="$summaryMetrics['subjects_count'] ?? 0" icon="tag">
                        @if ($duplicateSubjectGroups > 0)
                            <x-button type="button" variant="warning" size="sm" @click="$dispatch('open-modal', 'subjects-modal')" class="whitespace-normal">
                                Posibles duplicados ({{ $duplicateSubjectGroups }})
                            </x-button>
                        @endif
                    </x-summary-item>

                    <x-summary-item label="Distribución por tipo">
                        <dl class="space-y-1.5 text-xs">
                            @foreach ($typeLabels as $type => $label)
                                <div class="flex items-baseline justify-between gap-2">
                                    <dt class="text-slate-600">{{ $label }}</dt>
                                    <dd class="font-semibold tabular-nums text-acode-primary">{{ $byType[$type] ?? 0 }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-summary-item>
                </x-summary-list>
            </details>
        </x-card>

        {{-- Nivel 3: Barra de Búsqueda, Filtros SQL y Alternador Tarjetas / Tabla --}}
        <section class="space-y-3" aria-label="Búsqueda y filtros del banco">
            <x-search-filter
                :action="route('questions.index')"
                search-name="search"
                search-label="Buscar por título interno o enunciado"
                :search-value="$filters['search'] ?? ''"
                placeholder="Buscar por título interno o enunciado…"
                :clear-url="route('questions.index')"
                :clear-keys="['search', 'question_type', 'subject_tag', 'subject_id', 'paraphrase_status', 'sort_by', 'per_page']"
                :has-active-filters="$hasActiveFilters"
            >
                <x-slot:parameters>
                    @if (!empty($filters['subject_id']))
                        <input type="hidden" name="subject_id" value="{{ $filters['subject_id'] }}" x-ref="subjectIdHidden">
                    @endif
                    <input type="hidden" name="per_page" value="{{ (int) ($perPage ?? 20) }}">
                </x-slot:parameters>

                <x-slot:filters>
                    <x-form.group name="subject_tag" label="Materia" class="sm:w-48">
                        <x-form.select
                            name="subject_tag"
                            size="sm"
                            @change="if ($refs.subjectIdHidden) $refs.subjectIdHidden.disabled = true"
                        >
                            <option value="">Todas las materias</option>
                            @foreach ($subjects as $subjectName)
                                <option value="{{ $subjectName }}" @selected(($filters['subject_tag'] ?? '') === $subjectName)>
                                    {{ $subjectName }}
                                </option>
                            @endforeach
                        </x-form.select>
                    </x-form.group>

                    <x-form.group name="question_type" label="Tipo de reactivo" class="sm:w-48">
                        <x-form.select name="question_type" size="sm">
                            <option value="">Todos los tipos</option>
                            @foreach ($typeLabels as $typeValue => $typeText)
                                <option value="{{ $typeValue }}" @selected(($filters['question_type'] ?? '') === $typeValue)>
                                    {{ $typeText }}
                                </option>
                            @endforeach
                        </x-form.select>
                    </x-form.group>

                    <x-form.group name="paraphrase_status" label="Blindaje anti-copia" class="sm:w-44">
                        <x-form.select name="paraphrase_status" size="sm">
                            <option value="">Todos</option>
                            <option value="with" @selected(($filters['paraphrase_status'] ?? '') === 'with')>Con paráfrasis</option>
                            <option value="without" @selected(($filters['paraphrase_status'] ?? '') === 'without')>Sin paráfrasis</option>
                        </x-form.select>
                    </x-form.group>

                    <x-form.group name="sort_by" label="Orden" class="sm:w-44">
                        <x-form.select name="sort_by" size="sm">
                            <option value="latest" @selected(($filters['sort_by'] ?? 'latest') === 'latest')>Más recientes</option>
                            <option value="oldest" @selected(($filters['sort_by'] ?? '') === 'oldest')>Más antiguos</option>
                            <option value="title" @selected(($filters['sort_by'] ?? '') === 'title')>Título (A-Z)</option>
                            <option value="subject" @selected(($filters['sort_by'] ?? '') === 'subject')>Materia (A-Z)</option>
                            <option value="paraphrases" @selected(($filters['sort_by'] ?? '') === 'paraphrases')>Más paráfrasis</option>
                        </x-form.select>
                    </x-form.group>
                </x-slot:filters>
            </x-search-filter>

            {{-- Barra de resumen de resultados, paginación y alternador de vista --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-1">
                <p class="text-sm text-slate-700">
                    @if ($questions->isNotEmpty() && $questions->hasPages())
                        {{ $questions->firstItem() }}–{{ $questions->lastItem() }} de
                    @endif
                    <strong class="font-semibold text-acode-primary">{{ $questions->total() }}</strong>
                    {{ $questions->total() === 1 ? 'reactivo' : 'reactivos' }}
                    @if ($activeSubjectName)
                        <x-badge variant="info" class="ml-1.5">Materia: {{ $activeSubjectName }}</x-badge>
                    @endif
                </p>

                <div class="flex flex-wrap items-center gap-3 self-start sm:self-auto">
                    <form method="GET" action="{{ route('questions.index') }}" class="w-32">
                        @foreach (request()->except(['per_page', 'page']) as $qKey => $qVal)
                            @if (is_scalar($qVal) && $qVal !== '')
                                <input type="hidden" name="{{ $qKey }}" value="{{ $qVal }}">
                            @endif
                        @endforeach
                        <x-form.select
                            name="per_page"
                            size="sm"
                            aria-label="Resultados por página"
                            onchange="this.form.submit()"
                        >
                            @foreach ([10, 20, 50] as $pageSizeOption)
                                <option value="{{ $pageSizeOption }}" @selected((int) ($perPage ?? 20) === $pageSizeOption)>
                                    {{ $pageSizeOption }} / pág.
                                </option>
                            @endforeach
                        </x-form.select>
                    </form>

                    <x-toggle-group
                        model="viewMode"
                        select="setViewMode"
                        variant="segmented"
                        label="Cambiar vista del catálogo"
                        :options="[
                            ['value' => 'cards', 'label' => 'Tarjetas', 'icon' => 'squares'],
                            ['value' => 'table', 'label' => 'Tabla compacta', 'icon' => 'table'],
                        ]"
                    />
                </div>
            </div>
        </section>

        {{-- Nivel 4: Catálogo (Tarjetas + Tabla Compacta) y Paginación Universal --}}
        @if ($questions->isEmpty())
            <x-empty-state
                icon="document"
                :title="$hasResultFilters ? 'Sin reactivos con esos filtros' : 'Tu banco de preguntas está vacío'"
                :message="$hasResultFilters
                    ? 'Cambia la búsqueda o limpia los filtros para ver más reactivos.'
                    : 'Crea tu primer reactivo manualmente o agrega un listado desde CSV o Excel.'"
            >
                @unless ($hasResultFilters)
                    <x-button :href="route('questions.create')" variant="primary" size="md">
                        <x-icon name="add" class="w-4 h-4" />
                        Crear reactivo
                    </x-button>
                @endunless
            </x-empty-state>
        @else
            {{-- Vista 4A: Tarjetas --}}
            <div x-show="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                @forelse ($questions as $question)
                    @php
                        $paraphrasesCount = (int) ($question->paraphrases_count ?? 0);
                        $paraphrasesLabel = $paraphrasesCount > 0 ? $paraphrasesCount . ' paráfrasis' : 'Sin paráfrasis';
                        $examLinksCount = (int) ($question->exam_questions_count ?? 0);
                        $childrenCount = (int) ($question->child_questions_count ?? 0);
                    @endphp
                    <article class="rounded-xl border border-slate-200 bg-white shadow-sm hover:border-acode-tech/60 hover:shadow-md transition-all flex flex-col justify-between">
                        <div class="p-5 space-y-3">
                            <div>
                                <h2 class="text-base font-semibold text-acode-primary leading-6">
                                    <a href="{{ route('questions.show', $question) }}" class="line-clamp-2 break-words rounded-sm underline decoration-acode-tech/50 underline-offset-2 hover:text-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                                        {{ $question->title }}
                                    </a>
                                </h2>
                                <p class="mt-1.5 text-sm text-slate-700 leading-5 line-clamp-3">
                                    {{ \Illuminate\Support\Str::limit($question->stem, 150) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5">
                                <x-badge :variant="$typeVariants[$question->question_type] ?? 'neutral'">
                                    {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                                </x-badge>
                                <x-badge variant="neutral" class="break-all">
                                    {{ $question->subject_tag ?? 'Sin materia' }}
                                </x-badge>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
                                <span class="font-semibold text-acode-primary tabular-nums">
                                    {{ \App\Support\AcademicNumber::forInput($question->default_points) }} pts
                                </span>
                                <span class="inline-flex items-center gap-1 tabular-nums">
                                    <x-icon name="clock" class="w-3.5 h-3.5" />
                                    {{ $question->default_time_seconds }} s
                                </span>
                                @if ($examLinksCount > 0)
                                    <span class="inline-flex items-center gap-1 text-acode-primary font-medium">
                                        <x-icon name="lock" class="w-3.5 h-3.5 text-acode-warning-dark" />
                                        En {{ $examLinksCount }} {{ $examLinksCount === 1 ? 'examen' : 'exámenes' }}
                                    </span>
                                @endif
                                @if ($childrenCount > 0)
                                    <span class="text-acode-tech font-medium">
                                        {{ $childrenCount }} {{ $childrenCount === 1 ? 'versión' : 'versiones' }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Acciones normativas --}}
                        <div class="px-4 py-3 bg-acode-surface/40 border-t border-slate-100 rounded-b-xl flex flex-wrap items-center justify-between gap-2">
                            <x-button
                                type="button"
                                variant="neutral"
                                size="sm"
                                title="Agregar paráfrasis anti-copia"
                                aria-label="{{ $paraphrasesLabel }}. Agregar paráfrasis a «{{ $question->title }}»"
                                :data-paraphrase="json_encode([
                                    'id' => $question->id,
                                    'title' => $question->title,
                                    'stem' => $question->stem,
                                    'actionUrl' => route('questions.paraphrase.store', $question),
                                ])"
                                @click="openParaphraseModal(JSON.parse($el.dataset.paraphrase), $event.currentTarget)"
                            >
                                <x-icon name="add" class="w-3.5 h-3.5 text-acode-tech" />
                                {{ $paraphrasesLabel }}
                            </x-button>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <x-button :href="route('questions.edit', $question)" variant="secondary" size="sm" aria-label="Editar «{{ $question->title }}»">
                                    Editar
                                </x-button>
                                <form method="POST" action="{{ route('questions.duplicate', $question) }}" class="inline">
                                    @csrf
                                    <x-button type="submit" variant="neutral" size="sm" :icon-only="true" title="Copiar reactivo" aria-label="Copiar reactivo «{{ $question->title }}»">
                                        <x-icon name="copy" class="w-4 h-4" />
                                    </x-button>
                                </form>

                                <form id="delete-card-question-{{ $question->id }}" method="POST" action="{{ route('questions.destroy', $question) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-button
                                        type="button"
                                        variant="danger"
                                        size="sm"
                                        :icon-only="true"
                                        title="Eliminar reactivo"
                                        aria-label="Eliminar reactivo «{{ $question->title }}»"
                                        @click="confirmDelete('delete-card-question-{{ $question->id }}', {{ \Illuminate\Support\Js::from($question->title) }}, {{ $examLinksCount }})"
                                    >
                                        <x-icon name="delete" class="w-4 h-4" />
                                    </x-button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full">
                        <x-empty-state
                            icon="document"
                            title="Sin reactivos disponibles"
                            message="Aún no hay reactivos para mostrar."
                        />
                    </div>
                @endforelse
            </div>

            {{-- Vista 4B: Tabla compacta (<x-table>) --}}
            <div x-show="viewMode === 'table'" x-cloak>
                <x-table scroll-label="Tabla compacta de reactivos" class="[&_tbody_td]:py-2.5">
                    <x-slot:header>
                        <x-table.th>Reactivo</x-table.th>
                        <x-table.th>Materia</x-table.th>
                        <x-table.th class="whitespace-nowrap">Tipo</x-table.th>
                        <x-table.th align="center">Paráfrasis</x-table.th>
                        <x-table.th align="center" class="whitespace-nowrap">Puntaje / Tiempo</x-table.th>
                        <x-table.th align="right">Acciones</x-table.th>
                    </x-slot:header>

                    @forelse ($questions as $question)
                        @php
                            $paraphrasesCount = (int) ($question->paraphrases_count ?? 0);
                            $paraphrasesLabel = $paraphrasesCount > 0 ? $paraphrasesCount . ' paráfrasis' : 'Sin paráfrasis';
                            $examLinksCount = (int) ($question->exam_questions_count ?? 0);
                        @endphp
                        <tr>
                            <x-table.td class="max-w-sm">
                                <a href="{{ route('questions.show', $question) }}" class="line-clamp-2 break-words rounded-sm text-sm font-semibold text-acode-primary underline decoration-acode-tech/50 underline-offset-2 hover:text-acode-tech focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech">
                                    {{ $question->title }}
                                </a>
                                <p class="text-xs text-slate-600 line-clamp-1 mt-0.5">
                                    {{ \Illuminate\Support\Str::limit($question->stem, 90) }}
                                </p>
                            </x-table.td>
                            <x-table.td class="min-w-[130px]">
                                <span class="text-sm text-slate-700">
                                    {{ $question->subject_tag ?? 'Sin materia' }}
                                </span>
                            </x-table.td>
                            <x-table.td class="whitespace-nowrap">
                                <x-badge :variant="$typeVariants[$question->question_type] ?? 'neutral'" class="whitespace-nowrap">
                                    {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                                </x-badge>
                            </x-table.td>
                            <x-table.td align="center">
                                <x-button
                                    type="button"
                                    variant="neutral"
                                    size="sm"
                                    title="Agregar paráfrasis anti-copia"
                                    aria-label="{{ $paraphrasesLabel }}. Agregar paráfrasis a «{{ $question->title }}»"
                                    class="whitespace-nowrap"
                                    :data-paraphrase="json_encode([
                                        'id' => $question->id,
                                        'title' => $question->title,
                                        'stem' => $question->stem,
                                        'actionUrl' => route('questions.paraphrase.store', $question),
                                    ])"
                                    @click="openParaphraseModal(JSON.parse($el.dataset.paraphrase), $event.currentTarget)"
                                >
                                    <x-icon name="add" class="w-3.5 h-3.5 text-acode-tech" />
                                    {{ $paraphrasesLabel }}
                                </x-button>
                            </x-table.td>
                            <x-table.td align="center" class="tabular-nums text-sm whitespace-nowrap">
                                <span class="font-semibold text-acode-primary">{{ \App\Support\AcademicNumber::forInput($question->default_points) }} pts</span>
                                <span class="mx-1 text-slate-600" aria-hidden="true">·</span>
                                <span class="text-slate-700">{{ $question->default_time_seconds }} s</span>
                            </x-table.td>
                            <x-table.td align="right" class="whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <x-button :href="route('questions.edit', $question)" variant="secondary" size="sm" aria-label="Editar «{{ $question->title }}»">Editar</x-button>
                                    <form method="POST" action="{{ route('questions.duplicate', $question) }}" class="inline">
                                        @csrf
                                        <x-button type="submit" variant="neutral" size="sm" :icon-only="true" title="Copiar reactivo" aria-label="Copiar reactivo «{{ $question->title }}»">
                                            <x-icon name="copy" class="w-4 h-4" />
                                        </x-button>
                                    </form>
                                    <form id="delete-table-question-{{ $question->id }}" method="POST" action="{{ route('questions.destroy', $question) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            :icon-only="true"
                                            title="Eliminar reactivo"
                                            aria-label="Eliminar reactivo «{{ $question->title }}»"
                                            @click="confirmDelete('delete-table-question-{{ $question->id }}', {{ \Illuminate\Support\Js::from($question->title) }}, {{ $examLinksCount }})"
                                        >
                                            <x-icon name="delete" class="w-4 h-4" />
                                        </x-button>
                                    </form>
                                </div>
                            </x-table.td>
                        </tr>
                    @empty
                        <tr>
                            <x-table.td colspan="6" align="center" class="py-8 text-slate-500">
                                No hay reactivos disponibles.
                            </x-table.td>
                        </tr>
                    @endforelse
                </x-table>
            </div>

            {{-- Paginación universal alineada a la derecha (MPW-V3 §8.4) --}}
            <div class="flex justify-end">
                {{ $questions->links() }}
            </div>
        @endif

        {{-- Nivel 5 · Modal 1: Paráfrasis Anti-Copia --}}
        <x-modal name="paraphrase-modal" title="Agregar paráfrasis anti-copia" max-width="lg">
            <div class="space-y-4">
                <div class="rounded-xl border border-acode-tech/30 bg-acode-surface p-3.5 text-sm text-slate-700 leading-5">
                    <p class="font-semibold text-acode-primary mb-1">¿Cómo funciona en el examen?</p>
                    <p class="text-xs text-slate-700 leading-4">
                        El sistema alterna equitativamente entre el enunciado original y las paráfrasis registradas entre distintos alumnos. No altera las opciones ni la respuesta correcta.
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                        Reactivo: <span class="text-acode-primary normal-case font-semibold" x-text="paraphraseForm.title"></span>
                    </p>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-sm text-slate-700 whitespace-pre-line leading-5">
                        <span class="block text-xs font-semibold uppercase text-slate-500 mb-1">Enunciado original (solo lectura)</span>
                        <span x-text="paraphraseForm.originalStem"></span>
                    </div>
                </div>

                <form method="POST" x-bind:action="paraphraseForm.actionUrl" class="space-y-3">
                    @csrf
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="stem" class="text-sm font-medium text-slate-800">
                                Nueva paráfrasis <span class="text-acode-danger font-bold" aria-hidden="true">*</span>
                            </label>
                            <span
                                class="text-xs font-mono"
                                :class="paraphraseForm.stem.length > 480 ? 'text-acode-danger font-bold' : 'text-slate-500'"
                                x-text="paraphraseForm.stem.length + ' / 500'"
                            ></span>
                        </div>
                        <x-form.textarea
                            name="stem"
                            rows="3"
                            minlength="3"
                            maxlength="500"
                            required
                            x-model="paraphraseForm.stem"
                            placeholder="Redacta una forma equivalente manteniendo intacta la respuesta correcta (3 a 500 caracteres)…"
                            class="placeholder:!text-slate-500"
                        />
                        <p
                            x-show="paraphraseMatchesOriginal"
                            x-cloak
                            class="mt-1 text-xs font-medium text-acode-danger"
                        >
                            La paráfrasis debe ser distinta al enunciado original.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <x-button type="button" variant="neutral" size="md" @click="$dispatch('close-modal', 'paraphrase-modal')">
                            Cancelar
                        </x-button>
                        <x-button
                            type="submit"
                            variant="primary"
                            size="md"
                            x-bind:disabled="paraphraseMatchesOriginal || paraphraseForm.stem.trim().length < 3"
                        >
                            <x-icon name="save" class="w-4 h-4" />
                            Guardar paráfrasis
                        </x-button>
                    </div>
                </form>
            </div>
        </x-modal>

        {{-- Nivel 5 · Modal 2: Carga Masiva (CSV / Excel .xlsx con Dry-Run Preview) --}}
        <x-modal
            name="bulk-import-modal"
            title="Importar CSV o Excel"
            max-width="2xl"
            :show="$errors->has('csv_file')"
        >
            <div class="space-y-5">
                {{-- Descarga de plantillas y alternativa rápida --}}
                <div class="rounded-xl border border-slate-200 bg-acode-surface/70 p-4 space-y-3">
                    <div>
                        <p class="text-xs font-semibold text-acode-primary uppercase tracking-wider">1. Descarga una plantilla oficial</p>
                        <p class="text-xs text-slate-600 mt-1">
                            Incluye ejemplos listos para opción múltiple, relación de columnas, preguntas abiertas y código.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-button :href="route('questions.template.csv')" variant="neutral" size="sm">
                            <x-icon name="downloadReport" class="w-4 h-4 text-acode-tech" />
                            Descargar plantilla CSV
                        </x-button>
                        <x-button :href="route('questions.template.xlsx')" variant="neutral" size="sm">
                            <x-icon name="downloadReport" class="w-4 h-4 text-acode-tech" />
                            Descargar plantilla Excel (.xlsx)
                        </x-button>
                        <x-button :href="route('questions.batch.create')" variant="neutral" size="sm">
                            <x-icon name="add" class="w-4 h-4" />
                            Captura rápida en lote
                        </x-button>
                    </div>
                </div>

                {{-- Formulario de carga y previsualización --}}
                <form method="POST" action="{{ route('questions.import') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <label for="bulk_csv_file" class="block text-sm font-medium text-slate-800 mb-1.5">
                            2. Selecciona tu archivo (.csv, .txt o .xlsx)
                        </label>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2.5">
                            <input
                                id="bulk_csv_file"
                                type="file"
                                name="csv_file"
                                accept=".csv,.txt,.xlsx"
                                required
                                x-ref="bulkImportFileInput"
                                @change="onImportFileSelected($event)"
                                class="block w-full min-w-0 h-10 text-sm text-slate-700 file:mr-3 file:h-full file:px-3.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-acode-primary file:text-white hover:file:bg-acode-tech border border-slate-300 rounded-lg bg-white p-1 focus:outline-none focus:ring-2 focus:ring-acode-tech"
                            />
                            <x-button
                                type="button"
                                variant="secondary"
                                size="md"
                                @click="runImportPreview()"
                                x-bind:disabled="importPreview.loading || !importPreview.hasFile"
                                class="shrink-0"
                                loading-when="importPreview.loading"
                            >
                                <x-icon name="search" class="w-4 h-4" x-show="!importPreview.loading" />
                                <span x-text="importPreview.loading ? 'Analizando…' : 'Ver vista previa'"></span>
                            </x-button>
                        </div>

                        @error('csv_file')
                            <p class="mt-1.5 text-xs font-medium text-acode-danger">{{ $message }}</p>
                        @enderror
                        <p role="alert" x-show="importPreview.errorMessage" x-cloak x-text="importPreview.errorMessage" class="mt-1.5 text-xs font-medium text-acode-danger-dark"></p>
                    </div>

                    {{-- Resultados de previsualización Dry-Run --}}
                    <div x-show="importPreview.data !== null" x-cloak class="space-y-3 border-t border-slate-100 pt-3">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <span class="block text-xs text-slate-500">Total filas</span>
                                <span class="text-base font-bold text-acode-primary" x-text="importPreview.data?.total_rows ?? 0"></span>
                            </div>
                            <div class="rounded-lg border border-acode-accent/40 bg-acode-accent/15 px-3 py-2">
                                <span class="block text-xs text-acode-primary">Válidas</span>
                                <span class="text-base font-bold text-acode-primary" x-text="importPreview.data?.valid_count ?? 0"></span>
                            </div>
                            <div class="rounded-lg border border-acode-danger/30 bg-acode-danger/10 px-3 py-2">
                                <span class="block text-xs text-acode-danger-dark">Con errores</span>
                                <span class="text-base font-bold text-acode-danger-dark" x-text="importPreview.data?.error_count ?? 0"></span>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <span class="block text-xs text-slate-500">Cuota disponible</span>
                                <span
                                    class="text-base font-bold text-acode-primary"
                                    x-text="importPreview.data?.quota?.is_unlimited ? 'Ilimitado' : (importPreview.data?.quota?.remaining ?? 0)"
                                ></span>
                            </div>
                        </div>

                        {{-- Errores generales de lote / cuota --}}
                        <div
                            x-show="(importPreview.data?.errors || []).length > 0"
                            class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-3 text-xs text-acode-danger-dark space-y-1"
                        >
                            <p class="font-bold">Observaciones bloqueantes:</p>
                            <ul class="list-disc pl-4 space-y-0.5">
                                <template x-for="(gErr, gIdx) in (importPreview.data?.errors || [])" :key="gIdx">
                                    <li x-text="gErr"></li>
                                </template>
                            </ul>
                        </div>

                        {{-- Advertencias de typos de materia --}}
                        <div
                            x-show="(importPreview.data?.subject_warnings || []).length > 0"
                            class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 p-3 text-xs text-acode-primary space-y-1"
                        >
                            <p class="font-semibold">Posibles variaciones de escritura en materias detectadas:</p>
                            <ul class="list-disc pl-4 space-y-0.5">
                                <template x-for="(warn, wIdx) in (importPreview.data?.subject_warnings || [])" :key="wIdx">
                                    <li>
                                        «<strong x-text="warn.input"></strong>» es similar a:
                                        <span class="font-semibold" x-text="(warn.suggestions || []).join(', ')"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        {{-- Tabla fila por fila (MPW-V3 §8.4) --}}
                        <x-table scroll-label="Vista previa de las filas del archivo" class="[&>div]:max-h-60 [&>div]:overflow-y-auto">
                            <x-slot:header>
                                <x-table.th class="px-3 py-2.5">Fila</x-table.th>
                                <x-table.th class="px-3 py-2.5">Estado</x-table.th>
                                <x-table.th class="px-3 py-2.5">Tipo</x-table.th>
                                <x-table.th class="px-3 py-2.5">Título</x-table.th>
                                <x-table.th class="px-3 py-2.5">Materia</x-table.th>
                                <x-table.th class="px-3 py-2.5">Detalle</x-table.th>
                            </x-slot:header>

                            <template x-for="row in (importPreview.data?.rows || [])" :key="row.row">
                                <tr>
                                    <x-table.td class="px-3 py-2 font-mono text-xs text-slate-600" x-text="row.row"></x-table.td>
                                    <x-table.td class="px-3 py-2 text-xs">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                                            :class="row.status === 'valid' ? 'bg-acode-accent/15 text-acode-primary' : 'bg-acode-danger/10 text-acode-danger-dark'"
                                            x-text="row.status === 'valid' ? 'Válido' : 'Error'"
                                        ></span>
                                    </x-table.td>
                                    <x-table.td class="px-3 py-2 text-xs text-slate-700" x-text="row.question_type || '—'"></x-table.td>
                                    <x-table.td class="px-3 py-2 text-xs font-medium text-acode-primary max-w-[10rem] truncate" x-text="row.title || '—'"></x-table.td>
                                    <x-table.td class="px-3 py-2 text-xs text-slate-700" x-text="row.subject_tag || 'Sin materia'"></x-table.td>
                                    <x-table.td class="px-3 py-2 text-xs">
                                        <template x-if="row.status === 'valid'">
                                            <span class="text-slate-700 font-medium">Lista para guardar</span>
                                        </template>
                                        <template x-if="row.status !== 'valid'">
                                            <span class="text-acode-danger-dark" x-text="(row.errors || []).join(' · ')"></span>
                                        </template>
                                    </x-table.td>
                                </tr>
                            </template>
                        </x-table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-100">
                        <span class="text-xs text-slate-500" x-show="jsReady && !importPreview.canCommit">
                            Revisa un archivo válido para habilitar el guardado.
                        </span>
                        <div class="flex items-center gap-2 ml-auto">
                            <x-button type="button" variant="neutral" size="md" @click="$dispatch('close-modal', 'bulk-import-modal')">
                                Cancelar
                            </x-button>
                            <x-button
                                type="submit"
                                variant="primary"
                                size="md"
                                x-bind:disabled="jsReady && !importPreview.canCommit"
                            >
                                <x-icon name="save" class="w-4 h-4" />
                                Guardar reactivos
                            </x-button>
                        </div>
                    </div>
                </form>
            </div>
        </x-modal>

        {{-- Nivel 5 · Modal 3: Gestor y Unificador de Materias --}}
        <x-modal
            name="subjects-modal"
            title="Gestionar y unificar materias"
            max-width="xl"
            :show="$errors->has('source_subject_ids') || $errors->has('target_name')"
        >
            <div class="space-y-4">
                <p class="text-sm text-slate-700 leading-5">
                    Selecciona una o varias materias registradas con errores de escritura o variantes (por ejemplo «Bses de datos» y «Bases de Datos») y defínelas bajo un único nombre canónico.
                </p>

                @if ($subjectsStats->isEmpty())
                    <x-empty-state
                        icon="document"
                        title="Sin materias registradas"
                        message="Asigna una materia al crear o editar tus reactivos para agruparlas aquí."
                    />
                @else
                    <form method="POST" action="{{ route('questions.subjects.merge') }}" class="space-y-4">
                        @csrf

                        @error('source_subject_ids')
                            <p class="text-xs font-medium text-acode-danger">{{ $message }}</p>
                        @enderror

                        <div class="max-h-64 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
                            @forelse ($subjectsStats as $subjectItem)
                                @php
                                    $similar = $subjectItem->similar_to;
                                    $preferredName = $similar
                                        ? (((int) ($similar['questions_count'] ?? 0) >= (int) $subjectItem->questions_count) ? $similar['name'] : $subjectItem->name)
                                        : $subjectItem->name;
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-3.5 py-2.5 hover:bg-acode-surface/60">
                                    <label class="flex flex-1 items-start gap-2.5 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="source_subject_ids[]"
                                            value="{{ $subjectItem->id }}"
                                            x-model="mergeForm.selectedIds"
                                            class="mt-0.5 h-4 w-4 rounded-md border-slate-300 text-acode-tech focus:ring-acode-tech"
                                        />
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-semibold text-acode-primary">{{ $subjectItem->name }}</span>
                                                <x-badge variant="neutral">{{ (int) $subjectItem->questions_count }} reactivos</x-badge>
                                            </div>
                                            @if ($similar)
                                                <p class="mt-1 text-xs text-acode-primary font-medium">
                                                    Similar a «{{ $similar['name'] }}» ({{ (int) ($similar['questions_count'] ?? 0) }} reactivos)
                                                </p>
                                            @endif
                                        </div>
                                    </label>

                                    @if ($similar)
                                        <x-button
                                            type="button"
                                            variant="warning"
                                            size="sm"
                                            @click.prevent="quickSelectMerge([{{ $subjectItem->id }}, {{ (int) $similar['id'] }}], {{ \Illuminate\Support\Js::from($preferredName) }})"
                                            class="self-start sm:self-center shrink-0"
                                        >
                                            Seleccionar más usada
                                        </x-button>
                                    @endif
                                </div>
                            @empty
                                <p class="p-4 text-xs text-slate-500 text-center">No hay materias disponibles.</p>
                            @endforelse
                        </div>

                        <x-form.group
                            name="target_name"
                            label="Nombre canónico final"
                            :required="true"
                            help-text="Todos los reactivos de las materias seleccionadas pasarán a usar este nombre."
                        >
                            <x-form.input
                                name="target_name"
                                x-model="mergeForm.targetName"
                                minlength="2"
                                maxlength="100"
                                required
                                placeholder="Ej. Bases de Datos"
                            />
                        </x-form.group>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                            <x-button type="button" variant="neutral" size="md" @click="$dispatch('close-modal', 'subjects-modal')">
                                Cancelar
                            </x-button>
                            <x-button
                                type="submit"
                                variant="primary"
                                size="md"
                                x-bind:disabled="mergeForm.selectedIds.length === 0 || mergeForm.targetName.trim().length < 2"
                            >
                                <x-icon name="save" class="w-4 h-4" />
                                Guardar cambios
                            </x-button>
                        </div>
                    </form>
                @endif
            </div>
        </x-modal>
    </div>

    @push('scripts')
        <script>
            window.acodeQuestionBank = function (config) {
                let savedView = 'cards';
                try {
                    const stored = localStorage.getItem('acode_questions_view_mode');
                    if (stored === 'cards' || stored === 'table') {
                        savedView = stored;
                    }
                } catch (e) {
                    // Almacenamiento local no disponible
                }

                return {
                    jsReady: false,
                    viewMode: savedView,
                    previewUrl: config.previewUrl || '',
                    paraphraseForm: {
                        id: null,
                        title: '',
                        originalStem: '',
                        stem: '',
                        actionUrl: '',
                    },
                    importPreview: {
                        hasFile: false,
                        loading: false,
                        errorMessage: '',
                        canCommit: false,
                        data: null,
                    },
                    mergeForm: {
                        selectedIds: @js(array_map('strval', (array) old('source_subject_ids', []))),
                        targetName: @js(old('target_name', '')),
                    },

                    init() {
                        this.jsReady = true;
                    },

                    setViewMode(mode) {
                        this.viewMode = mode;
                        try {
                            localStorage.setItem('acode_questions_view_mode', mode);
                        } catch (e) {
                            // Ignora bloqueo de localStorage
                        }
                    },

                    normalizeText(value) {
                        return String(value ?? '')
                            .trim()
                            .replace(/\s+/g, ' ')
                            .toLowerCase();
                    },

                    get paraphraseMatchesOriginal() {
                        const a = this.normalizeText(this.paraphraseForm.stem);
                        const b = this.normalizeText(this.paraphraseForm.originalStem);
                        return a.length > 0 && a === b;
                    },

                    openParaphraseModal(payload, trigger) {
                        this.paraphraseForm = {
                            id: payload.id,
                            title: payload.title || '',
                            originalStem: payload.stem || '',
                            stem: '',
                            actionUrl: payload.actionUrl || '',
                        };
                        trigger.dispatchEvent(new CustomEvent('open-modal', { bubbles: true, detail: 'paraphrase-modal' }));
                    },

                    onImportFileSelected(event) {
                        const file = event?.target?.files?.[0] || null;
                        this.importPreview = {
                            hasFile: Boolean(file),
                            loading: false,
                            errorMessage: '',
                            canCommit: false,
                            data: null,
                        };
                    },

                    async runImportPreview() {
                        const fileInput = this.$refs.bulkImportFileInput;
                        const file = fileInput?.files?.[0];
                        if (!file || !this.previewUrl) return;

                        this.importPreview.loading = true;
                        this.importPreview.errorMessage = '';

                        try {
                            const formData = new FormData();
                            formData.append('csv_file', file);

                            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                            const response = await fetch(this.previewUrl, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: formData,
                            });

                            const payload = await response.json();
                            if (!response.ok) {
                                this.importPreview.errorMessage = payload?.errors?.csv_file?.[0] || payload?.message || 'No se pudo analizar el archivo.';
                                this.importPreview.canCommit = false;
                                this.importPreview.data = null;
                                return;
                            }

                            this.importPreview.data = payload;
                            this.importPreview.canCommit = Boolean(payload?.can_commit);
                        } catch (e) {
                            this.importPreview.errorMessage = 'Error de conexión al previsualizar el archivo.';
                            this.importPreview.canCommit = false;
                        } finally {
                            this.importPreview.loading = false;
                        }
                    },

                    quickSelectMerge(ids, preferredName) {
                        const next = new Set(this.mergeForm.selectedIds.map(String));
                        ids.forEach((id) => next.add(String(id)));
                        this.mergeForm.selectedIds = Array.from(next);
                        this.mergeForm.targetName = preferredName || '';
                    },

                    confirmDelete(formId, title, linkedCount) {
                        const warningSuffix = linkedCount > 0
                            ? ' Está en ' + linkedCount + ' examen(es); esos exámenes lo conservarán.'
                            : '';
                        AcodeAlert.confirm({
                            title: '¿Eliminar reactivo?',
                            text: 'El reactivo "' + title + '" será retirado de tu banco.' + warningSuffix,
                            confirmButtonText: 'Eliminar',
                            cancelButtonText: 'Cancelar',
                            isDanger: true,
                            onConfirm: () => {
                                const form = document.getElementById(formId);
                                if (form) form.submit();
                            },
                        });
                    },
                };
            };
        </script>
    @endpush
@endsection
