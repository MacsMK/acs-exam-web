{{--
    ============================================================================
    A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
    ============================================================================
    Archivo:     resources/views/groups/index.blade.php
    Ubicación:   resources/views/groups/index.blade.php
    Descripción: Catálogo docente con búsqueda, filtros, cuadrícula y tabla de
                 grupos, acciones de inscripción y resumen de matrículas.
    ============================================================================
--}}
@extends('layouts.app')

@section('title', 'Mis grupos escolares')

@section('content')
@php
    $catalogGroups = $groups->map(fn ($group) => [
        'id' => $group->id,
        'name' => $group->name,
        'subject' => filled($group->subject) ? $group->subject : 'Sin asignatura',
        'students_count' => (int) $group->students_count,
        'is_closed' => (bool) $group->is_closed,
        'folio' => $group->access_folio,
        'url' => route('groups.show', $group),
        'periods_url' => route('groups.periods.index', $group),
    ])->values();
    $subjects = $catalogGroups->pluck('subject')->unique()->sort();
    $groupCount = $catalogGroups->count();
    $activeGroupCount = $catalogGroups->where('is_closed', false)->count();
@endphp

<div
    x-data="{
        searchInput: '',
        search: '',
        filter: 'all',
        subjectFilter: '',
        viewMode: 'grid',
        copyResult: null,
        groups: @js($catalogGroups),

        normalize(value) {
            return (value || '').normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase('es').trim();
        },

        get filteredGroups() {
            const term = this.normalize(this.search);

            return this.groups
                .filter(group => {
                    const matchesSearch = !term || [group.name, group.subject, group.folio]
                        .some(value => this.normalize(value).includes(term));
                    const matchesSubject = !this.subjectFilter || group.subject === this.subjectFilter;
                    const matchesStatus = this.filter === 'all' || group.is_closed === (this.filter === 'closed');

                    return matchesSearch && matchesSubject && matchesStatus;
                })
                .sort((a, b) => Number(a.is_closed) - Number(b.is_closed) || a.name.localeCompare(b.name, 'es'));
        },

        clearFilters() {
            this.searchInput = '';
            this.search = '';
            this.filter = 'all';
            this.subjectFilter = '';
            this.$nextTick(() => this.$refs.groupSearch.focus());
        },

        async copyFolio(group) {
            this.copyResult = null;
            let copied = false;
            try {
                copied = await window.copyGroupFolio?.(group.folio);
            } catch {
                copied = false;
            }

            this.copyResult = {
                groupId: group.id,
                message: copied
                    ? 'Folio de ' + group.name + ' copiado al portapapeles.'
                    : 'No se pudo copiar el folio. Selecciónalo para copiarlo manualmente.',
            };

            if (copied) window.AcodeAlert?.toast(this.copyResult.message, 'success');
        },
    }"
    class="space-y-6"
