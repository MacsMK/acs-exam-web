{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-empty-state (MPW-V2 §5.2.2, §7.3) --}}
@props([
    'title' => null,
    'message' => 'No hay registros disponibles.',
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-center']) }}>
    @if ($title)
        <p class="text-sm font-semibold text-acode-primary mb-1">{{ $title }}</p>
    @endif
    <p class="text-sm text-slate-500">{{ $message }}</p>
    @if (! $slot->isEmpty())
        <div class="mt-3 text-sm text-slate-500">
            {{ $slot }}
        </div>
    @endif
</div>
