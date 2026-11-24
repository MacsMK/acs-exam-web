{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-button (MPW-V3 §7.3, §7.8, §8.3, Tabla 15) --}}
@props([
    'variant' => 'primary', // primary, secondary, accent, warning, danger, neutral
    'size' => 'md',        // sm, md, lg (Tabla 15)
    'type' => 'submit',
    'href' => null,
    'loading' => false,
    'loadingWhen' => null, // Expresión Alpine para botones de formulario
    'disabled' => false,
    'iconOnly' => false,
])

@php
    $sizeClasses = match ($size) {
        'sm' => $iconOnly ? 'h-8 w-8 p-0 text-xs' : 'h-8 px-3 text-xs',
        'lg' => $iconOnly ? 'h-12 w-12 p-0 text-base' : 'h-12 px-6 text-base',
        default => $iconOnly ? 'h-10 w-10 p-0 text-sm' : 'h-10 px-4 text-sm',
    };

    $variantClasses = match ($variant) {
        'secondary' => 'bg-acode-tech text-white hover:bg-acode-tech-dark focus:ring-acode-tech',
        'accent' => 'bg-acode-accent text-white hover:opacity-90 focus:ring-acode-accent',
        'warning' => 'bg-acode-warning text-slate-900 hover:opacity-90 focus:ring-acode-warning',
        'danger' => 'bg-acode-danger text-white hover:bg-acode-danger-dark focus:ring-acode-danger',
        'neutral' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 focus:ring-acode-tech',
        default => 'bg-acode-primary text-white hover:bg-acode-primary-dark focus:ring-acode-tech',
    };

    // Los slots de texto directo necesitan gap; el slot label hace explícitos el icono y el texto.
    $hasLabel = isset($label) && $label->isNotEmpty();
    $spacingClass = $hasLabel && ! $loadingWhen ? 'space-x-2' : 'gap-2';
    $baseClasses = 'inline-flex items-center justify-center font-semibold rounded-lg shadow-sm transition-all duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed select-none ' . $spacingClass . ' ' . $sizeClasses . ' ' . $variantClasses;
@endphp

@if ($href || $attributes->has(':href'))
    <a @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $baseClasses]) }}>
        @if ($loading)
            <svg @class(['animate-spin h-4 w-4 shrink-0', '-ml-1 mr-2' => ! $hasLabel]) aria-hidden="true" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        @unless ($hasLabel && $loading)
            {{ $slot }}
        @endunless
        @if ($hasLabel)
            <span {{ $label->attributes }}>{{ $label }}</span>
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $disabled || $loading ? 'disabled' : '' }} {{ $attributes->merge(['class' => $baseClasses]) }}>
        @if ($loading || $loadingWhen)
            <svg
                @unless ($loading) x-show="{{ $loadingWhen }}" x-cloak @endunless
                @class(['animate-spin h-4 w-4 shrink-0', '-ml-1 mr-2' => $loading && ! $hasLabel])
                aria-hidden="true"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        @unless ($hasLabel && $loading)
            {{ $slot }}
        @endunless
        @if ($hasLabel)
            <span {{ $label->attributes }}>{{ $label }}</span>
        @endif
    </button>
@endif
