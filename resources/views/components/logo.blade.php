{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-logo (MPW-V2 §7.3, §8.1.2) --}}
{{-- Usa exclusivamente los archivos oficiales de public/images/branding; no dibujar el logotipo a mano. --}}
@props([
    'variant' => 'full', // 'full' (fondo claro), 'compact' / 'symbol' (isotipo), 'negative' (fondo oscuro)
    'showTagline' => true,
])

@php
    $archivo = match ($variant) {
        'compact', 'symbol' => 'isotipo.png',
        'negative' => 'logo-fondo-claro.png',
        default => $showTagline ? 'logo-eslogan.png' : 'logo-completo.png',
    };
    $clasePorDefecto = in_array($variant, ['compact', 'symbol'], true) ? 'h-8 w-8 shrink-0 object-contain' : 'h-10 w-auto object-contain';
@endphp

<img src="{{ asset('images/branding/'.$archivo) }}" alt="A-Code Solutions" {{ $attributes->merge(['class' => $clasePorDefecto]) }}>
