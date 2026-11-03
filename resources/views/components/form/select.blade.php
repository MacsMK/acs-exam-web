{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-form.select (MPW-V2 §7.3, §8.3.4, §8.5, Tabla 28) --}}
@props([
    'name' => null,
    'size' => 'md', // sm, md, lg
    'hasError' => false,
    'disabled' => false,
])

@php
    $isInvalid = $hasError || ($name && $errors->has($name));

    $heightClass = match ($size) {
        'sm' => 'h-8 text-xs pl-3 pr-8',
        'lg' => 'h-12 text-base pl-4 pr-11',
        default => 'h-10 text-sm pl-3.5 pr-10',
    };

    $borderClasses = $isInvalid
        ? 'border-acode-danger text-slate-900 focus:ring-2 focus:ring-acode-danger focus:border-acode-danger'
        : 'border-slate-300 text-slate-800 focus:ring-2 focus:ring-acode-tech focus:border-acode-tech';
@endphp

<div class="relative w-full">
    <select
        @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
        {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->merge([
            'class' => 'w-full appearance-none rounded-lg border bg-white transition-colors focus:outline-none disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed ' . $heightClass . ' ' . $borderClasses
        ]) }}
    >
        {{ $slot }}
    </select>

    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500">
        <x-icon name="chevronDown" class="{{ $size === 'sm' ? 'w-4 h-4' : 'w-5 h-5' }}" />
    </div>
</div>
