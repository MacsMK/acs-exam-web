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
    $groupsCollection = $groups->getCollection();
    $totalPeriodsInView = (int) $groupsCollection->sum('evaluation_periods_count');
    $totalExamsInView = (int) $groupsCollection->sum('exams_count');
@endphp

<div
    x-data="{
        search: '',
        statusFilter: 'all',

        groupsIndex: @js(
            $groupsCollection->map(fn ($group) => [
                'id' => $group->id,
                'is_closed' => (bool) $group->is_closed,
                'haystack' => mb_strtolower(
                    $group->name . ' ' .
                    ($group->subject ?? '') . ' ' .
                    ($group->access_folio ?? '')
                ),
            ])->values()
        ),

        get visibleCount() {
            const term = this.search.toLowerCase().trim();

            return this.groupsIndex.filter(group => {
                const matchesSearch = !term || group.haystack.includes(term);
                const matchesStatus =
                    this.statusFilter === 'all' ||
                    (this.statusFilter === 'active' && !group.is_closed) ||
                    (this.statusFilter === 'closed' && group.is_closed);

                return matchesSearch && matchesStatus;
            }).length;
        },

        matchesGroup(haystack, isClosed) {
            const term = this.search.toLowerCase().trim();
            const matchesSearch = !term || haystack.includes(term);
            const matchesStatus =
                this.statusFilter === 'all' ||
                (this.statusFilter === 'active' && !isClosed) ||
                (this.statusFilter === 'closed' && isClosed);

            return matchesSearch && matchesStatus;
        }
    }"
    class="space-y-6"
