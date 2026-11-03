{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-logo (MPW-V2 §7.3, §8.1.2) --}}
@props([
    'variant' => 'full', // 'full', 'compact', 'symbol', 'negative'
    'showTagline' => true,
])

@if ($variant === 'compact' || $variant === 'symbol')
    {{-- Isotipo geométrico SVG aislado --}}
    <svg {{ $attributes->merge(['class' => 'w-8 h-8 shrink-0', 'viewBox' => '0 0 40 40', 'fill' => 'none', 'xmlns' => 'http://www.w3.org/2000/svg']) }}>
        <path d="M20 4L4 32H13.5L20 19.5L26.5 32H36L20 4Z" fill="#142B4A" />
        <path d="M20 19.5L13.5 32H26.5L20 19.5Z" fill="#2563A6" />
        <path d="M20 25L16.8 32H23.2L20 25Z" fill="#16A6A1" />
    </svg>
@elseif ($variant === 'negative')
    {{-- Versión Monocromática Negativa (#FFFFFF) para Navbar y fondos oscuros --}}
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 select-none']) }}>
        <svg class="w-8 h-8 shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 4L4 32H13.5L20 19.5L26.5 32H36L20 4Z" fill="#FFFFFF" />
            <path d="M20 19.5L13.5 32H26.5L20 19.5Z" fill="#FFFFFF" fill-opacity="0.75" />
            <path d="M20 25L16.8 32H23.2L20 25Z" fill="#FFFFFF" fill-opacity="0.45" />
        </svg>
        <div class="flex flex-col">
            <span class="font-bold text-base tracking-tight text-white leading-tight">A-Code Solutions</span>
            @if ($showTagline)
                <span class="text-[10px] text-white/70 font-normal tracking-wide">Analizamos, diseñamos, transformamos</span>
            @endif
        </div>
    </div>
@else
    {{-- Imagotipo completo horizontal (por defecto) --}}
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 select-none']) }}>
        <svg class="w-9 h-9 shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 4L4 32H13.5L20 19.5L26.5 32H36L20 4Z" fill="#142B4A" />
            <path d="M20 19.5L13.5 32H26.5L20 19.5Z" fill="#2563A6" />
            <path d="M20 25L16.8 32H23.2L20 25Z" fill="#16A6A1" />
        </svg>
        <div class="flex flex-col">
            <span class="font-bold text-lg tracking-tight text-acode-primary leading-tight">A-Code Solutions</span>
            @if ($showTagline)
                <span class="text-[10px] text-slate-500 font-medium tracking-wide">Analizamos, diseñamos, transformamos</span>
            @endif
        </div>
    </div>
@endif
