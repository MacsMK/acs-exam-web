{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-button (MPW-V2 §7.3, §7.8, §8.3, Tabla 27) --}}
@props([
    'variant' => 'primary', // primary, secondary, accent, warning, danger, neutral
    'size' => 'md',        // sm, md, lg (Tabla 27)
    'type' => 'submit',
    'href' => null,
    'loading' => false,
    'disabled' => false,
])

@php
    $sizeClasses = match ($size) {
        'sm' => 'h-8 px-3 text-xs',
        'lg' => 'h-12 px-6 text-base',
        default => 'h-10 px-4 text-sm',
    };

    $variantClasses = match ($variant) {
        'secondary' => 'bg-acode-tech text-white hover:bg-acode-tech-dark focus:ring-acode-tech',
        'accent' => 'bg-acode-accent text-white hover:opacity-90 focus:ring-acode-accent',
        'warning' => 'bg-acode-warning text-slate-900 hover:opacity-90 focus:ring-acode-warning',
        'danger' => 'bg-acode-danger text-white hover:opacity-90 focus:ring-acode-danger',
        'neutral' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 focus:ring-slate-300',
        default => 'bg-acode-primary text-white hover:bg-acode-primary-dark focus:ring-acode-tech',
    };

    $baseClasses = 'inline-flex items-center justify-center gap-2 font-semibold rounded-lg transition-all duration-200 ease-in-out hover:shadow-sm active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed select-none ' . $sizeClasses . ' ' . $variantClasses;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $baseClasses]) }}>
        @if ($loading)
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $disabled || $loading ? 'disabled' : '' }} {{ $attributes->merge(['class' => $baseClasses]) }}>
        @if ($loading)
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
