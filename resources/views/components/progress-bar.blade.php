{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-progress-bar (MPW-V2 §7.3, §7.4.2, §8.1) --}}
{{-- Barra de progreso institucional: el avance se expresa con una clase de fracción de Tailwind (w-N/12), sin style="". --}}
@props([
    'value' => 0,          // 0 a 100
    'variant' => 'tech',   // primary, tech, accent, warning, danger
    'size' => 'md',        // sm (h-2), md (h-2.5), lg (h-3)
    'label' => null,       // texto accesible (aria-label)
])
@php
    $percent = max(0, min(100, (int) $value));
    $heightClass = match ($size) {
        'sm' => 'h-2',
        'lg' => 'h-3',
        default => 'h-2.5',
    };
    $fillClass = match ($variant) {
        'primary' => 'bg-acode-primary',
        'accent' => 'bg-acode-accent',
        'warning' => 'bg-acode-warning',
        'danger' => 'bg-acode-danger',
        default => 'bg-acode-tech',
    };
    // Las vistas usan Tailwind 3.4 (CDN), que solo trae fracciones de doce: el avance se redondea a 12 pasos.
    $steps = $percent === 0 ? 0 : max(1, (int) round($percent * 12 / 100));
    $widthClass = match (true) {
        $steps === 0 => 'w-0',
        $steps >= 12 => 'w-full',
        default => "w-{$steps}/12",
    };
@endphp
<div
    role="progressbar"
    aria-valuenow="{{ $percent }}"
    aria-valuemin="0"
    aria-valuemax="100"
    @if ($label) aria-label="{{ $label }}" @endif
    {{ $attributes->merge(['class' => 'w-full overflow-hidden rounded-full bg-slate-100 ' . $heightClass]) }}
>
    <div class="h-full rounded-full transition-all duration-500 {{ $fillClass }} {{ $widthClass }}"></div>
</div>
