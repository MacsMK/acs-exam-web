{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-page-header (MPW-V3 §7.3, §7.8.2, §8.1, §8.10) --}}
@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
])

<div {{ $attributes->merge(['class' => 'flex flex-col xl:flex-row xl:items-center justify-between gap-5 rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 shadow-sm']) }}>
    <div class="min-w-0 max-w-3xl">
        @if (! empty($breadcrumbs))
            <nav aria-label="Ruta de navegación" class="mb-2 flex flex-wrap items-center gap-1.5 text-xs font-medium text-slate-500">
                @foreach ($breadcrumbs as $crumb)
                    @php
                        $crumbLabel = is_array($crumb) ? ($crumb['label'] ?? '') : (string) $crumb;
                        $crumbHref = is_array($crumb) ? ($crumb['href'] ?? $crumb['url'] ?? null) : null;
                    @endphp

                    @if (! $loop->first)
                        <x-icon name="chevronRight" class="w-3.5 h-3.5 text-slate-400" />
                    @endif

                    @if ($crumbHref && ! $loop->last)
                        <a href="{{ $crumbHref }}" class="transition hover:text-acode-tech">
                            {{ $crumbLabel }}
                        </a>
                    @else
                        <span @if ($loop->last) aria-current="page" class="font-semibold text-acode-primary" @endif>
                            {{ $crumbLabel }}
                        </span>
                    @endif
                @endforeach
            </nav>
        @endif

        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="text-2xl sm:text-3xl font-bold text-acode-primary tracking-tight">
                {{ $title }}
            </h1>

            @if (isset($badge) && $badge->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2">
                    {{ $badge }}
                </div>
            @endif
        </div>

        @if ($subtitle)
            <p class="mt-2 text-sm text-slate-500 leading-relaxed">
                {{ $subtitle }}
            </p>
        @endif

        @if (isset($meta) && $meta->isNotEmpty())
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-500">
                {{ $meta }}
            </div>
        @endif
    </div>

    @if (isset($actions) && $actions->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            {{ $actions }}
        </div>
    @endif
</div>
