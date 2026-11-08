{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-badge (MPW-V2 §7.3, §8.1.1, §8.6) --}}
@props([
    'variant' => 'neutral', // success/accent, warning, danger, tech/info, primary, neutral
])

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center rounded-full text-xs px-2.5 py-0.5 font-medium tracking-wide ' . match ($variant) {
        'success', 'accent' => 'bg-acode-accent/15 text-acode-accent border border-acode-accent/30',
        'warning' => 'bg-acode-warning/25 text-slate-900 border border-acode-warning/40',
        'danger' => 'bg-acode-danger/10 text-acode-danger border border-acode-danger/30',
        'info', 'tech' => 'bg-acode-tech/15 text-acode-tech border border-acode-tech/30',
        'primary' => 'bg-acode-primary/10 text-acode-primary border border-acode-primary/20',
        default => 'bg-slate-100 text-slate-700 border border-slate-200',
    },
]) }}>
    {{ $slot }}
</span>
