{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-form.textarea (MPW-V2 §7.3, §8.5, Tabla 29) --}}
@props([
    'name' => null,
    'rows' => 4,
    'mono' => false,
    'hasError' => false,
    'disabled' => false,
])

@php
    $isInvalid = $hasError || ($name && $errors->has($name));

    $borderClasses = $isInvalid
        ? 'border-acode-danger text-slate-900 focus:ring-2 focus:ring-acode-danger focus:border-acode-danger'
        : 'border-slate-300 text-slate-800 focus:ring-2 focus:ring-acode-tech focus:border-acode-tech';
@endphp

<textarea
    @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
    rows="{{ $rows }}"
    {{ $disabled ? 'disabled' : '' }}
    {{ $attributes->merge([
        'class' => 'w-full rounded-lg border px-3.5 py-2 text-sm bg-white placeholder-slate-400 transition-colors focus:outline-none disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed ' . ($mono ? 'font-mono ' : 'font-sans ') . $borderClasses
    ]) }}
>{{ $slot }}</textarea>
