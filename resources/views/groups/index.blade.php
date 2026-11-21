{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/groups/index.blade.php
    Ubicación:   resources/views/groups/index.blade.php
    Descripción: Catálogo operativo de grupos escolares del docente con arquitectura
                 espacial homologada: encabezado institucional, 4 KPIs canónicos,
                 barra de búsqueda y filtrado con alternancia cuadrícula/lista,
                 tarjetas con folio copiable y modal QR (MPW-V2 §7.3, §7.4.2,
                 §7.8.2, §8.1–§8.12; MC-V2 §2.4).
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
        totalStudentsCount: {{ (int) $groups->sum('students_count') }},

        init() {
            setInterval(() => this.pollGroupsState(), 3000);
        },

        async pollGroupsState() {
            try {
                const res = await fetch(window.location.href, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!res.ok) return;
                const data = await res.json();
                if (!Array.isArray(data.groups)) return;

                this.groups = data.groups;
                this.totalStudentsCount = Number(data.total_students ?? this.totalStudentsCount);
            } catch (e) {
                // Reintenta en el siguiente ciclo
            }
        },

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
                    }
                }).catch(() => {});
            }
        }
    }"
    @keydown.escape.window="closeQr()"
    class="space-y-6"
>

    {{-- Nivel 1: Encabezado Institucional --}}
    <x-page-header
        title="Mis grupos escolares"
        subtitle="Gestiona tus aulas escolares, consulta folios de acceso, supervisa la matrícula y accede a las evaluaciones por periodo."
    >
        <x-slot:actions>
            <x-button :href="route('groups.create')" variant="primary" size="md">
                <x-icon name="add" class="w-4 h-4" />
                Crear grupo
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Nivel 2: Barra de 4 Tarjetas KPI Canónicas --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card
            label="Total de grupos"
            :value="$groups->count()"
            meta="Aulas escolares registradas"
            nav-icon="groups"
            tone="tech"
        />

        <x-kpi-card
            label="Grupos activos"
            :value="$groups->where('is_closed', false)->count()"
            meta="En ciclo lectivo vigente"
            icon="success"
            tone="accent"
        />

        <x-kpi-card
            label="Grupos cerrados"
            :value="$groups->where('is_closed', true)->count()"
            meta="Ciclos escolares concluidos"
            icon="lock"
            tone="neutral"
        />

        <x-kpi-card
            label="Estudiantes inscritos"
            meta="Censo total en tus aulas"
            icon="user"
            tone="primary"
        >
            <x-slot:valueSlot>
                <span x-text="totalStudentsCount">{{ $groups->sum('students_count') }}</span>
            </x-slot:valueSlot>
        </x-kpi-card>
    </div>

    {{-- Nivel 3: Barra de Búsqueda, Filtros de Estado y Alternador de Vista --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            {{-- Buscador predictivo multidimensional --}}
            <div class="w-full lg:max-w-md">
                <label for="group-search" class="sr-only">
                    Buscar grupo, materia o folio
                </label>
                <div class="relative">
                    <x-form.input
                        id="group-search"
                        type="text"
                        enterkeyhint="search"
                        autocomplete="off"
                        prefixIcon="search"
                        x-model="search"
                        placeholder="Buscar por grupo, asignatura o folio..."
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

            {{-- Filtros de estado y switch de vista --}}
            <div class="flex flex-wrap items-center justify-between lg:justify-end gap-3">
                <x-toggle-group
                    model="filter"
                    :options="['all' => 'Todos', 'active' => 'Activos', 'closed' => 'Cerrados']"
                    label="Filtrar grupos por estado"
                />

                <div class="hidden sm:block h-6 w-px bg-slate-200"></div>

                <x-toggle-group
                    model="viewMode"
                    variant="segmented"
                    :options="[
                        ['value' => 'grid', 'label' => 'Cuadrícula', 'icon' => 'squares2x2'],
                        ['value' => 'list', 'label' => 'Lista', 'icon' => 'listBullet'],
                    ]"
                    label="Cambiar vista de grupos"
                />
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
                <x-icon name="add" class="w-4 h-4" />
                Crear grupo
            </x-button>
        </x-empty-state>
    @else
        {{-- Cabecera de Resultados y Conteo --}}
        <x-section-header title="Grupos registrados">
            <x-slot:actions>
                <p class="text-xs sm:text-sm text-slate-500">
                    Mostrando
                    <span class="font-bold text-acode-primary" x-text="filteredGroups.length"></span>
                    de
                    <span class="font-bold text-slate-700">{{ $groups->count() }}</span>
                    {{ $groups->count() === 1 ? 'grupo' : 'grupos' }}
                </p>
            </x-slot:actions>
        </x-section-header>

        {{-- Nivel 4A: Cuadrícula de Tarjetas (Grid View) --}}
        <div
            x-show="filteredGroups.length > 0 && viewMode === 'grid'"
            class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3"
        >
            <template x-for="group in filteredGroups" :key="group.id">
                <article class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-acode-tech/40 hover:shadow-md">
                    <div>
                        {{-- Cabecera de tarjeta: Materia + Badge semántico de estado --}}
                        <div class="flex items-start justify-between gap-3">
                            <span
                                class="text-xs font-semibold uppercase tracking-wider text-acode-tech break-words"
                                x-text="group.subject || 'Sin asignatura'"
                            ></span>

                            <span
                                class="inline-flex items-center rounded-full text-xs px-2.5 py-0.5 font-semibold tracking-wide shrink-0"
                                :class="group.is_closed
                                    ? 'bg-slate-100 text-slate-700 border border-slate-200'
                                    : 'bg-acode-accent/15 text-acode-primary border border-acode-accent/40'"
                                x-text="group.is_closed ? 'Cerrado' : 'Activo'"
                            ></span>
                        </div>

                        {{-- Título del grupo escolar --}}
                        <h3
                            class="mt-3 break-words text-lg font-bold text-acode-primary leading-snug"
                            x-text="group.name"
                        ></h3>

                        {{-- Chip de folio de acceso alfanumérico con botón de copiado --}}
                        <div class="mt-4 flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-acode-surface/60 px-3 py-2">
                            <div class="flex items-center gap-1.5 overflow-hidden">
                                <span class="text-xs font-semibold text-slate-500 shrink-0">Folio:</span>
                                <span
                                    class="font-mono text-xs sm:text-sm font-bold tracking-wider text-acode-primary truncate select-all"
                                    x-text="group.folio || 'N/D'"
                                ></span>
                            </div>

                            <x-button
                                variant="neutral"
                                size="sm"
                                type="button"
                                x-on:click="copyFolio(group.folio)"
                                title="Copiar folio al portapapeles"
                            >
                                <x-icon name="copy" class="w-3.5 h-3.5" />
                                <span>Copiar</span>
                            </x-button>
                        </div>

                        {{-- Conteo de estudiantes --}}
                        <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3 text-xs text-slate-600">
                            <x-icon name="userGroup" class="w-4 h-4 text-acode-tech shrink-0" />
                            <span class="font-bold text-slate-800" x-text="group.students_count"></span>
                            <span x-text="group.students_count === 1 ? 'estudiante inscrito' : 'estudiantes inscritos'"></span>
                        </div>
                    </div>

                    {{-- Tríada de botones de acción canónicos --}}
                    <div class="mt-5 flex items-center gap-2 border-t border-slate-100 pt-4">
                        <x-button
                            ::href="group.url"
                            variant="primary"
                            size="sm"
                            class="flex-1"
                        >
                            Abrir grupo
                        </x-button>

                        <x-button
                            ::href="group.periods_url"
                            variant="neutral"
                            size="sm"
                        >
                            Abrir periodos
                        </x-button>

                        <x-button
                            type="button"
                            variant="neutral"
                            size="sm"
                            :icon-only="true"
                            @click="openQr(group)"
                            title="Abrir QR del grupo"
                            aria-label="Abrir QR del grupo"
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
                    <x-table.th align="center">Estudiantes inscritos</x-table.th>
                    <x-table.th align="center">Estado</x-table.th>
                    <x-table.th align="right">Acciones</x-table.th>
                </x-slot:header>

                <template x-for="group in filteredGroups" :key="group.id">
                    <tr>
                        <x-table.td>
                            <div class="font-bold text-acode-primary text-sm" x-text="group.name"></div>
                            <div class="text-xs font-semibold text-acode-tech mt-0.5" x-text="group.subject || 'Sin asignatura'"></div>
                        </x-table.td>

                        <x-table.td>
                            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-acode-surface/60 px-2.5 py-1">
                                <span class="font-mono text-xs font-bold text-acode-primary select-all" x-text="group.folio || 'N/D'"></span>
                                <x-button
                                    type="button"
                                    variant="neutral"
                                    size="sm"
                                    :icon-only="true"
                                    title="Copiar folio"
                                    aria-label="Copiar folio"
                                    x-on:click="copyFolio(group.folio)"
                                    class="!h-6 !w-6"
                                >
                                    <x-icon name="copy" class="w-3.5 h-3.5" />
                                </x-button>
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
                                class="inline-flex items-center rounded-full text-xs px-2.5 py-0.5 font-semibold tracking-wide"
                                :class="group.is_closed
                                    ? 'bg-slate-100 text-slate-700 border border-slate-200'
                                    : 'bg-acode-accent/15 text-acode-primary border border-acode-accent/40'"
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
                                    Abrir periodos
                                </x-button>

                                <x-button
                                    type="button"
                                    variant="neutral"
                                    size="sm"
                                    :icon-only="true"
                                    @click="openQr(group)"
                                    title="Abrir QR"
                                    aria-label="Abrir QR"
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
        <div x-show="filteredGroups.length === 0" x-cloak>
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

        {{-- Contenedor de la ventana modal --}}
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
            {{-- Encabezado --}}
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-b border-slate-100 bg-slate-50/70">
                <h3 id="qr-modal-title" class="text-base font-bold text-acode-primary tracking-tight truncate">
                    Código QR y Folio · <span x-text="selectedGroup?.name || 'Grupo escolar'"></span>
                </h3>
                <x-button
                    type="button"
                    variant="neutral"
                    size="sm"
                    :icon-only="true"
                    title="Cerrar ventana"
                    aria-label="Cerrar ventana"
                    x-on:click="closeQr()"
                >
                    <x-icon name="xMark" class="w-4 h-4" />
                </x-button>
            </div>

            {{-- Cuerpo del modal --}}
            <div class="px-6 py-5 text-sm text-slate-700 flex flex-col items-center text-center">
                <p class="text-xs text-slate-500 mb-4 max-w-xs">
                    Comparte este código QR o el folio alfanumérico con tus estudiantes para que se inscriban al grupo escolar.
                </p>

                <div class="p-3 bg-white border border-slate-200 rounded-2xl shadow-sm mb-4 inline-flex items-center justify-center">
                    <img
                        :src="'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(selectedGroup?.folio || '') + '&color=14-43-74&bgcolor=255-255-255&margin=1'"
                        :alt="'Código QR de acceso al grupo ' + (selectedGroup?.name || '')"
                        class="w-48 h-48 object-contain rounded-lg"
                        loading="lazy"
                    />
                </div>

                <div class="w-full bg-acode-surface border border-slate-200 rounded-xl p-3 mb-4">
                    <span class="text-xs font-semibold text-slate-500 block mb-1">
                        Folio alfanumérico oficial
                    </span>
                    <span
                        class="font-mono text-2xl font-bold tracking-widest text-acode-primary block select-all"
                        x-text="selectedGroup?.folio || '------'"
                    ></span>
                </div>

                <div class="flex items-center justify-center gap-3 w-full">
                    <x-button
                        type="button"
                        variant="accent"
                        size="md"
                        class="w-full"
                        @click="copyFolio(selectedGroup?.folio)"
                    >
                        <x-icon name="check" class="w-4 h-4" x-show="copiedFolio" />
                        <x-icon name="copy" class="w-4 h-4" x-show="!copiedFolio" />
                        <span x-text="copiedFolio ? '¡Folio copiado!' : 'Copiar folio'"></span>
                    </x-button>
                </div>
            </div>

            {{-- Pie de acciones --}}
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
