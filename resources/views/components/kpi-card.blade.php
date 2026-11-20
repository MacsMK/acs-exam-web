{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-kpi-card (MPW-V2 §7.3, §8.1.1, §8.2, MC-V2 §2.4) --}}
@props([
    'label',
    'value' => null,
    'meta' => null,
    'icon' => null,
    'navIcon' => null,
    'tone' => 'tech', // tech, accent, warning, danger, primary, neutral
    'href' => null,
    'mono' => false,
])

@php
    $toneConfig = match ($tone) {
        'accent' => [
            'iconBox' => 'bg-acode-accent/15 text-acode-accent',
            'hoverBorder' => 'hover:border-acode-accent',
            'hoverText' => 'group-hover:text-acode-accent',
            'valueColor' => 'text-acode-primary',
        ],
        'warning' => [
            'iconBox' => 'bg-acode-warning/20 text-acode-warning-dark',
            'hoverBorder' => 'hover:border-acode-warning',
            'hoverText' => 'group-hover:text-acode-warning-dark',
            'valueColor' => 'text-acode-primary',
        ],
        'danger' => [
            'iconBox' => 'bg-acode-danger/10 text-acode-danger',
            'hoverBorder' => 'hover:border-acode-danger',
            'hoverText' => 'group-hover:text-acode-danger',
            'valueColor' => 'text-acode-primary',
        ],
        'primary' => [
            'iconBox' => 'bg-acode-primary/10 text-acode-primary',
            'hoverBorder' => 'hover:border-acode-primary',
            'hoverText' => 'group-hover:text-acode-primary',
            'valueColor' => 'text-acode-primary',
        ],
        'neutral' => [
            'iconBox' => 'bg-slate-100 text-slate-600',
            'hoverBorder' => 'hover:border-slate-300',
            'hoverText' => 'group-hover:text-acode-primary',
            'valueColor' => 'text-acode-primary',
        ],
        default => [
            'iconBox' => 'bg-acode-surface text-acode-tech',
            'hoverBorder' => 'hover:border-acode-tech',
            'hoverText' => 'group-hover:text-acode-tech',
            'valueColor' => 'text-acode-primary',
        ],
    };

    $isInteractive = filled($href);
    $cardClasses = $isInteractive
        ? 'group flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md ' . $toneConfig['hoverBorder']
        : 'flex flex-col justify-between h-full rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm';

    $valueClasses = ($mono ? 'font-mono ' : '') . 'text-3xl lg:text-4xl font-extrabold tracking-tight tabular-nums ' . $toneConfig['valueColor'];
    $hasValueSlot = isset($valueSlot) && $valueSlot->isNotEmpty();
    $hasBodySlot = $slot->isNotEmpty();
@endphp

@if ($isInteractive)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cardClasses]) }}>
@else
    <div {{ $attributes->merge(['class' => $cardClasses]) }}>
@endif
        <div class="flex items-center gap-3">
            @if ($navIcon || $icon)
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $toneConfig['iconBox'] }} {{ $isInteractive ? 'transition-transform duration-200 group-hover:scale-105' : '' }}">
                    @if ($navIcon)
                        <x-nav-icon :name="$navIcon" class="h-6 w-6" />
                    @else
                        <x-icon :name="$icon" class="h-6 w-6" />
                    @endif
                </div>
            @endif

            <div class="flex min-w-0 items-center gap-1.5 text-sm font-semibold text-slate-600 {{ $isInteractive ? 'transition-colors duration-200 ' . $toneConfig['hoverText'] : '' }}">
                <span class="leading-snug">{{ $label }}</span>
                @if ($isInteractive)
                    <x-icon name="arrowUpRight" class="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                @endif
            </div>
        </div>

        @if ($value !== null || $hasValueSlot || $meta || $hasBodySlot)
            <div class="mt-4 space-y-2.5">
                @if ($value !== null || $hasValueSlot || $meta)
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        @if ($hasValueSlot)
                            <div class="{{ $valueClasses }}">
                                {{ $valueSlot }}
                            </div>
                        @elseif ($value !== null)
                            <p class="{{ $valueClasses }}">
                                {{ $value }}
                            </p>
                        @endif

                        @if ($meta)
                            <p class="text-xs text-slate-500">
                                {{ $meta }}
                            </p>
                        @endif
                    </div>
                @endif

                @if ($hasBodySlot)
                    <div>
                        {{ $slot }}
                    </div>
                @endif
            </div>
        @endif
@if ($isInteractive)
    </a>
@else
    </div>
@endif
