{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-badge (MPW-V2 §7.3, §8.6) --}}
@props([
    'variant' => 'neutral',
])

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center rounded-full text-xs px-2.5 py-0.5 font-medium ' . match ($variant) {
        'success', 'accent' => 'bg-acode-accent/15 text-acode-accent',
        'warning' => 'bg-acode-warning/20 text-acode-primary',
        'danger' => 'bg-acode-danger/10 text-acode-danger',
        'info', 'tech' => 'bg-acode-tech/15 text-acode-tech',
        'primary' => 'bg-acode-primary/10 text-acode-primary',
        default => 'bg-slate-200 text-slate-600',
    },
]) }}>
    {{ $slot }}
</span>
