{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-section-header (MPW-V3 §7.3, §8.1, §8.10) --}}
@props([
    'title',
    'subtitle' => null,
    'actionLabel' => null,
    'actionHref' => null,
    'institutional' => false,
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-2']) }}>
    <div class="min-w-0">
        <h2 @class([
            'text-acode-primary tracking-tight',
            'text-xl leading-7 font-semibold' => $institutional,
            'text-base sm:text-lg font-bold' => ! $institutional,
        ])>
            {{ $title }}
        </h2>

        @if ($subtitle)
            <p @class(['mt-0.5 text-xs', 'text-slate-700' => $institutional, 'text-slate-500' => ! $institutional])>
                {{ $subtitle }}
            </p>
        @endif
    </div>

    @if (isset($actions) && $actions->isNotEmpty())
        <div class="flex max-w-full shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @elseif ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}"
           @class([
               'shrink-0 text-xs font-semibold text-acode-tech hover:underline',
               'inline-flex min-h-8 items-center rounded-lg px-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech' => $institutional,
           ])>
            {{ $actionLabel }}
        </a>
    @endif
</div>
