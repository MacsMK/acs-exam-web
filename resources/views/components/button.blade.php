{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-button (MPW-V2 §7.3, §7.8, §8.4) --}}
@props([
    'variant' => 'primary',
    'type' => 'submit',
    'href' => null,
])

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge([
        'class' => 'h-10 px-4 inline-flex items-center justify-center rounded-lg text-sm font-semibold transition focus:outline-none focus:ring-2 ' . match ($variant) {
            'secondary' => 'bg-acode-tech text-white hover:bg-acode-tech-dark focus:ring-acode-tech',
            'accent' => 'bg-acode-accent text-white hover:opacity-90 focus:ring-acode-accent',
            'danger' => 'bg-acode-danger text-white hover:opacity-90 focus:ring-acode-danger',
            'neutral' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 focus:ring-slate-300',
            default => 'bg-acode-primary text-white hover:bg-acode-primary-dark focus:ring-acode-tech',
        },
    ]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge([
        'class' => 'h-10 px-4 inline-flex items-center justify-center rounded-lg text-sm font-semibold transition focus:outline-none focus:ring-2 ' . match ($variant) {
            'secondary' => 'bg-acode-tech text-white hover:bg-acode-tech-dark focus:ring-acode-tech',
            'accent' => 'bg-acode-accent text-white hover:opacity-90 focus:ring-acode-accent',
            'danger' => 'bg-acode-danger text-white hover:opacity-90 focus:ring-acode-danger',
            'neutral' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 focus:ring-slate-300',
            default => 'bg-acode-primary text-white hover:bg-acode-primary-dark focus:ring-acode-tech',
        },
    ]) }}>
        {{ $slot }}
    </button>
@endif
