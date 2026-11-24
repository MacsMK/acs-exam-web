{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-search-filter (MPW-V3 §7.8.2, §8.8, Tabla 10, Tabla 18) --}}
@props([
    'action' => request()->url(),
    'method' => 'GET',
    'searchName' => 'search',
    'searchValue' => request('search', ''),
    'searchLabel' => null,
    'placeholder' => 'Buscar reactivo, alumno o examen…',
    'clearUrl' => request()->url(),
    'clearKeys' => ['search', 'filter', 'type', 'group_id', 'subject', 'status', 'date'],
    'hasActiveFilters' => null,
    'autoSubmit' => false,
    'resultsId' => null,
    'liveClear' => false,
])

@php
    $activeClearKeys = array_values(array_unique(array_merge(
        [$searchName],
        is_array($clearKeys) ? $clearKeys : []
    )));
    $showClearAction = $hasActiveFilters !== null
        ? (bool) $hasActiveFilters
        : request()->anyFilled($activeClearKeys);
    $predictive = $resultsId && strcasecmp($method, 'GET') === 0;
    $clearFormId = $predictive && $liveClear ? $resultsId . '-clear' : null;
@endphp

<form
    method="{{ $method }}"
    action="{{ $action }}"
    x-data
    role="search"
    aria-label="{{ $searchLabel ?? $placeholder }}"
    @if ($predictive)
        data-catalog-search="{{ $resultsId }}"
        data-catalog-search-field="{{ $searchName }}"
    @elseif ($autoSubmit)
        x-on:input.debounce.300ms="if ($event.target.name === @js($searchName)) $el.submit()"
    @endif
    {{ $attributes }}
>
    {{ $parameters ?? '' }}

    <x-search-panel>
        <x-slot:field>
            <label for="{{ $searchName }}" class="sr-only">{{ $searchLabel ?? $placeholder }}</label>
            <x-form.input
                :name="$searchName"
                prefix-icon="search"
                :placeholder="$placeholder"
                :value="$searchValue"
            />
        </x-slot:field>

        <x-slot:actions>
            @if ($showClearAction || $predictive)
                <span @if ($predictive) data-catalog-clear @endif @unless ($showClearAction) hidden @endunless>
                    @if ($clearFormId)
                        <x-button type="submit" :form="$clearFormId" variant="secondary" :disabled="! $showClearAction">
                            Limpiar filtros
                        </x-button>
                    @else
                        <x-button :href="$clearUrl" variant="secondary">
                            Limpiar filtros
                        </x-button>
                    @endif
                </span>
            @endif
        </x-slot:actions>

        @isset($filters)
            <x-slot:filters>{{ $filters }}</x-slot:filters>
        @endisset

        @if ($predictive)
            <x-slot:feedback>
                <p class="sr-only text-xs font-medium text-acode-danger-dark" role="status" aria-atomic="true" data-catalog-status></p>
            </x-slot:feedback>
        @endif
    </x-search-panel>
</form>
@if ($clearFormId)
    <form id="{{ $clearFormId }}" method="GET" action="{{ $clearUrl }}" hidden></form>
@endif

@if ($predictive)
    @once
        @push('scripts')
            <script src="{{ asset('js/catalog-search.js') }}" defer></script>
        @endpush
    @endonce
@endif
