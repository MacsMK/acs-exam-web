{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/groups/index.blade.php
    Ubicación:   resources/views/groups/index.blade.php
    Descripción: Catálogo operativo de grupos escolares del docente con arquitectura
                 espacial de 5 niveles: hero institucional, métricas KPI en tiempo real,
                 barra de búsqueda y filtrado multidimensional con alternancia de vista,
                 cuadrícula de tarjetas con folio y microinteracciones, vista de tabla
                 compacta y modal dinámico de código QR institucional (MPW-V2 §5.2.1,
                 §7.2, §7.3, §7.8.2, §8.1.1, §8.4, §8.6, Tabla 20, Tabla 29).
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Mis grupos escolares')

@section('content')
<div
    x-data="{
        search: '',
        filter: 'all',
        viewMode: 'grid',
        qrModalOpen: false,
        selectedGroup: null,
        copiedFolio: false,

        groups: @js(
            $groups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $group->subject ?? 'Sin asignatura',
                'students_count' => (int) $group->students_count,
                'is_closed' => (bool) $group->is_closed,
                'folio' => $group->access_folio,
                'url' => route('groups.show', $group),
                'periods_url' => route('groups.periods.index', $group),
            ])->values()
        ),

        get filteredGroups() {
            const term = this.search.trim().toLocaleLowerCase();

            return this.groups
                .filter(group => {
                    const matchesSearch = !term ||
                        (group.name && group.name.toLocaleLowerCase().includes(term)) ||
                        (group.subject && group.subject.toLocaleLowerCase().includes(term)) ||
                        (group.folio && group.folio.toLocaleLowerCase().includes(term));

                    const matchesStatus =
                        this.filter === 'all' ||
                        (this.filter === 'active' && !group.is_closed) ||
                        (this.filter === 'closed' && group.is_closed);

                    return matchesSearch && matchesStatus;
                })
                .sort((a, b) => {
                    if (a.is_closed !== b.is_closed) {
                        return Number(a.is_closed) - Number(b.is_closed);
                    }

                    return a.name.localeCompare(b.name, 'es');
                });
        },

        openQr(group) {
            this.selectedGroup = group;
            this.copiedFolio = false;
            this.qrModalOpen = true;
        },

        closeQr() {
            this.qrModalOpen = false;
            this.selectedGroup = null;
            this.copiedFolio = false;
        },

        copyFolio(folio) {
            if (!folio) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(folio).then(() => {
                    this.copiedFolio = true;
                    setTimeout(() => { this.copiedFolio = false; }, 2500);

                    if (typeof AcodeAlert !== 'undefined') {
                        AcodeAlert.toast('Folio copiado al portapapeles con éxito', 'success');
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            iconColor: '#16A6A1',
                            title: 'Folio copiado al portapapeles con éxito',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            customClass: {
                                popup: 'rounded-xl shadow-lg text-sm font-medium font-sans'
                            }
                        });
                    }
                }).catch(() => {});
            }
        }
    }"
    @keydown.escape.window="closeQr()"
    class="space-y-6"
