{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-table.th (MPW-V2 §7.3, §8.4) --}}
@props([
    'align' => 'left', // left, center, right
])

@php
    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };
@endphp

<th scope="col" {{ $attributes->merge(['class' => 'px-4 py-3 font-semibold text-xs tracking-wider uppercase text-white select-none ' . $alignClass]) }}>
    {{ $slot }}
</th>
