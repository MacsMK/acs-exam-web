{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-table.td (MPW-V2 §7.3, §8.4) --}}
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

<td {{ $attributes->merge(['class' => 'px-4 py-3 text-sm text-slate-700 ' . $alignClass]) }}>
    {{ $slot }}
</td>
