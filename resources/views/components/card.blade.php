{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-card (MPW-V2 §7.3, §8.3) --}}
@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 shadow-sm p-6']) }}>
    @if ($title || $subtitle)
        <div class="mb-4">
            @if ($title)
                <h3 class="text-base font-semibold text-acode-primary">{{ $title }}</h3>
            @endif
            @if ($subtitle)
                <p class="text-xs text-slate-500 mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