>

    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        title="Control de calificaciones"
        subtitle="Consulta y captura calificaciones ponderadas por grupo, gestiona parciales y descarga reportes en Excel."
    >
        <x-slot:actions>
            <x-button :href="route('groups.index')" variant="neutral" size="md">
                <x-icon name="userGroup" class="w-4 h-4" />
                Abrir grupos
            </x-button>

            <x-button :href="route('groups.create')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4" />
                Crear grupo
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Fila de 3 KPIs Resumen (sin consultas adicionales) --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-kpi-card
            label="Grupos en vista"
            :value="$groups->count()"
            :meta="'De ' . $groups->total() . ' ' . ($groups->total() === 1 ? 'grupo registrado' : 'grupos registrados')"
            nav-icon="groups"
            tone="tech"
        />

        <x-kpi-card
            label="Periodos configurados"
            :value="$totalPeriodsInView"
            meta="Parciales en los grupos listados"
            icon="clock"
            tone="accent"
        />

        <x-kpi-card
            label="Exámenes aplicados"
            :value="$totalExamsInView"
            meta="Evaluaciones vinculadas"
            nav-icon="exams"
            tone="primary"
        />
    </div>

    @if ($groups->isEmpty())
        <x-empty-state
            icon="reportBar"
            title="Sin grupos escolares registrados"
            message="Crea un grupo escolar para definir esquemas evaluativos, asociar exámenes y capturar calificaciones."
        >
            <x-button variant="primary" size="md" :href="route('groups.create')">
                <x-icon name="add" class="w-4 h-4" />
                Crear grupo
            </x-button>
        </x-empty-state>
    @else
        {{-- Nivel 3: Barra de Búsqueda y Filtro Client-Side --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="w-full lg:max-w-md">
                    <label for="gradebook-hub-search" class="sr-only">
                        Buscar grupo, materia o folio
                    </label>
                    <div class="relative">
                        <x-form.input
                            id="gradebook-hub-search"
                            type="text"
                            autocomplete="off"
                            prefixIcon="search"
                            x-model="search"
                            placeholder="Buscar por grupo, materia o folio..."
                            class="pr-10"
                        />
                        <button
                            type="button"
                            title="Limpiar búsqueda"
                            aria-label="Limpiar búsqueda"
                            x-show="search.length > 0"
                            x-cloak
                            x-on:click="search = ''"
                            class="absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-acode-tech"
                        >
                            <x-icon name="xMark" class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <x-toggle-group
                    model="statusFilter"
                    :options="['all' => 'Todos', 'active' => 'Activos', 'closed' => 'Cerrados']"
                    label="Filtrar grupos por estado"
                />
            </div>
        </div>

        {{-- Cabecera de Sección --}}
        <x-section-header title="Grupos y sábanas de calificaciones">
            <x-slot:actions>
                <p class="text-xs sm:text-sm text-slate-500">
                    Mostrando
                    <span class="font-bold text-acode-primary" x-text="visibleCount"></span>
                    de
                    <span class="font-bold text-slate-700">{{ $groups->count() }}</span>
                    {{ $groups->count() === 1 ? 'grupo en esta página' : 'grupos en esta página' }}
                </p>
            </x-slot:actions>
        </x-section-header>

        {{-- Nivel 4: Cuadrícula de Grupos con Periodos y Accesos Rápidos --}}
        <div
            x-show="visibleCount > 0"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
        >
            @forelse ($groups as $group)
                @php
                    $groupHaystack = mb_strtolower($group->name . ' ' . ($group->subject ?? '') . ' ' . ($group->access_folio ?? ''));
                @endphp
                <div
                    x-show="matchesGroup(@js($groupHaystack), @js((bool) $group->is_closed))"
                    class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-acode-tech/40 hover:shadow-md"
                >
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <x-badge :variant="$group->is_closed ? 'neutral' : 'accent'">
                                {{ $group->is_closed ? 'Semestre cerrado' : 'Grupo activo' }}
                            </x-badge>

                            <span class="text-xs font-mono bg-acode-surface px-2.5 py-0.5 rounded-lg border border-slate-200 text-acode-primary font-semibold select-all">
                                Folio: {{ $group->access_folio }}
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-acode-primary mb-1 break-words">
                            {{ $group->name }}
                        </h3>
                        <p class="text-xs text-slate-500 mb-4">
                            Materia: {{ $group->subject ?? 'Sin materia asignada' }}
                        </p>

                        <div class="bg-acode-surface/60 border border-slate-200/70 rounded-lg p-3 text-xs text-slate-600 space-y-1.5 mb-4">
                            <div class="flex justify-between">
                                <span>Estudiantes inscritos</span>
                                <span class="font-semibold text-acode-primary tabular-nums">{{ $group->students_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Periodos configurados</span>
                                <span class="font-semibold text-acode-primary tabular-nums">{{ $group->evaluation_periods_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Exámenes aplicados</span>
                                <span class="font-semibold text-acode-primary tabular-nums">{{ $group->exams_count }}</span>
                            </div>
                        </div>

                        {{-- Lista de periodos del grupo --}}
                        <div class="space-y-1.5 mb-4">
                            <p class="text-xs font-semibold text-slate-700">Periodos evaluativos</p>
                            @forelse ($group->evaluationPeriods as $period)
                                <div class="flex items-center justify-between gap-2 text-xs py-1.5 px-2.5 rounded-lg bg-white border border-slate-200/80 hover:border-acode-tech/50 transition">
                                    <span class="min-w-0 flex-1 font-medium text-slate-700 truncate">
                                        {{ $period->name }}
                                    </span>
                                    <a
                                        href="{{ route('groups.periods.gradebook', [$group, $period]) }}"
                                        class="inline-flex items-center gap-1 text-acode-tech hover:underline font-semibold shrink-0"
                                    >
                                        <span>Abrir calificaciones</span>
                                        <x-icon name="chevronRight" class="w-3.5 h-3.5" />
                                    </a>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 italic">No hay periodos creados aún.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                        <x-button variant="neutral" size="sm" :href="route('groups.show', $group)">
                            Ver detalles
                        </x-button>

                        <x-button variant="secondary" size="sm" :href="route('groups.periods.index', $group)">
                            Abrir periodos
                        </x-button>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <x-empty-state
                        icon="reportBar"
                        title="Sin grupos escolares registrados"
                        message="Crea un grupo escolar para comenzar a gestionar calificaciones."
                    />
                </div>
            @endforelse
        </div>

        {{-- Estado vacío reactivo ante búsqueda sin coincidencias --}}
        <div x-show="visibleCount === 0" x-cloak>
            <x-empty-state
                icon="search"
                title="No encontramos grupos escolares"
                message="Ningún grupo coincide con los criterios de búsqueda o estado seleccionados."
            >
                <x-button
                    type="button"
                    variant="neutral"
                    size="sm"
                    @click="search = ''; statusFilter = 'all'"
                >
                    Limpiar filtros
                </x-button>
            </x-empty-state>
        </div>

        @if ($groups->hasPages())
            <div class="flex justify-end">
                {{ $groups->links() }}
            </div>
        @endif
    @endif

</div>
@endsection
