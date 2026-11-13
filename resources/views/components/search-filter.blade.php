{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-search-filter (MPW-V2 §7.8.2, §8.8, Tabla 20, Tabla 28) --}}
@props([
    'action' => request()->url(),
    'method' => 'GET',
    'searchName' => 'search',
    'searchValue' => request('search', ''),
    'placeholder' => 'Buscar reactivo, alumno o examen…',
    'clearUrl' => request()->url(),
    'clearKeys' => ['search', 'filter', 'type', 'group_id', 'subject', 'status', 'date'],
    'hasActiveFilters' => null,
    'autoSubmit' => false,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6']) }}>
    <form
        method="{{ $method }}"
        action="{{ $action }}"
        x-data="{
            searchQuery: @js($searchValue),
        }"
        x-ref="searchForm"
        class="flex flex-col md:flex-row items-stretch md:items-center gap-3"
    >
        {{-- Campo de búsqueda predictiva con debounce de 300 ms e icono integrado --}}
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <x-icon name="search" class="w-5 h-5" />
            </div>
            <input
                type="text"
                name="{{ $searchName }}"
                x-model="searchQuery"
                @if ($autoSubmit)
                    @input.debounce.300ms="$refs.searchForm.submit()"
                @endif
                placeholder="{{ $placeholder }}"
                value="{{ $searchValue }}"
                class="w-full h-10 pl-10 pr-4 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech bg-white placeholder-slate-400"
            />
        </div>

        {{-- Slot para selectores de filtrado múltiple (materia, grupo, fecha, etc.) --}}
        @if (isset($filters))
            <div class="flex flex-wrap items-center gap-3">
                {{ $filters }}
            </div>
        @endif

        {{-- Botones de acción normativa (18 verbos oficiales: Buscar y Limpiar) --}}
        <div class="flex items-center gap-2 shrink-0">
            <x-button type="submit" variant="secondary" size="md">
                <x-icon name="search" class="w-4 h-4 mr-1.5" />
                Buscar
            </x-button>

            @php
                $activeClearKeys = array_values(array_unique(array_merge(
                    [$searchName],
                    is_array($clearKeys) ? $clearKeys : []
                )));
                $showClearAction = $hasActiveFilters !== null
                    ? (bool) $hasActiveFilters
                    : request()->anyFilled($activeClearKeys);
            @endphp

            @if ($showClearAction)
                <x-button href="{{ $clearUrl }}" variant="neutral" size="md">
                    <x-icon name="filter" class="w-4 h-4 mr-1.5 text-slate-400" />
                    Limpiar filtros
                </x-button>
            @endif
        </div>
    </form>
</div>