>
    <x-page-header title="Mis grupos escolares" :institutional="true">
        <x-slot:actions>
            <x-button :href="route('groups.create')" variant="primary" size="md">
                <x-icon name="add" class="h-4 w-4" />
                Crear grupo
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($catalogGroups->isEmpty())
        <x-empty-state
            icon="userGroup"
            title="Todavía no tienes grupos escolares"
            message="Crea tu primer grupo para inscribir estudiantes y gestionar sus periodos de evaluación."
        />
    @else
        <section class="space-y-3" aria-label="Catálogo de grupos">
            <form role="search" aria-label="Grupos" x-on:submit.prevent="search = searchInput">
                <x-search-panel>
                    <x-slot:field>
                        <x-form.input
                            id="group-search"
                            type="search"
                            prefix-icon="search"
                            enterkeyhint="search"
                            autocomplete="off"
                            aria-label="Buscar grupos"
                            x-ref="groupSearch"
                            x-model="searchInput"
                            @input.debounce.300ms="search = searchInput"
                            placeholder="Buscar por nombre, asignatura o folio…"
                        />
                    </x-slot:field>

                    <x-slot:actions>
                        <x-button
                            type="button"
                            variant="secondary"
                            x-show="searchInput !== '' || filter !== 'all' || subjectFilter"
                            x-cloak
                            @click="clearFilters()"
                        >
                            Limpiar filtros
                        </x-button>
                    </x-slot:actions>

                    <x-slot:filters>
                        <x-form.group field-id="group-status" label="Estado" class="sm:w-44">
                            <x-form.select id="group-status" size="sm" x-model="filter">
                                <option value="all">Todos</option>
                                <option value="active">Activos</option>
                                <option value="closed">Cerrados</option>
                            </x-form.select>
                        </x-form.group>

                        <x-form.group field-id="group-subject" label="Asignatura" class="sm:w-48">
                            <x-form.select id="group-subject" size="sm" x-model="subjectFilter">
                                <option value="">Todas</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject }}">{{ $subject }}</option>
                                @endforeach
                            </x-form.select>
                        </x-form.group>
                    </x-slot:filters>
                </x-search-panel>
            </form>

            <x-section-header title="Grupos registrados" :institutional="true">
                <x-slot:actions>
                    <p class="text-sm text-slate-700" role="status" aria-atomic="true">
                        Mostrando
                        <span class="font-semibold text-acode-primary" x-text="filteredGroups.length">{{ $groupCount }}</span>
                        de {{ $groupCount }} {{ $groupCount === 1 ? 'grupo' : 'grupos' }}
                    </p>

                    <x-toggle-group
                        model="viewMode"
                        variant="segmented"
                        :options="[
                            ['value' => 'grid', 'label' => 'Cuadrícula', 'icon' => 'squares2x2'],
                            ['value' => 'list', 'label' => 'Lista', 'icon' => 'listBullet'],
                        ]"
                        label="Cambiar vista de grupos"
                    />
                </x-slot:actions>
            </x-section-header>

            <p class="sr-only" role="status" aria-atomic="true" x-text="copyResult?.message || ''"></p>

            <div
                x-show="filteredGroups.length > 0 && viewMode === 'grid'"
                class="grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-3"
            >
                <template x-for="group in filteredGroups" :key="group.id">
                    <x-card class="min-w-0 transition hover:border-acode-tech hover:shadow-md" padding="p-4">
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="break-words text-xs font-semibold uppercase tracking-wider text-acode-tech"
                                      x-text="group.subject"></span>
                                <x-badge class="shrink-0" variant="accent" x-show="!group.is_closed">Activo</x-badge>
                                <x-badge class="shrink-0" variant="neutral" x-show="group.is_closed">Cerrado</x-badge>
                            </div>
                            <h3 class="break-words text-base font-semibold leading-6 text-acode-primary"
                                x-text="group.name"></h3>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 pt-3 text-xs text-slate-700">
                            <span class="font-mono font-semibold">
                                Folio: <span class="select-all" x-text="group.folio || 'No disponible'"></span>
                            </span>
                            <span x-text="group.students_count + (group.students_count === 1 ? ' estudiante inscrito' : ' estudiantes inscritos')"></span>
                        </div>

                        <div class="mt-2">
                            @include('groups.partials._catalog-actions')
                        </div>
                    </x-card>
                </template>
            </div>

            <div x-show="filteredGroups.length > 0 && viewMode === 'list'" x-cloak>
                <p class="mb-2 text-xs text-slate-700 sm:hidden">Desliza la tabla para ver todas las columnas.</p>
                <x-table zebra="slate" scroll-label="Grupos registrados">
                    <x-slot:header>
                        <x-table.th align="center">Grupo</x-table.th>
                        <x-table.th align="center">Folio de acceso</x-table.th>
                        <x-table.th align="center">Estudiantes inscritos</x-table.th>
                        <x-table.th align="center">Estado</x-table.th>
                        <x-table.th align="center">Acciones</x-table.th>
                    </x-slot:header>

                    <template x-for="group in filteredGroups" :key="group.id">
                        <tr>
                            <th scope="row" class="min-w-40 px-4 py-3 text-left text-sm">
                                <div class="font-semibold text-acode-primary" x-text="group.name"></div>
                                <div class="mt-0.5 text-xs font-normal text-slate-700" x-text="group.subject"></div>
                            </th>
                            <x-table.td>
                                <span class="select-all whitespace-nowrap font-mono text-xs font-semibold text-acode-primary"
                                      x-text="group.folio || 'No disponible'"></span>
                            </x-table.td>
                            <x-table.td align="center">
                                <span x-text="group.students_count"></span>
                            </x-table.td>
                            <x-table.td align="center">
                                <x-badge variant="accent" x-show="!group.is_closed">Activo</x-badge>
                                <x-badge variant="neutral" x-show="group.is_closed">Cerrado</x-badge>
                            </x-table.td>
                            <x-table.td>
                                @include('groups.partials._catalog-actions')
                            </x-table.td>
                        </tr>
                    </template>
                </x-table>
            </div>

            <div x-show="filteredGroups.length === 0" x-cloak>
                <x-empty-state
                    icon="search"
                    title="No hay grupos que coincidan"
                    message="Cambia la búsqueda o limpia los filtros para ver otros grupos."
                />
            </div>
        </section>

        <x-catalog-summary title="Resumen de grupos">
            <x-summary-item label="Total de grupos" :value="$groupCount" icon="squares2x2" />
            <x-summary-item label="Grupos activos" :value="$activeGroupCount" icon="success" />
            <x-summary-item label="Grupos cerrados" :value="$groupCount - $activeGroupCount" icon="lock" />
            <x-summary-item label="Matrículas" :value="$groups->sum('students_count')" icon="userGroup" />
        </x-catalog-summary>

        <x-group.qr-modal />
    @endif
</div>
@endsection
