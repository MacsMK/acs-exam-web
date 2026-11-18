{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-tab (MPW-V2 §7.3, §7.5, §8.3.2) --}}
{{-- Pestaña de un tablist enlazada a una variable de Alpine.js, con ícono y contador opcionales. --}}
@props([
    'name',                    // identificador de la pestaña
    'label',                   // texto visible
    'icon' => null,            // nombre del ícono de x-icon
    'count' => null,           // contador opcional
    'model' => 'activeTab',    // variable de Alpine.js con la pestaña activa
    'select' => null,          // expresión de Alpine.js al seleccionar (por omisión asigna el modelo)
])
@php
    $selectExpression = $select ?? ($model . ' = ' . \Illuminate\Support\Js::from($name));
@endphp
<button
    type="button"
    role="tab"
    @click="{{ $selectExpression }}"
    :aria-selected="({{ $model }} === @js($name)).toString()"
    :class="{{ $model }} === @js($name)
        ? 'border-acode-tech text-acode-primary font-bold'
        : 'border-transparent text-slate-500 hover:text-acode-primary font-medium'"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 border-b-3 px-4 py-3 text-sm transition whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-acode-tech']) }}
>
    @if ($icon)
        <x-icon :name="$icon" class="w-4 h-4 text-acode-tech" />
    @endif
    <span>{{ $label }}</span>
    @if (! is_null($count))
        <span
            class="rounded-full px-2 py-0.5 text-xs font-semibold"
            :class="{{ $model }} === @js($name) ? 'bg-acode-tech text-white' : 'bg-slate-100 text-slate-600'"
        >
            {{ $count }}
        </span>
    @endif
</button>
