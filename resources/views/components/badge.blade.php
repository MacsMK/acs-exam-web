{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-badge (MPW-V3 §7.3, §8.1.1, §8.6) --}}
@props([
    'variant' => 'neutral', // success/accent, warning, danger, tech/info, primary, neutral
])

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center rounded-full shadow-sm text-xs px-2.5 py-0.5 font-semibold tracking-wide ' . match ($variant) {
        'success', 'accent' => 'bg-acode-accent/15 text-acode-primary border border-acode-accent/40',
        'warning' => 'bg-acode-warning/25 text-acode-primary border border-acode-warning/50',
        'danger' => 'bg-acode-danger/10 text-acode-danger border border-acode-danger/30',
        'info', 'tech' => 'bg-acode-tech/15 text-acode-tech border border-acode-tech/30',
        'primary' => 'bg-acode-primary/10 text-acode-primary border border-acode-primary/25',
        default => 'bg-slate-100 text-slate-700 border border-slate-200',
    },
]) }}>
    {{ $slot }}
</span>
