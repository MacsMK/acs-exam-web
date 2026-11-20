{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-toggle-group (MPW-V2 §7.3, §7.5, §8.3.2, §8.8) --}}
{{-- Grupo de opciones excluyentes enlazado a una variable de Alpine.js (filtros por estado o cambio de vista). --}}
@props([
    'model',                 // nombre de la variable de Alpine.js que guarda la opción seleccionada
    'options' => [],         // [valor => etiqueta] o [['value' => ..., 'label' => ..., 'icon' => ...], ...]
    'variant' => 'pills',    // pills (filtros) o segmented (cambio de vista)
    'label' => null,         // texto accesible del grupo
    'select' => null,        // opcional: función de Alpine.js que recibe el valor (p. ej. setViewMode) en lugar de asignar directo
])
@php
    $items = collect($options)->map(function ($option, $key): array {
        return is_array($option)
            ? ['value' => $option['value'], 'label' => $option['label'], 'icon' => $option['icon'] ?? null]
            : ['value' => $key, 'label' => $option, 'icon' => null];
    })->values();

    $isSegmented = $variant === 'segmented';
    $wrapperClass = $isSegmented
        ? 'flex items-center rounded-lg border border-slate-200 bg-slate-50 p-0.5'
        : 'flex items-center gap-1.5';
    $itemClass = $isSegmented
        ? 'inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-xs font-medium transition'
        : 'h-9 rounded-lg px-3.5 text-xs sm:text-sm font-semibold transition';
    $activeClass = $isSegmented
        ? 'bg-white text-acode-primary shadow-sm font-semibold'
        : 'bg-acode-primary text-white shadow-sm';
    $inactiveClass = $isSegmented
        ? 'text-slate-500 hover:text-slate-700'
        : 'bg-slate-100 text-slate-600 hover:bg-slate-200';
@endphp
<div role="group" @if ($label) aria-label="{{ $label }}" @endif {{ $attributes->merge(['class' => $wrapperClass]) }}>
    @foreach ($items as $item)
        <button
            type="button"
            @click="{{ $select ? $select . '(' . \Illuminate\Support\Js::from($item['value']) . ')' : $model . ' = ' . \Illuminate\Support\Js::from($item['value']) }}"
            :class="{{ $model }} === @js($item['value']) ? @js($activeClass) : @js($inactiveClass)"
            :aria-pressed="({{ $model }} === @js($item['value'])).toString()"
            @if ($item['icon']) title="{{ $item['label'] }}" @endif
            class="{{ $itemClass }} focus:outline-none focus:ring-2 focus:ring-acode-tech"
        >
            @if ($item['icon'])
                <x-icon :name="$item['icon']" class="w-4 h-4" />
                <span class="hidden sm:inline">{{ $item['label'] }}</span>
            @else
                {{ $item['label'] }}
            @endif
        </button>
    @endforeach
</div>
