{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-icon-button (MPW-V2 §7.3, §8.3.2, §8.3.3, §8.12) --}}
{{-- Botón compuesto solo por un ícono. La etiqueta es obligatoria y se usa como aria-label y title (MPW-V2 §8.3.3). --}}
@props([
    'icon',                // nombre del ícono de x-icon
    'label',               // texto accesible obligatorio
    'variant' => 'muted',  // muted, tech
    'size' => 'md',        // sm, md, lg
    'type' => 'button',
])
@php
    $iconClass = match ($size) {
        'sm' => 'w-3.5 h-3.5',
        'lg' => 'w-5 h-5',
        default => 'w-4 h-4',
    };
    $variantClasses = match ($variant) {
        'tech' => 'text-slate-400 hover:text-acode-tech',
        default => 'text-slate-400 hover:text-slate-600',
    };
@endphp
<button
    type="{{ $type }}"
    aria-label="{{ $label }}"
    title="{{ $label }}"
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-lg p-1 transition focus:outline-none focus:ring-2 focus:ring-acode-tech ' . $variantClasses]) }}
>
    <x-icon :name="$icon" :class="$iconClass" />
</button>
