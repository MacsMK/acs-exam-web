{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-card (MPW-V3 §7.3, §8.1.1, §8.3) --}}
@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-6',
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 shadow-sm ' . $padding]) }}>
    @if ($title || $subtitle || isset($actions))
        <div class="flex items-start justify-between gap-4 mb-4 pb-3 border-b border-slate-100">
            <div>
                @if ($title)
                    <h3 class="text-base font-semibold text-acode-primary tracking-tight leading-6">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-slate-500 mt-0.5 leading-4">{{ $subtitle }}</p>
                @endif
            </div>
            @if (isset($actions))
                <div class="flex items-center gap-2 shrink-0">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <div class="card-body">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
            {{ $footer }}
        </div>
    @endif
</div>
