{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-section-header (MPW-V3 §7.3, §8.1, §8.10) --}}
@props([
    'title',
    'subtitle' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-2']) }}>
    <div class="min-w-0">
        <h2 class="text-base sm:text-lg font-bold text-acode-primary tracking-tight">
            {{ $title }}
        </h2>

        @if ($subtitle)
            <p class="text-xs text-slate-500 mt-0.5">
                {{ $subtitle }}
            </p>
        @endif
    </div>

    @if (isset($actions) && $actions->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            {{ $actions }}
        </div>
    @elseif ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}"
           class="text-xs font-semibold text-acode-tech hover:underline shrink-0">
            {{ $actionLabel }}
        </a>
    @endif
</div>
