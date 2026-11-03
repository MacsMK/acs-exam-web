{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-empty-state (MPW-V2 §5.2.2, §7.3, §8.1.1) --}}
@props([
    'icon' => 'search',
    'title' => null,
    'message' => 'No hay registros disponibles.',
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-center flex flex-col items-center justify-center']) }}>
    @if ($icon)
        <div class="w-12 h-12 rounded-full bg-acode-surface flex items-center justify-center text-acode-tech mb-3">
            <x-icon :name="$icon" class="w-6 h-6" />
        </div>
    @endif

    @if ($title)
        <h3 class="text-base font-semibold text-acode-primary mb-1">{{ $title }}</h3>
    @endif

    <p class="text-sm text-slate-500 max-w-sm">{{ $message }}</p>

    @if (! $slot->isEmpty())
        <div class="mt-4 flex items-center justify-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
