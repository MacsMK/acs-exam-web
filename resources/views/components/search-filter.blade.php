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

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-6']) }}>
    <form
        method="{{ $method }}"
        action="{{ $action }}"
        x-data="{
            searchQuery: @js($searchValue),
        }"
        x-ref="searchForm"
        class="space-y-3"
    >
        @php
            $activeClearKeys = array_values(array_unique(array_merge(
                [$searchName],
                is_array($clearKeys) ? $clearKeys : []
            )));
            $showClearAction = $hasActiveFilters !== null
                ? (bool) $hasActiveFilters
                : request()->anyFilled($activeClearKeys);
        @endphp

        {{-- Fila principal: campo de búsqueda predictiva (ancho completo disponible) + botones Buscar / Limpiar --}}
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <div class="relative flex-1 min-w-0">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
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
                    class="w-full h-10 pl-10 pr-3.5 rounded-lg border border-slate-300 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-acode-tech focus:border-acode-tech bg-white placeholder-slate-400"
                />
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <x-button type="submit" variant="secondary" size="md">
                    <x-icon name="search" class="w-4 h-4" />
                    Buscar
                </x-button>

                @if ($showClearAction)
                    <x-button href="{{ $clearUrl }}" variant="neutral" size="md">
                        <x-icon name="filter" class="w-4 h-4 text-slate-500" />
                        Limpiar filtros
                    </x-button>
                @endif
            </div>
        </div>

        {{-- Fila secundaria de filtros desplegables para no comprimir el buscador --}}
        @if (isset($filters))
            <div class="flex flex-wrap items-center gap-3 pt-1">
                {{ $filters }}
            </div>
        @endif
    </form>
</div>
