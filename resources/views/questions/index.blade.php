{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: questions/index.blade.php (MPW-V2 §7, §8, Tabla 20, Tabla 28, RF-BAN-001..012) --}}
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
        $hasActiveFilters = filled($filters['search'] ?? null)
            || filled($filters['question_type'] ?? null)
            || filled($filters['subject_tag'] ?? null)
            || filled($filters['subject_id'] ?? null)
            || filled($filters['paraphrase_status'] ?? null)
            || (($filters['sort_by'] ?? 'latest') !== 'latest')
            || ((int) ($perPage ?? 10) !== 10);

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
        class="mx-auto max-w-7xl space-y-6"
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
                        <x-icon name="edit" class="w-3.5 h-3.5 mr-1.5" />
                        Editar copia
                    </x-button>
                    <x-button :href="route('questions.show', session('duplicated_question_id'))" variant="neutral" size="sm">
                        <x-icon name="view" class="w-3.5 h-3.5 mr-1.5" />
                        Abrir
                    </x-button>
                </div>
            </div>
        @endif

        {{-- Nivel 1: Hero Institucional y Acciones Clave --}}
        <section class="relative overflow-hidden rounded-2xl bg-acode-primary text-white shadow-md">
            <div class="relative z-10 px-6 py-7 sm:px-8 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-teal-200 border border-white/15">
                        <x-icon name="document" class="w-3.5 h-3.5" />
                        <span>Catálogo Docente · Plan {{ $quotaSummary['package_name'] ?? 'Activo' }}</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                        Banco de preguntas
                    </h1>
                    <p class="text-sm text-slate-200 leading-relaxed">
                        Organiza tus reactivos por materia y tipo, refuerza el blindaje anti-copia con paráfrasis equitativas e importa cuestionarios masivos desde CSV o Excel.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <x-button :href="route('questions.create')" variant="primary" size="md" class="!bg-acode-accent hover:!bg-teal-700 !text-white !shadow-sm">
                        <x-icon name="add" class="w-4 h-4 mr-1.5" />
                        Crear reactivo
                    </x-button>

                    <x-button
                        type="button"
                        variant="secondary"
                        size="md"
                        @click="$dispatch('open-modal', 'bulk-import-modal')"
                    >
                        <x-icon name="upload" class="w-4 h-4 mr-1.5" />
                        Importar en lote
                    </x-button>

                    <x-button
                        :href="route('questions.batch.create')"
                        variant="neutral"
                        size="md"
                    >
                        <x-icon name="add" class="w-4 h-4 mr-1.5 text-acode-tech" />
                        Captura rápida en lote
                    </x-button>

                    <x-button
                        type="button"
                        variant="neutral"
                        size="md"
                        @click="$dispatch('open-modal', 'subjects-modal')"
                    >
                        <x-icon name="filter" class="w-4 h-4 mr-1.5 text-slate-500" />
                        <span>Gestionar materias</span>
                        @if ($duplicateSubjectGroups > 0)
                            <span class="ml-1.5 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-[11px] font-bold bg-acode-warning text-acode-primary">
                                {{ $duplicateSubjectGroups }}
                            </span>
                        @endif
                    </x-button>
                </div>
            </div>
        </section>

        {{-- Nivel 2: 4 Tarjetas KPI --}}
        <section class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores del banco de preguntas">
            {{-- KPI 1: Reactivos y Cuota SaaS --}}
            <x-card class="h-full">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reactivos activos</p>
                        <p class="mt-1 text-3xl font-extrabold text-acode-primary tabular-nums">
                            {{ $summaryMetrics['total_active'] ?? 0 }}
                        </p>
                    </div>
                    <span class="w-10 h-10 rounded-xl bg-acode-tech/10 text-acode-tech flex items-center justify-center shrink-0">
                        <x-icon name="document" class="w-5 h-5" />
                    </span>
                </div>

                <div class="mt-4 space-y-1.5">
                    <div class="flex items-center justify-between text-xs font-medium">
                        <span class="text-slate-500">Cuota de plan</span>
                        @if ($quotaUnlimited)
                            <span class="font-bold text-acode-accent">{{ $quotaUsed }} / Ilimitado</span>
                        @else
                            <span class="font-bold {{ $quotaPercent >= 90 ? 'text-acode-danger' : 'text-acode-primary' }}">
                                {{ $quotaUsed }} / {{ number_format((int) $quotaLimit) }}
                            </span>
                        @endif
                    </div>
                    <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-300 {{ $quotaUnlimited ? 'bg-acode-accent' : ($quotaPercent >= 95 ? 'bg-acode-danger' : 'bg-acode-tech') }}"
                            :style="{ width: '{{ $quotaPercent }}%' }"
                        ></div>
                    </div>
                </div>
            </x-card>

            {{-- KPI 2: Blindaje Anti-Copia --}}
            <x-card class="h-full">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Blindaje anti-copia</p>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-3xl font-extrabold text-acode-accent tabular-nums">{{ $withParaphrases }}</span>
                            <span class="text-xs text-slate-500">protegidos</span>
                        </div>
                    </div>
                    <span class="w-10 h-10 rounded-xl bg-acode-accent/15 text-acode-accent flex items-center justify-center shrink-0">
                        <x-icon name="success" class="w-5 h-5" />
                    </span>
                </div>

                <div class="mt-4 flex items-center justify-between gap-2 text-xs">
                    <span class="text-slate-600">
                        <strong class="font-bold {{ $withoutParaphrases > 0 ? 'text-amber-700' : 'text-slate-700' }}">{{ $withoutParaphrases }}</strong> sin paráfrasis
                    </span>
                    @if ($withoutParaphrases > 0)
                        <a
                            href="{{ route('questions.index', ['paraphrase_status' => 'without']) }}"
                            class="font-semibold text-acode-tech hover:underline"
                        >
                            Reforzar vulnerabilidad →
                        </a>
                    @else
                        <x-badge variant="success">100% cubierto</x-badge>
                    @endif
                </div>
            </x-card>

            {{-- KPI 3: Materias registradas --}}
            <x-card class="h-full">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Materias registradas</p>
                        <p class="mt-1 text-3xl font-extrabold text-acode-primary tabular-nums">
                            {{ $summaryMetrics['subjects_count'] ?? 0 }}
                        </p>
                    </div>
                    <span class="w-10 h-10 rounded-xl bg-acode-warning/20 text-amber-700 flex items-center justify-center shrink-0">
                        <x-icon name="reportBar" class="w-5 h-5" />
                    </span>
                </div>

                <div class="mt-4">
                    @if ($duplicateSubjectGroups > 0)
                        <button
                            type="button"
                            @click="$dispatch('open-modal', 'subjects-modal')"
                            class="w-full text-left rounded-lg border border-acode-warning/50 bg-acode-warning/15 px-2.5 py-1.5 text-xs font-semibold text-amber-900 hover:bg-acode-warning/25 transition flex items-center justify-between gap-2"
                        >
                            <span>Posibles duplicados por escritura ({{ $duplicateSubjectGroups }})</span>
                            <span class="underline shrink-0">Unificar</span>
                        </button>
                    @else
                        <p class="text-xs text-slate-500">
                            Nombres de materias consistentes y sin posibles duplicados.
                        </p>
                    @endif
                </div>
            </x-card>

            {{-- KPI 4: Distribución por tipo --}}
            <x-card class="h-full">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Distribución por tipo</p>
                    <x-icon name="code" class="w-4 h-4 text-acode-tech" />
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-lg bg-slate-50 px-2.5 py-1.5 flex items-center justify-between border border-slate-100">
                        <span class="text-slate-600 truncate">Opción múltiple</span>
                        <span class="font-bold text-acode-primary tabular-nums">{{ $byType['multiple_choice'] ?? 0 }}</span>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-2.5 py-1.5 flex items-center justify-between border border-slate-100">
                        <span class="text-slate-600 truncate">Relación</span>
                        <span class="font-bold text-acode-accent tabular-nums">{{ $byType['matching'] ?? 0 }}</span>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-2.5 py-1.5 flex items-center justify-between border border-slate-100">
                        <span class="text-slate-600 truncate">Abierta</span>
                        <span class="font-bold text-amber-700 tabular-nums">{{ $byType['open'] ?? 0 }}</span>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-2.5 py-1.5 flex items-center justify-between border border-slate-100">
                        <span class="text-slate-600 truncate">Código</span>
                        <span class="font-bold text-acode-tech tabular-nums">{{ $byType['code'] ?? 0 }}</span>
                    </div>
                </div>
            </x-card>
        </section>

        {{-- Nivel 3: Barra de Búsqueda, Filtros SQL y Alternador Tarjetas / Tabla --}}
        <section class="space-y-3" aria-label="Búsqueda y filtros del banco">
            <x-search-filter
                :action="route('questions.index')"
                search-name="search"
                :search-value="$filters['search'] ?? ''"
                placeholder="Buscar por título interno o enunciado…"
                :clear-url="route('questions.index')"
                :clear-keys="['search', 'question_type', 'subject_tag', 'subject_id', 'paraphrase_status', 'sort_by', 'per_page']"
                :has-active-filters="$hasActiveFilters"
            >
                <x-slot:filters>
                    @if (!empty($filters['subject_id']))
                        <input type="hidden" name="subject_id" value="{{ $filters['subject_id'] }}" x-ref="subjectIdHidden">
                    @endif

                    <select
                        name="subject_tag"
                        aria-label="Filtrar por materia"
                        @change="if ($refs.subjectIdHidden) $refs.subjectIdHidden.disabled = true"
                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech"
                    >
                        <option value="">Todas las materias</option>
                        @foreach ($subjects as $subjectName)
                            <option value="{{ $subjectName }}" @selected(($filters['subject_tag'] ?? '') === $subjectName)>
                                {{ $subjectName }}
                            </option>
                        @endforeach
                    </select>

                    <select
                        name="question_type"
                        aria-label="Filtrar por tipo de reactivo"
                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech"
                    >
                        <option value="">Todos los tipos</option>
                        @foreach ($typeLabels as $typeValue => $typeText)
                            <option value="{{ $typeValue }}" @selected(($filters['question_type'] ?? '') === $typeValue)>
                                {{ $typeText }}
                            </option>
                        @endforeach
                    </select>

                    <select
                        name="paraphrase_status"
                        aria-label="Filtrar por blindaje anti-copia"
                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech"
                    >
                        <option value="">Todo blindaje</option>
                        <option value="with" @selected(($filters['paraphrase_status'] ?? '') === 'with')>Con paráfrasis</option>
                        <option value="without" @selected(($filters['paraphrase_status'] ?? '') === 'without')>Sin paráfrasis</option>
                    </select>

                    <select
                        name="sort_by"
                        aria-label="Ordenar reactivos"
                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech"
                    >
                        <option value="latest" @selected(($filters['sort_by'] ?? 'latest') === 'latest')>Más recientes</option>
                        <option value="oldest" @selected(($filters['sort_by'] ?? '') === 'oldest')>Más antiguos</option>
                        <option value="title" @selected(($filters['sort_by'] ?? '') === 'title')>Título (A-Z)</option>
                        <option value="subject" @selected(($filters['sort_by'] ?? '') === 'subject')>Materia (A-Z)</option>
                        <option value="paraphrases" @selected(($filters['sort_by'] ?? '') === 'paraphrases')>Más paráfrasis</option>
                    </select>

                    <select
                        name="per_page"
                        aria-label="Resultados por página"
                        @change="$refs.searchForm.submit()"
                        class="h-8 rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-acode-tech"
                    >
                        @foreach ([10, 20, 50] as $pageSizeOption)
                            <option value="{{ $pageSizeOption }}" @selected((int) ($perPage ?? 10) === $pageSizeOption)>
                                {{ $pageSizeOption }} / pág.
                            </option>
                        @endforeach
                    </select>
                </x-slot:filters>
            </x-search-filter>

            {{-- Barra de resumen de resultados y alternador de vista --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-1">
                <p class="text-xs text-slate-600">
                    Mostrando <strong class="text-acode-primary">{{ $questions->count() }}</strong> de
                    <strong class="text-acode-primary">{{ $questions->total() }}</strong> reactivos
                    @if (!empty($filters['subject_id']))
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full bg-acode-tech/10 text-acode-tech font-semibold">
                            Materia seleccionada #{{ $filters['subject_id'] }}
                        </span>
                    @endif
                </p>

                <div class="inline-flex items-center rounded-xl border border-slate-200 bg-white p-1 shadow-xs self-start sm:self-auto" role="group" aria-label="Cambiar vista del catálogo">
                    <button
                        type="button"
                        @click="setViewMode('cards')"
                        class="h-8 px-3 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
                        :class="viewMode === 'cards' ? 'bg-acode-primary text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50'"
                    >
                        <x-icon name="document" class="w-3.5 h-3.5" />
                        Tarjetas
                    </button>
                    <button
                        type="button"
                        @click="setViewMode('table')"
                        class="h-8 px-3 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
                        :class="viewMode === 'table' ? 'bg-acode-primary text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50'"
                    >
                        <x-icon name="reportBar" class="w-3.5 h-3.5" />
                        Tabla compacta
                    </button>
                </div>
            </div>
        </section>

        {{-- Nivel 4: Catálogo (Tarjetas + Tabla Compacta) y Paginación Universal --}}
        @if ($questions->isEmpty())
            <x-card>
                <x-empty-state
                    icon="document"
                    :title="$hasActiveFilters ? 'Sin reactivos con esos filtros' : 'Tu banco de preguntas está vacío'"
                    :message="$hasActiveFilters
                        ? 'No encontramos reactivos que coincidan con la búsqueda o filtros aplicados.'
                        : 'Crea tu primer reactivo manualmente o importa un listado desde CSV o Excel.'"
                >
                    <div class="flex flex-wrap items-center justify-center gap-2.5">
                        @if ($hasActiveFilters)
                            <x-button :href="route('questions.index')" variant="neutral" size="md">
                                <x-icon name="filter" class="w-4 h-4 mr-1.5" />
                                Limpiar filtros
                            </x-button>
                        @endif
                        <x-button :href="route('questions.create')" variant="primary" size="md">
                            <x-icon name="add" class="w-4 h-4 mr-1.5" />
                            Crear reactivo
                        </x-button>
                    </div>
                </x-empty-state>
            </x-card>
        @else
            {{-- Vista 4A: Tarjetas --}}
            <div x-show="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                @forelse ($questions as $question)
                    @php
                        $paraphrasesCount = (int) ($question->paraphrases_count ?? 0);
                        $examLinksCount = (int) ($question->exam_questions_count ?? 0);
                        $childrenCount = (int) ($question->child_questions_count ?? 0);
                    @endphp
                    <article class="rounded-xl border border-slate-200 bg-white shadow-sm hover:border-acode-tech/50 hover:shadow-md transition-all flex flex-col justify-between">
                        <div class="p-5 space-y-3">
                            {{-- Badges superiores --}}
                            <div class="flex flex-wrap items-center gap-1.5">
                                <x-badge :variant="$typeVariants[$question->question_type] ?? 'neutral'">
                                    {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                                </x-badge>

                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200 max-w-[10rem] truncate">
                                    {{ $question->subject_tag ?? 'Sin materia' }}
                                </span>

                                <button
                                    type="button"
                                    @click="openParaphraseModal({
                                        id: {{ $question->id }},
                                        title: @js($question->title),
                                        stem: @js($question->stem),
                                        actionUrl: @js(route('questions.paraphrase.store', $question)),
                                        count: {{ $paraphrasesCount }},
                                    })"
                                    title="Blindaje anti-copia: alterna equitativamente el enunciado entre alumnos"
                                    class="ml-auto inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border transition cursor-pointer {{ $paraphrasesCount > 0 ? 'bg-acode-accent/10 text-teal-800 border-acode-accent/30 hover:bg-acode-accent/20' : 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100' }}"
                                >
                                    <x-icon name="{{ $paraphrasesCount > 0 ? 'check' : 'warning' }}" class="w-3 h-3" />
                                    <span>{{ $paraphrasesCount }} paráfrasis</span>
                                </button>
                            </div>

                            {{-- Título clickeable y extracto --}}
                            <div>
                                <h2 class="text-base font-bold text-acode-primary leading-snug line-clamp-2">
                                    <a href="{{ route('questions.show', $question) }}" class="hover:text-acode-tech transition">
                                        {{ $question->title }}
                                    </a>
                                </h2>
                                <p class="mt-1.5 text-xs text-slate-600 leading-relaxed line-clamp-3">
                                    {{ \Illuminate\Support\Str::limit($question->stem, 150) }}
                                </p>
                            </div>

                            {{-- Métricas rápidas --}}
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-2 text-[11px] text-slate-500">
                                <span class="font-mono font-semibold text-acode-primary">
                                    {{ number_format((float) $question->default_points, 1) }} pts
                                </span>
                                <span class="inline-flex items-center gap-1 font-mono">
                                    <x-icon name="clock" class="w-3.5 h-3.5 text-slate-400" />
                                    {{ $question->default_time_seconds }}s
                                </span>
                                @if ($examLinksCount > 0)
                                    <span class="inline-flex items-center gap-1 text-amber-700 font-medium">
                                        <x-icon name="lock" class="w-3 h-3" />
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
                        <div class="px-4 py-3 bg-slate-50/80 border-t border-slate-100 rounded-b-xl flex flex-wrap items-center justify-between gap-1.5">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <x-button :href="route('questions.show', $question)" variant="neutral" size="sm">
                                    <x-icon name="view" class="w-3.5 h-3.5 mr-1" />
                                    Abrir
                                </x-button>

                                <x-button :href="route('questions.edit', $question)" variant="secondary" size="sm">
                                    <x-icon name="edit" class="w-3.5 h-3.5 mr-1" />
                                    Editar
                                </x-button>

                                <x-button
                                    type="button"
                                    variant="neutral"
                                    size="sm"
                                    @click="openParaphraseModal({
                                        id: {{ $question->id }},
                                        title: @js($question->title),
                                        stem: @js($question->stem),
                                        actionUrl: @js(route('questions.paraphrase.store', $question)),
                                        count: {{ $paraphrasesCount }},
                                    })"
                                >
                                    <x-icon name="add" class="w-3.5 h-3.5 mr-1" />
                                    Paráfrasis
                                </x-button>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <form method="POST" action="{{ route('questions.duplicate', $question) }}" class="inline">
                                    @csrf
                                    <x-button type="submit" variant="neutral" size="sm">
                                        <x-icon name="copy" class="w-3.5 h-3.5 mr-1" />
                                        Copiar
                                    </x-button>
                                </form>

                                <form id="delete-card-question-{{ $question->id }}" method="POST" action="{{ route('questions.destroy', $question) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-button
                                        type="button"
                                        variant="danger"
                                        size="sm"
                                        @click="confirmDelete('delete-card-question-{{ $question->id }}', @js($question->title), {{ $examLinksCount }})"
                                    >
                                        <x-icon name="delete" class="w-3.5 h-3.5 mr-1" />
                                        Eliminar
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
                <x-table>
                    <x-slot:header>
                        <tr>
                            <x-table.th>Reactivo</x-table.th>
                            <x-table.th>Materia</x-table.th>
                            <x-table.th>Tipo</x-table.th>
                            <x-table.th align="center">Blindaje</x-table.th>
                            <x-table.th align="center">Valor / Tiempo</x-table.th>
                            <x-table.th align="right">Acciones</x-table.th>
                        </tr>
                    </x-slot:header>

                    @forelse ($questions as $question)
                        @php
                            $paraphrasesCount = (int) ($question->paraphrases_count ?? 0);
                            $examLinksCount = (int) ($question->exam_questions_count ?? 0);
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <x-table.td class="max-w-sm">
                                <a href="{{ route('questions.show', $question) }}" class="font-semibold text-acode-primary hover:text-acode-tech transition line-clamp-1">
                                    {{ $question->title }}
                                </a>
                                <p class="text-xs text-slate-500 line-clamp-1 mt-0.5">
                                    {{ \Illuminate\Support\Str::limit($question->stem, 90) }}
                                </p>
                            </x-table.td>
                            <x-table.td>
                                <span class="text-xs font-medium text-slate-700">
                                    {{ $question->subject_tag ?? 'Sin materia' }}
                                </span>
                            </x-table.td>
                            <x-table.td>
                                <x-badge :variant="$typeVariants[$question->question_type] ?? 'neutral'">
                                    {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                                </x-badge>
                            </x-table.td>
                            <x-table.td align="center">
                                <button
                                    type="button"
                                    @click="openParaphraseModal({
                                        id: {{ $question->id }},
                                        title: @js($question->title),
                                        stem: @js($question->stem),
                                        actionUrl: @js(route('questions.paraphrase.store', $question)),
                                        count: {{ $paraphrasesCount }},
                                    })"
                                    title="Blindaje anti-copia: rota el enunciado equitativamente entre alumnos"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border transition {{ $paraphrasesCount > 0 ? 'bg-acode-accent/10 text-teal-800 border-acode-accent/30 hover:bg-acode-accent/20' : 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100' }}"
                                >
                                    <span>{{ $paraphrasesCount }}</span>
                                </button>
                            </x-table.td>
                            <x-table.td align="center" class="font-mono text-xs whitespace-nowrap">
                                <span class="font-semibold text-acode-primary">{{ number_format((float) $question->default_points, 1) }} pts</span>
                                <span class="text-slate-400">·</span>
                                <span class="text-slate-600">{{ $question->default_time_seconds }}s</span>
                            </x-table.td>
                            <x-table.td align="right" class="whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <x-button :href="route('questions.show', $question)" variant="neutral" size="sm">Abrir</x-button>
                                    <x-button :href="route('questions.edit', $question)" variant="secondary" size="sm">Editar</x-button>
                                    <x-button
                                        type="button"
                                        variant="neutral"
                                        size="sm"
                                        @click="openParaphraseModal({
                                            id: {{ $question->id }},
                                            title: @js($question->title),
                                            stem: @js($question->stem),
                                            actionUrl: @js(route('questions.paraphrase.store', $question)),
                                            count: {{ $paraphrasesCount }},
                                        })"
                                    >
                                        Paráfrasis
                                    </x-button>
                                    <form method="POST" action="{{ route('questions.duplicate', $question) }}" class="inline">
                                        @csrf
                                        <x-button type="submit" variant="neutral" size="sm">Copiar</x-button>
                                    </form>
                                    <form id="delete-table-question-{{ $question->id }}" method="POST" action="{{ route('questions.destroy', $question) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            @click="confirmDelete('delete-table-question-{{ $question->id }}', @js($question->title), {{ $examLinksCount }})"
                                        >
                                            Eliminar
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

            {{-- Paginación universal (visible tanto en móvil como en escritorio) --}}
            <div class="mt-6">
                {{ $questions->links() }}
            </div>
        @endif

        {{-- Nivel 5 · Modal 1: Paráfrasis Anti-Copia --}}
        <x-modal name="paraphrase-modal" title="Agregar paráfrasis anti-copia" max-width="lg">
            <div class="space-y-4">
                <div class="rounded-xl border border-acode-tech/25 bg-acode-surface/80 p-3.5 text-xs text-slate-700 leading-relaxed">
                    <p class="font-bold text-acode-primary mb-1">¿Cómo funciona en el examen?</p>
                    <p>
                        El sistema alterna equitativamente entre el enunciado original y las paráfrasis registradas entre distintos alumnos. No altera las opciones ni la respuesta correcta.
                    </p>
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                        Reactivo: <span class="text-acode-primary normal-case" x-text="paraphraseForm.title"></span>
                    </p>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700 whitespace-pre-line">
                        <span class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Enunciado original (solo lectura)</span>
                        <span x-text="paraphraseForm.originalStem"></span>
                    </div>
                </div>

                <form method="POST" x-bind:action="paraphraseForm.actionUrl" class="space-y-3">
                    @csrf
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="modal_paraphrase_stem" class="text-xs font-semibold text-slate-800">
                                Nueva variante de redacción <span class="text-acode-danger">*</span>
                            </label>
                            <span
                                class="text-[11px] font-mono"
                                :class="paraphraseForm.stem.length > 480 ? 'text-acode-danger font-bold' : 'text-slate-400'"
                                x-text="paraphraseForm.stem.length + ' / 500'"
                            ></span>
                        </div>
                        <x-form.textarea
                            id="modal_paraphrase_stem"
                            name="stem"
                            rows="3"
                            minlength="3"
                            maxlength="500"
                            required
                            x-model="paraphraseForm.stem"
                            placeholder="Redacta una forma equivalente manteniendo intacta la respuesta correcta (3 a 500 caracteres)…"
                        />
                        <p
                            x-show="paraphraseMatchesOriginal"
                            x-cloak
                            class="mt-1 text-xs font-medium text-acode-danger"
                        >
                            La paráfrasis debe ser distinta al enunciado original.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <x-button type="button" variant="neutral" size="md" @click="$dispatch('close-modal', 'paraphrase-modal')">
                            Cancelar
                        </x-button>
                        <x-button
                            type="submit"
                            variant="primary"
                            size="md"
                            x-bind:disabled="paraphraseMatchesOriginal || paraphraseForm.stem.trim().length < 3"
                        >
                            <x-icon name="save" class="w-4 h-4 mr-1.5" />
                            Guardar paráfrasis
                        </x-button>
                    </div>
                </form>
            </div>
        </x-modal>

        {{-- Nivel 5 · Modal 2: Carga Masiva (CSV / Excel .xlsx con Dry-Run Preview) --}}
        <x-modal
            name="bulk-import-modal"
            title="Importar reactivos en lote (CSV / Excel .xlsx)"
            max-width="2xl"
            :show="$errors->has('csv_file')"
        >
            <div class="space-y-5">
                {{-- Descarga de plantillas y alternativa rápida --}}
                <div class="rounded-xl border border-slate-200 bg-acode-surface/60 p-4 space-y-3">
                    <div>
                        <p class="text-xs font-bold text-acode-primary uppercase tracking-wider">1. Descarga una plantilla oficial</p>
                        <p class="text-xs text-slate-600 mt-0.5">
                            Incluye ejemplos listos para opción múltiple, relación de columnas, preguntas abiertas y código.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-button :href="route('questions.template.csv')" variant="neutral" size="sm">
                            <x-icon name="downloadReport" class="w-3.5 h-3.5 mr-1.5 text-acode-tech" />
                            Descargar plantilla CSV
                        </x-button>
                        <x-button :href="route('questions.template.xlsx')" variant="neutral" size="sm">
                            <x-icon name="downloadReport" class="w-3.5 h-3.5 mr-1.5 text-acode-accent" />
                            Descargar plantilla Excel (.xlsx)
                        </x-button>
                        <x-button :href="route('questions.batch.create')" variant="secondary" size="sm">
                            <x-icon name="add" class="w-3.5 h-3.5 mr-1.5" />
                            Captura rápida en lote
                        </x-button>
                    </div>
                </div>

                {{-- Formulario de carga y previsualización --}}
                <form method="POST" action="{{ route('questions.import') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <label for="bulk_csv_file" class="block text-xs font-bold text-acode-primary uppercase tracking-wider mb-1.5">
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
                                class="block w-full text-xs text-slate-700 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-acode-primary file:text-white hover:file:bg-acode-tech border border-slate-300 rounded-lg bg-white p-1.5"
                            />
                            <x-button
                                type="button"
                                variant="secondary"
                                size="md"
                                @click="runImportPreview()"
                                x-bind:disabled="importPreview.loading || !importPreview.hasFile"
                                class="shrink-0"
                            >
                                <x-icon name="search" class="w-4 h-4 mr-1.5" />
                                <span x-text="importPreview.loading ? 'Analizando…' : 'Previsualizar archivo'"></span>
                            </x-button>
                        </div>

                        @error('csv_file')
                            <p class="mt-1.5 text-xs font-medium text-acode-danger">{{ $message }}</p>
                        @enderror
                        <p x-show="importPreview.errorMessage" x-cloak x-text="importPreview.errorMessage" class="mt-1.5 text-xs font-medium text-acode-danger"></p>
                    </div>

                    {{-- Resultados de previsualización Dry-Run --}}
                    <div x-show="importPreview.data !== null" x-cloak class="space-y-3 border-t border-slate-100 pt-3">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <span class="block text-[11px] text-slate-500">Total filas</span>
                                <span class="text-base font-bold text-acode-primary" x-text="importPreview.data?.total_rows ?? 0"></span>
                            </div>
                            <div class="rounded-lg border border-acode-accent/30 bg-acode-accent/10 px-3 py-2">
                                <span class="block text-[11px] text-teal-800">Válidas</span>
                                <span class="text-base font-bold text-acode-accent" x-text="importPreview.data?.valid_count ?? 0"></span>
                            </div>
                            <div class="rounded-lg border border-acode-danger/30 bg-acode-danger/10 px-3 py-2">
                                <span class="block text-[11px] text-acode-danger">Con errores</span>
                                <span class="text-base font-bold text-acode-danger" x-text="importPreview.data?.error_count ?? 0"></span>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <span class="block text-[11px] text-slate-500">Cuota disponible</span>
                                <span
                                    class="text-base font-bold text-acode-primary"
                                    x-text="importPreview.data?.quota?.is_unlimited ? 'Ilimitado' : (importPreview.data?.quota?.remaining ?? 0)"
                                ></span>
                            </div>
                        </div>

                        {{-- Errores generales de lote / cuota --}}
                        <div
                            x-show="(importPreview.data?.errors || []).length > 0"
                            class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 p-3 text-xs text-acode-danger space-y-1"
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
                            class="rounded-xl border border-acode-warning/60 bg-acode-warning/15 p-3 text-xs text-amber-950 space-y-1"
                        >
                            <p class="font-bold">Posibles variaciones de escritura en materias detectadas:</p>
                            <ul class="list-disc pl-4 space-y-0.5">
                                <template x-for="(warn, wIdx) in (importPreview.data?.subject_warnings || [])" :key="wIdx">
                                    <li>
                                        «<strong x-text="warn.input"></strong>» es similar a:
                                        <span class="font-semibold" x-text="(warn.suggestions || []).join(', ')"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        {{-- Tabla fila por fila --}}
                        <div class="max-h-60 overflow-y-auto rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-xs text-left">
                                <thead class="bg-slate-50 sticky top-0 text-slate-600 font-semibold">
                                    <tr>
                                        <th class="px-3 py-2">Fila</th>
                                        <th class="px-3 py-2">Estado</th>
                                        <th class="px-3 py-2">Tipo</th>
                                        <th class="px-3 py-2">Título</th>
                                        <th class="px-3 py-2">Materia</th>
                                        <th class="px-3 py-2">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <template x-for="row in (importPreview.data?.rows || [])" :key="row.row">
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-slate-500" x-text="row.row"></td>
                                            <td class="px-3 py-2">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold"
                                                    :class="row.status === 'valid' ? 'bg-acode-accent/15 text-teal-800' : 'bg-acode-danger/10 text-acode-danger'"
                                                    x-text="row.status === 'valid' ? 'Válido' : 'Error'"
                                                ></span>
                                            </td>
                                            <td class="px-3 py-2 text-slate-600" x-text="row.question_type || '—'"></td>
                                            <td class="px-3 py-2 font-medium text-acode-primary max-w-[10rem] truncate" x-text="row.title || '—'"></td>
                                            <td class="px-3 py-2 text-slate-600" x-text="row.subject_tag || 'Sin materia'"></td>
                                            <td class="px-3 py-2">
                                                <template x-if="row.status === 'valid'">
                                                    <span class="text-teal-700">Lista para importar</span>
                                                </template>
                                                <template x-if="row.status !== 'valid'">
                                                    <span class="text-acode-danger" x-text="(row.errors || []).join(' · ')"></span>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-100">
                        <span class="text-xs text-slate-500" x-show="jsReady && !importPreview.canCommit">
                            Previsualiza un archivo válido para habilitar la confirmación.
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
                                <x-icon name="upload" class="w-4 h-4 mr-1.5" />
                                Confirmar e importar
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
                <p class="text-xs text-slate-600 leading-relaxed">
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
                                <label class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-3.5 py-2.5 hover:bg-slate-50 cursor-pointer">
                                    <div class="flex items-start gap-2.5">
                                        <input
                                            type="checkbox"
                                            name="source_subject_ids[]"
                                            value="{{ $subjectItem->id }}"
                                            x-model="mergeForm.selectedIds"
                                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-acode-tech focus:ring-acode-tech"
                                        />
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-semibold text-acode-primary">{{ $subjectItem->name }}</span>
                                                <x-badge variant="neutral">{{ (int) $subjectItem->questions_count }} reactivos</x-badge>
                                            </div>
                                            @if ($similar)
                                                <p class="mt-1 text-xs text-amber-800 font-medium">
                                                    Similar a «{{ $similar['name'] }}» ({{ (int) ($similar['questions_count'] ?? 0) }} reactivos)
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($similar)
                                        <button
                                            type="button"
                                            @click.prevent="quickSelectMerge([{{ $subjectItem->id }}, {{ (int) $similar['id'] }}], @js($preferredName))"
                                            class="self-start sm:self-center shrink-0 rounded-lg border border-acode-warning/60 bg-acode-warning/15 px-2.5 py-1 text-[11px] font-semibold text-amber-950 hover:bg-acode-warning/25 transition"
                                        >
                                            Unificar hacia la más usada
                                        </button>
                                    @endif
                                </label>
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

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <x-button type="button" variant="neutral" size="md" @click="$dispatch('close-modal', 'subjects-modal')">
                                Cancelar
                            </x-button>
                            <x-button
                                type="submit"
                                variant="primary"
                                size="md"
                                x-bind:disabled="mergeForm.selectedIds.length === 0 || mergeForm.targetName.trim().length < 2"
                            >
                                <x-icon name="save" class="w-4 h-4 mr-1.5" />
                                Unificar materias
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

                    openParaphraseModal(payload) {
                        this.paraphraseForm = {
                            id: payload.id,
                            title: payload.title || '',
                            originalStem: payload.stem || '',
                            stem: '',
                            actionUrl: payload.actionUrl || '',
                        };
                        this.$dispatch('open-modal', 'paraphrase-modal');
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
                            ? ' Nota: este reactivo está en ' + linkedCount + ' examen(es); si alguno está publicado o tiene intentos, no podrá eliminarse.'
                            : '';
                        AcodeAlert.confirm({
                            title: '¿Eliminar reactivo?',
                            text: 'El reactivo "' + title + '" será retirado de tu catálogo activo.' + warningSuffix,
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
