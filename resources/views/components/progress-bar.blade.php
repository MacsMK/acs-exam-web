{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-progress-bar (MPW-V3 §7.3, §7.4.2, §8.1) --}}
@props([
    'value' => 0,
    'max' => 100,
    'variant' => 'tech',
    'size' => 'md',
    'label' => null,
])
@php
    $maximum = $max > 0 ? (float) $max : 100;
    $currentValue = max(0, min($maximum, (float) $value));
    $heightClass = match ($size) {
        'sm' => 'h-2',
        'lg' => 'h-3',
        default => 'h-2.5',
    };
    $fillClass = match ($variant) {
        'primary' => '[&::-webkit-progress-value]:bg-acode-primary [&::-moz-progress-bar]:bg-acode-primary',
        'accent' => '[&::-webkit-progress-value]:bg-acode-accent [&::-moz-progress-bar]:bg-acode-accent',
        'warning' => '[&::-webkit-progress-value]:bg-acode-warning [&::-moz-progress-bar]:bg-acode-warning',
        'danger' => '[&::-webkit-progress-value]:bg-acode-danger [&::-moz-progress-bar]:bg-acode-danger',
        default => '[&::-webkit-progress-value]:bg-acode-tech [&::-moz-progress-bar]:bg-acode-tech',
    };
@endphp
<progress
    value="{{ $currentValue }}"
    max="{{ $maximum }}"
    @if ($label) aria-label="{{ $label }}" @endif
    {{ $attributes->class([
        'block w-full appearance-none overflow-hidden rounded-full border-0 bg-slate-100',
        '[&::-webkit-progress-bar]:bg-slate-100 [&::-webkit-progress-value]:rounded-full [&::-moz-progress-bar]:rounded-full',
        $heightClass,
        $fillClass,
    ]) }}
></progress>