>

    {{-- Nivel 1: Hero Institucional + Botón canónico Crear grupo --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-5 rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 shadow-sm">
        <div class="max-w-2xl">
            <h1 class="text-2xl sm:text-3xl font-bold text-acode-primary tracking-tight">
                Mis grupos escolares
            </h1>
            <p class="mt-2 text-sm text-slate-500 leading-relaxed">
                Gestiona tus aulas escolares, consulta folios de acceso, supervisa la matrícula y accede a las evaluaciones por periodo.
            </p>
        </div>

        <div class="flex items-center shrink-0">
            <x-button :href="route('groups.create')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4 mr-1.5" />
                Crear grupo
            </x-button>
        </div>
    </div>

    {{-- Nivel 2: Barra de 4 Tarjetas KPI en Grid --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total de grupos --}}
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-icon name="userGroup" class="w-6 h-6 text-acode-tech" />
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Total de grupos
                </span>
            </div>
            <div class="mt-4">
                <p class="text-3xl lg:text-4xl font-extrabold text-acode-primary tracking-tight">
                    {{ $groups->count() }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Aulas escolares registradas
                </p>
            </div>
        </div>

        {{-- KPI 2: Grupos activos --}}
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-acode-accent/10 text-acode-accent">
                    <x-icon name="check" class="w-6 h-6 text-acode-accent" />
                </div>
                <x-badge variant="accent">
                    Grupos activos
                </x-badge>
            </div>
            <div class="mt-4">
                <p class="text-3xl lg:text-4xl font-extrabold text-acode-primary tracking-tight">
                    {{ $groups->where('is_closed', false)->count() }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    En ciclo lectivo vigente
                </p>
            </div>
        </div>

        {{-- KPI 3: Grupos cerrados --}}
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                    <x-icon name="lock" class="w-6 h-6 text-slate-500" />
                </div>
                <x-badge variant="neutral">
                    Grupos cerrados
                </x-badge>
            </div>
            <div class="mt-4">
                <p class="text-3xl lg:text-4xl font-extrabold text-slate-600 tracking-tight">
                    {{ $groups->where('is_closed', true)->count() }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Ciclos escolares concluidos
                </p>
            </div>
        </div>

        {{-- KPI 4: Alumnos matriculados --}}
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-acode-surface text-acode-tech">
                    <x-icon name="user" class="w-6 h-6 text-acode-tech" />
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Alumnos matriculados
                </span>
            </div>
            <div class="mt-4">
                <p class="text-3xl lg:text-4xl font-extrabold text-acode-primary tracking-tight">
                    {{ $groups->sum('students_count') }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Censo total en tus aulas
                </p>
            </div>
        </div>
    </div>

    {{-- Nivel 3: Barra de Búsqueda y Filtros de Estado + Alternador de Vista --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            {{-- Buscador predictivo multidimensional --}}
            <div class="w-full lg:max-w-md">
                <label for="group-search" class="sr-only">
                    Buscar grupo, materia o folio
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <x-icon name="search" class="w-4 h-4 text-slate-400" />
                    </div>
                    <input
                        id="group-search"
                        type="search"
                        x-model="search"
                        placeholder="Buscar por grupo, asignatura o folio..."
                        class="h-10 w-full rounded-lg border border-slate-300 pl-10 pr-9 text-sm text-slate-800 placeholder-slate-400 outline-none transition focus:border-acode-tech focus:ring-2 focus:ring-acode-tech/20"
                    >
                    <button
                        type="button"
                        x-show="search.length > 0"
                        @click="search = ''"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 transition"
                        title="Limpiar búsqueda"
                    >
                        <x-icon name="xMark" class="w-4 h-4" />
                    </button>
                </div>
            </div>

            {{-- Filtros de estado y switch de vista --}}
            <div class="flex flex-wrap items-center justify-between lg:justify-end gap-3">
                {{-- Filtro de Estado --}}
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        @click="filter = 'all'"
                        :class="filter === 'all'
                            ? 'bg-acode-primary text-white shadow-sm'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="h-9 rounded-lg px-3.5 text-xs sm:text-sm font-semibold transition"
                    >
                        Todos
                    </button>

                    <button
                        type="button"
                        @click="filter = 'active'"
                        :class="filter === 'active'
                            ? 'bg-acode-primary text-white shadow-sm'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="h-9 rounded-lg px-3.5 text-xs sm:text-sm font-semibold transition"
                    >
                        Activos
                    </button>

                    <button
                        type="button"
                        @click="filter = 'closed'"
                        :class="filter === 'closed'
                            ? 'bg-acode-primary text-white shadow-sm'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="h-9 rounded-lg px-3.5 text-xs sm:text-sm font-semibold transition"
                    >
                        Cerrados
                    </button>
                </div>

                {{-- Separador vertical en desktop --}}
                <div class="hidden sm:block h-6 w-px bg-slate-200"></div>

                {{-- Alternancia de Vista: Cuadrícula / Lista --}}
                <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50 p-0.5">
                    <button
                        type="button"
                        @click="viewMode = 'grid'"
                        :class="viewMode === 'grid'
                            ? 'bg-white text-acode-primary shadow-sm font-semibold'
                            : 'text-slate-500 hover:text-slate-700'"
                        class="inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-xs font-medium transition"
                        title="Vista en cuadrícula"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                        <span class="hidden sm:inline">Cuadrícula</span>
                    </button>

                    <button
                        type="button"
                        @click="viewMode = 'list'"
                        :class="viewMode === 'list'
                            ? 'bg-white text-acode-primary shadow-sm font-semibold'
                            : 'text-slate-500 hover:text-slate-700'"
                        class="inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-xs font-medium transition"
                        title="Vista en lista"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <span class="hidden sm:inline">Lista</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Estado Inicial: Sin grupos creados en base de datos --}}
    @if ($groups->isEmpty())
        <x-empty-state
            icon="userGroup"
            title="Todavía no tienes grupos escolares"
            message="Crea tu primer grupo escolar para organizar a tus estudiantes, asignar exámenes y gestionar evaluaciones por periodos."
        >
            <x-button :href="route('groups.create')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4 mr-1.5" />
                Crear grupo
            </x-button>
        </x-empty-state>
    @else

        {{-- Cabecera de Resultados y Conteo --}}
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-acode-primary">
                Grupos registrados
            </h2>

            <p class="text-xs sm:text-sm text-slate-500">
                Mostrando
                <span class="font-bold text-acode-primary" x-text="filteredGroups.length"></span>
                de
                <span class="font-bold text-slate-700">{{ $groups->count() }}</span>
                {{ $groups->count() === 1 ? 'grupo' : 'grupos' }}
            </p>
        </div>

        {{-- Nivel 4A: Cuadrícula de Tarjetas (Grid View) --}}
        <div
            x-show="filteredGroups.length > 0 && viewMode === 'grid'"
            class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3"
        >
            <template x-for="group in filteredGroups" :key="group.id">
                <article class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div>
                        {{-- Cabecera de tarjeta: Materia en mayúsculas + Badge semántico de estado --}}
                        <div class="flex items-start justify-between gap-3">
                            <span
                                class="text-xs font-bold uppercase tracking-wider text-acode-tech break-words"
                                x-text="group.subject || 'SIN ASIGNATURA'"
                            ></span>

                            <span
                                class="inline-flex items-center rounded-full text-xs px-2.5 py-0.5 font-medium tracking-wide shrink-0"
                                :class="group.is_closed
                                    ? 'bg-slate-100 text-slate-700 border border-slate-200'
                                    : 'bg-acode-accent/15 text-acode-accent border border-acode-accent/30'"
                                x-text="group.is_closed ? 'Cerrado' : 'Activo'"
                            ></span>
                        </div>

                        {{-- Título del grupo escolar --}}
                        <h3
                            class="mt-3 break-words text-lg font-bold text-acode-primary leading-snug"
                            x-text="group.name"
                        ></h3>

                        {{-- Chip de folio de acceso alfanumérico visible con botón rápido de copiado --}}
                        <div class="mt-4 flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2">
                            <div class="flex items-center gap-1.5 overflow-hidden">
                                <span class="text-[11px] font-semibold uppercase text-slate-400 shrink-0">Folio:</span>
                                <span
                                    class="font-mono text-xs sm:text-sm font-bold tracking-wider text-acode-primary truncate select-all"
                                    x-text="group.folio || 'N/D'"
                                ></span>
                            </div>

                            <button
                                type="button"
                                @click="copyFolio(group.folio)"
                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-acode-tech hover:bg-acode-surface transition focus:outline-none focus:ring-1 focus:ring-acode-tech"
                                title="Copiar folio al portapapeles"
                            >
                                <x-icon name="copy" class="w-3.5 h-3.5" />
                                <span>Copiar</span>
                            </button>
                        </div>

                        {{-- Conteo de alumnos con icono oficial x-icon --}}
                        <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3 text-xs text-slate-600">
                            <x-icon name="userGroup" class="w-4 h-4 text-acode-tech shrink-0" />
                            <span class="font-bold text-slate-800" x-text="group.students_count"></span>
                            <span x-text="group.students_count === 1 ? 'alumno inscrito' : 'alumnos inscritos'"></span>
                        </div>
                    </div>

                    {{-- Tríada de botones de acción canónicos --}}
                    <div class="mt-5 flex items-center gap-2 border-t border-slate-100 pt-4">
                        {{-- 1. Abrir grupo --}}
                        <x-button
                            ::href="group.url"
                            variant="primary"
                            size="sm"
                            class="flex-1"
                        >
                            Abrir grupo
                        </x-button>

                        {{-- 2. Periodos --}}
                        <x-button
                            ::href="group.periods_url"
                            variant="neutral"
                            size="sm"
                        >
                            Periodos
                        </x-button>

                        {{-- 3. Botón QR iconográfico --}}
                        <x-button
                            type="button"
                            variant="neutral"
                            size="sm"
                            @click="openQr(group)"
                            title="Ver Código QR del grupo"
                            aria-label="Ver Código QR"
                            class="px-2.5"
                        >
                            <x-icon name="qrCode" class="w-4 h-4 text-acode-primary" />
                        </x-button>
                    </div>
                </article>
            </template>
        </div>

        {{-- Nivel 4B: Vista de Tabla Compacta (List View) --}}
        <div
            x-show="filteredGroups.length > 0 && viewMode === 'list'"
            x-cloak
        >
            <x-table>
                <x-slot:header>
                    <x-table.th>Grupo</x-table.th>
                    <x-table.th>Folio de acceso</x-table.th>
                    <x-table.th align="center">Alumnos inscritos</x-table.th>
                    <x-table.th align="center">Estado</x-table.th>
                    <x-table.th align="right">Acciones</x-table.th>
                </x-slot:header>

                <template x-for="group in filteredGroups" :key="group.id">
                    <tr>
                        <x-table.td>
                            <div class="font-bold text-acode-primary text-sm" x-text="group.name"></div>
                            <div class="text-xs uppercase font-semibold text-acode-tech mt-0.5" x-text="group.subject || 'SIN ASIGNATURA'"></div>
                        </x-table.td>

                        <x-table.td>
                            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1">
                                <span class="font-mono text-xs font-bold text-acode-primary select-all" x-text="group.folio || 'N/D'"></span>
                                <button
                                    type="button"
                                    @click="copyFolio(group.folio)"
                                    class="text-slate-400 hover:text-acode-tech transition"
                                    title="Copiar folio"
                                >
                                    <x-icon name="copy" class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </x-table.td>

                        <x-table.td align="center">
                            <div class="inline-flex items-center gap-1.5 text-xs text-slate-700">
                                <x-icon name="userGroup" class="w-4 h-4 text-acode-tech shrink-0" />
                                <span class="font-bold text-slate-800" x-text="group.students_count"></span>
                            </div>
                        </x-table.td>

                        <x-table.td align="center">
                            <span
                                class="inline-flex items-center rounded-full text-xs px-2.5 py-0.5 font-medium tracking-wide"
                                :class="group.is_closed
                                    ? 'bg-slate-100 text-slate-700 border border-slate-200'
                                    : 'bg-acode-accent/15 text-acode-accent border border-acode-accent/30'"
                                x-text="group.is_closed ? 'Cerrado' : 'Activo'"
                            ></span>
                        </x-table.td>

                        <x-table.td align="right">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-button
                                    ::href="group.url"
                                    variant="primary"
                                    size="sm"
                                >
                                    Abrir grupo
                                </x-button>

                                <x-button
                                    ::href="group.periods_url"
                                    variant="neutral"
                                    size="sm"
                                >
                                    Periodos
                                </x-button>

                                <x-button
                                    type="button"
                                    variant="neutral"
                                    size="sm"
                                    @click="openQr(group)"
                                    title="Ver Código QR"
                                    aria-label="Ver Código QR"
                                    class="px-2"
                                >
                                    <x-icon name="qrCode" class="w-4 h-4 text-acode-primary" />
                                </x-button>
                            </div>
                        </x-table.td>
                    </tr>
                </template>
            </x-table>
        </div>

        {{-- Sin coincidencias por búsqueda o filtro --}}
        <div x-show="filteredGroups.length === 0" x-cloak class="mt-2">
            <x-empty-state
                icon="search"
                title="No encontramos grupos escolares"
                message="No se encontraron grupos que coincidan con los criterios de búsqueda o filtro seleccionados."
            >
                <x-button
                    type="button"
                    variant="neutral"
                    size="sm"
                    @click="search = ''; filter = 'all'"
                >
                    Limpiar filtros
                </x-button>
            </x-empty-state>
        </div>

    @endif

    {{-- Nivel 5: Modal Dinámico de Código QR Institucional --}}
    <div
        x-show="qrModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="qr-modal-title"
    >
        {{-- Fondo oscurecido y desenfocado normativo --}}
        <div
            x-show="qrModalOpen"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
            @click="closeQr()"
            aria-hidden="true"
        ></div>

        {{-- Contenedor de la ventana modal (16px rounded-2xl, shadow-xl) --}}
        <div
            x-show="qrModalOpen"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative bg-white rounded-2xl shadow-xl overflow-hidden transform transition-all w-full border border-slate-200 z-10 sm:max-w-md"
        >
            {{-- Encabezado con título institucional y botón de cierre 'X' --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/70">
                <h3 id="qr-modal-title" class="text-base font-bold text-acode-primary tracking-tight truncate pr-2">
                    Código QR y Folio · <span x-text="selectedGroup?.name || 'Grupo escolar'"></span>
                </h3>
                <button
                    type="button"
                    @click="closeQr()"
                    class="text-slate-400 hover:text-slate-600 rounded-lg p-1 transition focus:outline-none focus:ring-2 focus:ring-acode-tech"
                    aria-label="Cerrar modal"
                >
                    <x-icon name="xMark" class="w-5 h-5" />
                </button>
            </div>

            {{-- Cuerpo del modal --}}
            <div class="px-6 py-5 text-sm text-slate-700 flex flex-col items-center text-center">
                <p class="text-xs text-slate-500 mb-4 max-w-xs">
                    Comparte este código QR o el folio alfanumérico con los estudiantes para que se matriculen en el grupo escolar.
                </p>

                {{-- Visualizador de Código QR en alta definición --}}
                <div class="p-3 bg-white border border-slate-200 rounded-2xl shadow-sm mb-4 inline-flex items-center justify-center">
                    <img
                        :src="'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(selectedGroup?.folio || '') + '&color=14-43-74&bgcolor=255-255-255&margin=1'"
                        :alt="'Código QR de acceso al grupo ' + (selectedGroup?.name || '')"
                        class="w-48 h-48 object-contain rounded-lg"
                        loading="lazy"
                    />
                </div>

                {{-- Contenedor del Folio Alfanumérico --}}
                <div class="w-full bg-acode-surface border border-slate-200 rounded-xl p-3 mb-4">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 block mb-1">
                        Folio alfanumérico oficial
                    </span>
                    <span
                        class="font-mono text-2xl font-bold tracking-widest text-acode-primary block select-all"
                        x-text="selectedGroup?.folio || '------'"
                    ></span>
                </div>

                {{-- Botón de Copiar folio con Toast institucional --}}
                <div class="flex items-center justify-center gap-3 w-full">
                    <x-button
                        type="button"
                        variant="accent"
                        size="md"
                        class="w-full"
                        @click="copyFolio(selectedGroup?.folio)"
                    >
                        <x-icon name="check" class="w-4 h-4 mr-1.5" x-show="copiedFolio" />
                        <x-icon name="copy" class="w-4 h-4 mr-1.5" x-show="!copiedFolio" />
                        <span x-text="copiedFolio ? '¡Folio copiado!' : 'Copiar folio'"></span>
                    </x-button>
                </div>
            </div>

            {{-- Pie de acciones con botón canónico Cerrar --}}
            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
                <x-button
                    type="button"
                    variant="neutral"
                    size="md"
                    @click="closeQr()"
                >
                    Cerrar
                </x-button>
            </div>
        </div>
    </div>

</div>
@endsection
