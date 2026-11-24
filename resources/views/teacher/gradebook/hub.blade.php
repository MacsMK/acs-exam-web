{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/teacher/gradebook/hub.blade.php
    Ubicación:   resources/views/teacher/gradebook/hub.blade.php
    Descripción: Hub concentrador de control de calificaciones y periodos de
                 evaluación por grupo escolar, homologado con UI Kit A-Code
                 (MPW-V3 §7.3, §7.4.2, §7.8.2, §8.1–§8.12; MC-V3 §2.4).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Control de calificaciones')

@section('content')
    @php
        $hubUrl = route('teacher.gradebook.hub');
        $hasActiveFilters = $filters['search'] !== '' || $filters['status'] !== 'all' || $filters['subject'] !== '';
        $resultCount = $groups->total();
        $resultStatus = match ($resultCount) {
            0 => 'No hay grupos que coincidan',
            1 => '1 grupo encontrado',
            default => $resultCount . ' grupos encontrados',
        };
    @endphp

    <div class="space-y-6">
        <x-page-header
            title="Control de calificaciones"
            :subtitle="$hasGroups ? 'Busca un grupo y abre las calificaciones del periodo.' : null"
            institutional
        />

        @if ($hasGroups)
            <x-search-filter
                :action="$hubUrl"
                search-label="Buscar grupos"
                :search-value="$filters['search']"
                placeholder="Buscar por nombre, asignatura o folio…"
                results-id="teacher-gradebook-results"
                :clear-url="$hubUrl"
                :has-active-filters="$hasActiveFilters"
                live-clear
            >
                <x-slot:filters>
                    <x-form.group name="status" label="Estado" class="min-w-0 sm:w-32">
                        <x-form.select name="status" size="sm">
                            <option value="all" @selected($filters['status'] === 'all')>Todos</option>
                            <option value="active" @selected($filters['status'] === 'active')>Activos</option>
                            <option value="closed" @selected($filters['status'] === 'closed')>Cerrados</option>
                        </x-form.select>
                    </x-form.group>
                    <x-form.group name="subject" label="Asignatura" class="min-w-0 sm:w-44">
                        <x-form.select name="subject" size="sm">
                            <option value="">Todas</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject }}" @selected($filters['subject'] === $subject)>{{ $subject }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.group>
                </x-slot:filters>
            </x-search-filter>
        @endif

        @fragment('teacher-gradebook-results')
            <section
                id="teacher-gradebook-results"
                class="space-y-4"
                aria-label="Calificaciones por grupo"
                tabindex="-1"
                data-has-active-filters="{{ $hasActiveFilters ? '1' : '0' }}"
                data-search-status="{{ $hasGroups ? $resultStatus : 'Sin grupos escolares registrados' }}"
            >
                <x-section-header title="Calificaciones por grupo" institutional>
                    @if ($resultCount > 0)
                        <x-slot:actions>
                            <p class="text-sm text-slate-600">{{ $resultStatus }}</p>
                        </x-slot:actions>
                    @endif
                </x-section-header>

                @if (! $hasGroups)
                    <x-empty-state
                        icon="reportBar"
                        title="Sin grupos escolares registrados"
                        message="Crea un grupo escolar para configurar sus periodos y capturar calificaciones."
                    >
                        <x-button variant="primary" :href="route('groups.create', ['from_gradebook' => 1])">
                            <x-icon name="add" class="h-4 w-4" />
                            Crear grupo
                        </x-button>
                    </x-empty-state>
                @elseif ($groups->isEmpty())
                    <x-empty-state
                        icon="search"
                        title="No hay grupos que coincidan"
                        message="Cambia la búsqueda o limpia los filtros para ver otros grupos."
                    />
                @else
                    <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($groups as $group)
                            @php
                                $periods = $group->evaluationPeriods;
                                $periodNameCounts = $periods->countBy(fn ($period) => mb_strtolower($period->name));
                                $needsPeriods = ! $group->is_closed && $periods->isEmpty();
                                $periodsUrl = route('groups.periods.index', [$group, ...$returnQuery]);
                            @endphp

                            <x-card padding="p-4" class="min-w-0">
                                <div class="mb-4 space-y-2">
                                    <h3 class="break-words text-base font-semibold leading-6 text-acode-primary">{{ $group->name }}</h3>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                                            {{ $group->is_closed ? 'Grupo cerrado' : 'Grupo activo' }}
                                        </x-badge>
                                        @if (filled($group->access_folio))
                                            <span class="max-w-full select-all break-words rounded-lg border border-slate-200 bg-acode-surface px-2 py-0.5 font-mono text-xs font-medium text-acode-ink">
                                                Folio: {{ $group->access_folio }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="break-words text-xs text-slate-500">
                                        {{ filled($group->subject) ? 'Asignatura: ' . $group->subject : 'Sin asignatura' }}
                                    </p>
                                </div>

                                <div class="mb-4 space-y-2">
                                    <h4 class="text-sm font-semibold text-acode-primary">Periodos de evaluación</h4>
                                    @if ($periods->isEmpty())
                                        <p class="text-xs text-slate-500">No hay periodos creados aún.</p>
                                        @if ($needsPeriods)
                                            <x-button
                                                :href="$periodsUrl"
                                                :aria-label="'Abrir periodos de ' . $group->name"
                                                variant="primary"
                                                size="sm"
                                            >
                                                Abrir periodos
                                            </x-button>
                                        @endif
                                    @else
                                        <ul class="space-y-2" role="list">
                                            @foreach ($periods as $period)
                                                @php
                                                    $dates = null;
                                                    if ($periodNameCounts[mb_strtolower($period->name)] > 1) {
                                                        $dates = $period->starts_at->format('d/m/Y');
                                                        if (! $period->starts_at->isSameDay($period->ends_at)) {
                                                            $dates .= ' – ' . $period->ends_at->format('d/m/Y');
                                                        }
                                                    }
                                                @endphp
                                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-2 py-2">
                                                    <div class="min-w-0 flex-1 basis-32">
                                                        <p class="break-words text-xs font-medium text-slate-700">{{ $period->name }}</p>
                                                        @if ($dates)
                                                            <p class="text-xs text-slate-500">{{ $dates }}</p>
                                                        @endif
                                                    </div>
                                                    <x-button
                                                        :href="route('groups.periods.gradebook', [$group, $period, ...$returnQuery])"
                                                        :aria-label="'Abrir calificaciones de ' . $period->name . ($dates ? ' (' . $dates . ')' : '') . ', grupo ' . $group->name"
                                                        variant="primary"
                                                        size="sm"
                                                    >
                                                        Abrir calificaciones
                                                    </x-button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                <dl class="mb-3 grid grid-cols-2 gap-x-2 gap-y-2 text-xs text-slate-600 sm:grid-cols-3">
                                    <div>
                                        <dt>Estudiantes inscritos</dt>
                                        <dd class="font-semibold tabular-nums text-acode-primary">{{ $group->students_count }}</dd>
                                    </div>
                                    <div>
                                        <dt>Periodos configurados</dt>
                                        <dd class="font-semibold tabular-nums text-acode-primary">{{ $group->evaluation_periods_count }}</dd>
                                    </div>
                                    <div>
                                        <dt>Exámenes asignados</dt>
                                        <dd class="font-semibold tabular-nums text-acode-primary">{{ $group->exams_count }}</dd>
                                    </div>
                                </dl>

                                <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 pt-3">
                                    @unless ($needsPeriods)
                                        <x-button
                                            :href="$periodsUrl"
                                            :aria-label="'Abrir periodos de ' . $group->name"
                                            variant="secondary"
                                            size="sm"
                                        >
                                            Abrir periodos
                                        </x-button>
                                    @endunless
                                    <x-button
                                        :href="route('groups.show', [$group, ...$returnQuery])"
                                        :aria-label="'Abrir grupo ' . $group->name"
                                        variant="neutral"
                                        size="sm"
                                    >
                                        Abrir grupo
                                    </x-button>
                                </div>
                            </x-card>
                        @endforeach
                    </div>

                    {{ $groups->onEachSide(1)->links('pagination.acode', ['ariaLabel' => 'Paginación de grupos']) }}

                    <x-catalog-summary :title="$hasActiveFilters ? 'Resumen de grupos filtrados' : 'Resumen de grupos'">
                        <x-summary-item label="Periodos configurados" :value="$stats['periods']" icon="clock" />
                        <x-summary-item label="Exámenes asignados" :value="$stats['examAssignments']" icon="document"
                            note="Cada examen cuenta una vez por grupo al que se asignó." />
                    </x-catalog-summary>
                @endif
            </section>
        @endfragment
    </div>
@endsection
