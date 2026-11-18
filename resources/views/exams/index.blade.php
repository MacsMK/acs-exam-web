{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Vista: exams/index.blade.php (MPW-V2 §4.1.2, §7, §8, Tabla 20, Tabla 28, RF-EXA) --}}
@extends('layouts.app')

@section('title', 'Mis exámenes')

@section('content')
    @php
        $hasActiveFilters = filled($filters['search'] ?? null)
            || (filled($filters['status'] ?? null) && ($filters['status'] ?? 'all') !== 'all')
            || filled($filters['group_id'] ?? null);

        $statusLabels = [
            'draft' => ['label' => 'Borrador', 'variant' => 'warning'],
            'scheduled' => ['label' => 'Programado', 'variant' => 'info'],
            'active' => ['label' => 'Activo', 'variant' => 'accent'],
            'closed' => ['label' => 'Cerrado', 'variant' => 'neutral'],
        ];
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6"
        x-data="{
            confirmExamAction(formId, title, text, confirmText, isDanger = true) {
                if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.confirm({
                        title: title,
                        text: text,
                        confirmButtonText: confirmText,
                        cancelButtonText: 'Cancelar',
                        isDanger: isDanger,
                        onConfirm: () => {
                            const form = document.getElementById(formId);
                            if (form) form.submit();
                        },
                    });
                    return;
                }
                if (confirm(title + '\n' + text)) {
                    const form = document.getElementById(formId);
                    if (form) form.submit();
                }
            }
        }"
    >
        @if ($errors->any())
            <div class="rounded-xl border border-acode-danger/30 bg-acode-danger/10 px-4 py-3 text-sm text-acode-danger" role="alert">
                <p class="font-semibold mb-1">Revisa lo siguiente:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Nivel 1: Hero Institucional y CTA Nuevo examen (MPW-V2 Tabla 11-13, §8.3) --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <div class="space-y-2 max-w-2xl">
                    <x-badge variant="info">
                        Gestión de Evaluaciones · Flujo Guiado
                    </x-badge>
                    <h1 class="text-2xl sm:text-[28px] sm:leading-9 font-bold tracking-tight text-acode-primary">
                        Mis exámenes
                    </h1>
                    <p class="text-sm text-slate-700 leading-5">
                        Diseña evaluaciones con sorteo inteligente o selección manual, calibra ponderaciones sobre 100.00 puntos, supervisa entregas en vivo y recoteja respuestas abiertas asistidas por IA.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <x-button :href="route('exams.create')" variant="primary" size="md">
                        <x-icon name="add" class="w-4 h-4" />
                        Nuevo examen
                    </x-button>

                    <x-button :href="route('teacher.reviews.index')" variant="neutral" size="md">
                        <x-icon name="view" class="w-4 h-4 text-acode-tech" />
                        Centro de recoteje
                    </x-button>
                </div>
            </div>
        </section>

        {{-- Nivel 2: 4 Tarjetas KPI --}}
        <section class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores de exámenes del docente">
            <x-card class="h-full">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total exámenes</p>
                        <p class="mt-1 text-[28px] leading-9 font-bold text-acode-primary tabular-nums">
                            {{ (int) ($stats['total'] ?? 0) }}
                        </p>
                    </div>
                    <span class="w-10 h-10 rounded-xl bg-acode-surface text-acode-tech flex items-center justify-center shrink-0">
                        <x-icon name="document" class="w-5 h-5" />
                    </span>
                </div>
                <p class="mt-4 text-xs text-slate-500">
                    <strong class="font-semibold text-acode-primary">{{ (int) ($stats['closed'] ?? 0) }}</strong> concluidos en historial
                </p>
            </x-card>

            <x-card class="h-full">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Borradores</p>
                        <p class="mt-1 text-[28px] leading-9 font-bold text-acode-warning-dark tabular-nums">
                            {{ (int) ($stats['drafts'] ?? 0) }}
                        </p>
                    </div>
                    <span class="w-10 h-10 rounded-xl bg-acode-warning/20 text-acode-primary flex items-center justify-center shrink-0">
                        <x-icon name="edit" class="w-5 h-5" />
                    </span>
                </div>
                <p class="mt-4 text-xs text-slate-500">
                    En armado, ponderación o listos para publicar
                </p>
            </x-card>

            <x-card class="h-full">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Activos ahora</p>
                        <p class="mt-1 text-[28px] leading-9 font-bold text-acode-accent tabular-nums">
                            {{ (int) ($stats['published_active'] ?? 0) }}
                        </p>
                    </div>
                    <span class="w-10 h-10 rounded-xl bg-acode-accent/15 text-acode-accent flex items-center justify-center shrink-0">
                        <x-icon name="clock" class="w-5 h-5" />
                    </span>
                </div>
                <p class="mt-4 text-xs text-slate-500">
                    <strong class="font-semibold text-acode-tech">{{ (int) ($stats['scheduled'] ?? 0) }}</strong> programados próximamente
                </p>
            </x-card>

            <x-card class="h-full">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pendientes de recoteje</p>
                        <p class="mt-1 text-[28px] leading-9 font-bold {{ (int) ($stats['pending_recoteje_exams'] ?? 0) > 0 ? 'text-acode-warning-dark' : 'text-acode-primary' }} tabular-nums">
                            {{ (int) ($stats['pending_recoteje_exams'] ?? 0) }}
                        </p>
                    </div>
                    <span class="w-10 h-10 rounded-xl {{ (int) ($stats['pending_recoteje_exams'] ?? 0) > 0 ? 'bg-acode-warning/20 text-acode-primary' : 'bg-slate-100 text-slate-500' }} flex items-center justify-center shrink-0">
                        <x-icon name="{{ (int) ($stats['pending_recoteje_exams'] ?? 0) > 0 ? 'warning' : 'success' }}" class="w-5 h-5" />
                    </span>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs">
                    @if ((int) ($stats['pending_recoteje_exams'] ?? 0) > 0)
                        <a href="{{ route('exams.index', ['status' => 'pending_review']) }}" class="font-semibold text-acode-tech hover:underline">
                            Filtrar pendientes →
                        </a>
                    @else
                        <span class="text-slate-500">Sin entregas pendientes por validar</span>
                    @endif
                </div>
            </x-card>
        </section>

        {{-- Nivel 3: Barra de Búsqueda y Filtros SQL --}}
        <section aria-label="Filtros del listado de exámenes">
            <x-search-filter
                :action="route('exams.index')"
                search-name="search"
                :search-value="$filters['search'] ?? ''"
                placeholder="Buscar examen por título o descripción…"
                :clear-url="route('exams.index')"
                :clear-keys="['search', 'status', 'group_id']"
                :has-active-filters="$hasActiveFilters"
            >
                <x-slot:filters>
                    <div class="w-full sm:w-52">
                        <x-form.select name="status" size="md" aria-label="Filtrar por estado">
                            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Todos los estados</option>
                            <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Borradores</option>
                            <option value="scheduled" @selected(($filters['status'] ?? '') === 'scheduled')>Programados</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Activos ahora</option>
                            <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Cerrados</option>
                            <option value="pending_review" @selected(($filters['status'] ?? '') === 'pending_review')>Pendientes de recoteje</option>
                        </x-form.select>
                    </div>

                    <div class="w-full sm:w-56">
                        <x-form.select name="group_id" size="md" aria-label="Filtrar por grupo escolar">
                            <option value="">Todos los grupos</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" @selected((string) ($filters['group_id'] ?? '') === (string) $group->id)>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </x-form.select>
                    </div>
                </x-slot:filters>
            </x-search-filter>
        </section>

        {{-- Nivel 4: Tabla Paginada de Exámenes --}}
        @if ($exams->isEmpty())
            <x-empty-state
                icon="document"
                :title="$hasActiveFilters ? 'No encontramos exámenes con esos filtros' : 'Todavía no has creado exámenes'"
                :message="$hasActiveFilters
                    ? 'Intenta limpiar los filtros o cambiar el término de búsqueda.'
                    : 'Crea tu primer examen para armar reactivos, definir ponderaciones sobre 100.00 puntos y asignarlo a tus grupos.'"
            >
                @if ($hasActiveFilters)
                    <x-button :href="route('exams.index')" variant="neutral" size="md">
                        Limpiar filtros
                    </x-button>
                @endif
                <x-button :href="route('exams.create')" variant="primary" size="md">
                    <x-icon name="add" class="w-4 h-4" />
                    Nuevo examen
                </x-button>
            </x-empty-state>
        @else
            <x-table>
                <x-slot:header>
                    <x-table.th>Examen</x-table.th>
                    <x-table.th>Grupos asignados</x-table.th>
                    <x-table.th>Estructura y escala</x-table.th>
                    <x-table.th>Ventana de aplicación</x-table.th>
                    <x-table.th align="center">Estado</x-table.th>
                    <x-table.th align="right">Acciones</x-table.th>
                </x-slot:header>

                @forelse ($exams as $exam)
                    @php
                        $opStatus = $exam->operationalStatus();
                        $badgeMeta = $statusLabels[$opStatus] ?? $statusLabels['draft'];
                        $sectionsCount = (int) ($exam->sections_count ?? 0);
                        $questionsCount = (int) ($exam->exam_questions_count ?? 0);
                        $attemptsCount = (int) ($exam->student_attempts_count ?? 0);
                        $pendingRecoteje = (int) ($exam->pending_recoteje_count ?? 0);
                        $totalWeight = (float) ($exam->total_points_weight ?? 0);
                    @endphp
                    <tr>
                        <x-table.td class="max-w-xs">
                            <a
                                href="{{ $opStatus === 'draft' ? route('exams.assemble', $exam) : route('exams.review', $exam) }}"
                                class="text-sm font-semibold text-acode-primary hover:text-acode-tech transition line-clamp-1"
                            >
                                {{ $exam->title }}
                            </a>
                            <p class="text-xs text-slate-500 line-clamp-2 mt-0.5">
                                {{ \Illuminate\Support\Str::limit($exam->description ?: 'Sin instrucciones registradas.', 90) }}
                            </p>
                        </x-table.td>

                        <x-table.td class="max-w-[14rem]">
                            <div class="flex flex-wrap gap-1.5">
                                @forelse ($exam->groups as $group)
                                    <x-badge variant="info" class="max-w-[12rem] truncate" title="{{ $group->name }}">
                                        {{ $group->name }}
                                    </x-badge>
                                @empty
                                    <span class="text-xs text-slate-500 italic">Sin grupos asignados</span>
                                @endforelse
                            </div>
                        </x-table.td>

                        <x-table.td class="whitespace-nowrap">
                            <div class="text-xs font-semibold text-acode-primary">
                                {{ $sectionsCount }} {{ $sectionsCount === 1 ? 'apartado' : 'apartados' }} · {{ $questionsCount }} {{ $questionsCount === 1 ? 'reactivo' : 'reactivos' }}
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs font-mono">
                                <span class="{{ abs($totalWeight - 100.0) <= 0.01 ? 'text-acode-accent font-semibold' : 'text-acode-warning-dark font-semibold' }}">
                                    {{ number_format($totalWeight, 2) }} / 100.00 pts
                                </span>
                                <span class="text-slate-300">·</span>
                                <span class="text-slate-600">Escala {{ $exam->grading_scale }}</span>
                            </div>
                        </x-table.td>

                        <x-table.td class="whitespace-nowrap text-xs text-slate-700">
                            <div>
                                <span class="font-semibold text-slate-500">Inicio:</span>
                                {{ $exam->starts_at?->format('d/m/Y H:i') ?? '—' }}
                            </div>
                            <div class="mt-0.5">
                                <span class="font-semibold text-slate-500">Cierre:</span>
                                {{ $exam->ends_at?->format('d/m/Y H:i') ?? '—' }}
                            </div>
                        </x-table.td>

                        <x-table.td align="center" class="whitespace-nowrap">
                            <div class="flex flex-col items-center gap-1.5">
                                <x-badge :variant="$badgeMeta['variant']">
                                    {{ $badgeMeta['label'] }}
                                </x-badge>

                                @if ($pendingRecoteje > 0)
                                    <x-badge variant="warning">
                                        {{ $pendingRecoteje }} por recotejar
                                    </x-badge>
                                @elseif ($attemptsCount > 0)
                                    <span class="text-xs text-slate-500">
                                        {{ $attemptsCount }} {{ $attemptsCount === 1 ? 'intento' : 'intentos' }}
                                    </span>
                                @endif
                            </div>
                        </x-table.td>

                        <x-table.td align="right" class="whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                @if ($opStatus === 'draft')
                                    <x-button :href="route('exams.assemble', $exam)" variant="primary" size="sm">
                                        Armar
                                    </x-button>
                                    <x-button :href="route('exams.configuration', $exam)" variant="secondary" size="sm">
                                        Configurar
                                    </x-button>
                                    <x-button
                                        :href="route('exams.edit', $exam)"
                                        variant="neutral"
                                        size="sm"
                                        :icon-only="true"
                                        title="Editar datos y grupos"
                                        aria-label="Editar datos y grupos"
                                    >
                                        <x-icon name="edit" class="w-4 h-4" />
                                    </x-button>
                                    <x-button
                                        :href="route('exams.review', $exam)"
                                        variant="neutral"
                                        size="sm"
                                        :icon-only="true"
                                        title="Abrir revisión y publicación"
                                        aria-label="Abrir revisión y publicación"
                                    >
                                        <x-icon name="view" class="w-4 h-4" />
                                    </x-button>

                                    @if ($attemptsCount === 0)
                                        <form id="delete-exam-{{ $exam->id }}" method="POST" action="{{ route('exams.destroy', $exam) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <x-button
                                                type="button"
                                                variant="danger"
                                                size="sm"
                                                :icon-only="true"
                                                title="Eliminar borrador"
                                                aria-label="Eliminar borrador"
                                                @click="confirmExamAction(
                                                    'delete-exam-{{ $exam->id }}',
                                                    '¿Eliminar borrador?',
                                                    'El examen ' + {{ \Illuminate\Support\Js::from($exam->title) }} + ' y sus apartados se eliminarán de forma permanente.',
                                                    'Eliminar',
                                                    true
                                                )"
                                            >
                                                <x-icon name="delete" class="w-4 h-4" />
                                            </x-button>
                                        </form>
                                    @endif
                                @else
                                    <x-button :href="route('exams.monitor', $exam)" variant="secondary" size="sm">
                                        Monitoreo
                                    </x-button>
                                    <x-button :href="route('exams.revision.index', $exam)" variant="primary" size="sm">
                                        Recoteje
                                    </x-button>
                                    <x-button
                                        :href="route('exams.review', $exam)"
                                        variant="neutral"
                                        size="sm"
                                        :icon-only="true"
                                        title="Abrir resumen del examen"
                                        aria-label="Abrir resumen del examen"
                                    >
                                        <x-icon name="view" class="w-4 h-4" />
                                    </x-button>

                                    @if ($attemptsCount === 0)
                                        <form id="unpublish-exam-{{ $exam->id }}" method="POST" action="{{ route('exams.unpublish', $exam) }}" class="inline">
                                            @csrf
                                            <x-button
                                                type="button"
                                                variant="neutral"
                                                size="sm"
                                                :icon-only="true"
                                                title="Despublicar examen"
                                                aria-label="Despublicar examen"
                                                @click="confirmExamAction(
                                                    'unpublish-exam-{{ $exam->id }}',
                                                    '¿Despublicar examen?',
                                                    'El examen regresará a borrador porque aún no registra intentos de estudiantes.',
                                                    'Despublicar',
                                                    false
                                                )"
                                            >
                                                <x-icon name="arrowLeft" class="w-4 h-4" />
                                            </x-button>
                                        </form>
                                    @endif

                                    @if (in_array($opStatus, ['active', 'scheduled'], true))
                                        <form id="close-exam-{{ $exam->id }}" method="POST" action="{{ route('exams.close', $exam) }}" class="inline">
                                            @csrf
                                            <x-button
                                                type="button"
                                                variant="warning"
                                                size="sm"
                                                :icon-only="true"
                                                title="Cerrar examen anticipadamente"
                                                aria-label="Cerrar examen anticipadamente"
                                                @click="confirmExamAction(
                                                    'close-exam-{{ $exam->id }}',
                                                    '¿Cerrar examen anticipadamente?',
                                                    'Los estudiantes ya no podrán iniciar ni enviar nuevos intentos en ' + {{ \Illuminate\Support\Js::from($exam->title) }} + '.',
                                                    'Cerrar examen',
                                                    true
                                                )"
                                            >
                                                <x-icon name="lock" class="w-4 h-4" />
                                            </x-button>
                                        </form>
                                    @endif

                                    @if ($exam->result_visibility_mode === 'hidden')
                                        <form id="release-exam-{{ $exam->id }}" method="POST" action="{{ route('exams.results.release', $exam) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <x-button
                                                type="button"
                                                variant="accent"
                                                size="sm"
                                                :icon-only="true"
                                                title="Liberar resultados a estudiantes"
                                                aria-label="Liberar resultados a estudiantes"
                                                @click="confirmExamAction(
                                                    'release-exam-{{ $exam->id }}',
                                                    '¿Liberar resultados?',
                                                    'Los estudiantes podrán consultar sus calificaciones obtenidas en este examen.',
                                                    'Liberar resultados',
                                                    false
                                                )"
                                            >
                                                <x-icon name="check" class="w-4 h-4" />
                                            </x-button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </x-table.td>
                    </tr>
                @empty
                    <tr>
                        <x-table.td colspan="6" class="py-8">
                            <x-empty-state
                                icon="document"
                                title="Sin exámenes para mostrar"
                                message="No hay exámenes registrados en esta página."
                            />
                        </x-table.td>
                    </tr>
                @endforelse
            </x-table>

            <div class="mt-6 flex justify-end">
                {{ $exams->links() }}
            </div>
        @endif
    </div>
@endsection
