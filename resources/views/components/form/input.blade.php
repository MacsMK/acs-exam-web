{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-form.input (MPW-V3 §7.3, §8.3.4, §8.5, Tabla 18) --}}
@props([
    'name' => null,
    'id' => null,
    'type' => 'text',
    'numeric' => null,
    'size' => 'md', // sm (32px), md (40px), lg (48px)
    'prefixIcon' => null,
    'suffixIcon' => null,
    'hasError' => false,
    'disabled' => false,
    'accessible' => false,
])

@php
    $isInvalid = $hasError || ($name && $errors->has($name));
    $numericPattern = match ($numeric) {
        'integer' => '[0-9]+',
        'decimal' => '[0-9]+([.,][0-9]{1,2})?',
        default => null,
    };
    $inputAttributes = $attributes;
    if ($numericPattern !== null && $attributes->has('value')) {
        $inputAttributes = $attributes->except('value')->merge([
            'value' => \App\Support\AcademicNumber::forInput($attributes->get('value')),
        ]);
    }

    $heightClass = match ($size) {
        'sm' => 'h-8 text-xs',
        'lg' => 'h-12 text-base',
        default => 'h-10 text-sm',
    };

    $paddingClass = match ($size) {
        'sm' => ($prefixIcon ? 'pl-8 ' : 'px-3 ') . ($suffixIcon ? 'pr-8' : 'px-3'),
        'lg' => ($prefixIcon ? 'pl-11 ' : 'px-4 ') . ($suffixIcon ? 'pr-11' : 'px-4'),
        default => ($prefixIcon ? 'pl-10 ' : 'px-3.5 ') . ($suffixIcon ? 'pr-10' : 'px-3.5'),
    };

    $normalBorderClass = $accessible ? 'border-slate-500' : 'border-slate-300';
    $placeholderClass = $accessible ? 'placeholder-slate-500' : 'placeholder-slate-400';
    $borderClasses = $isInvalid
        ? 'border-acode-danger text-slate-900 focus:ring-2 focus:ring-acode-danger focus:border-acode-danger'
        : $normalBorderClass . ' text-slate-800 focus:ring-2 focus:ring-acode-tech focus:border-acode-tech';
@endphp

<div class="relative w-full">
    @if ($prefixIcon)
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <x-icon :name="$prefixIcon" class="{{ $size === 'sm' ? 'w-4 h-4' : ($size === 'lg' ? 'w-6 h-6' : 'w-5 h-5') }}" />
        </div>
    @endif

    <input
        type="{{ $numericPattern === null ? $type : 'text' }}"
        @if ($numericPattern !== null)
            data-numeric="{{ $numeric }}"
            inputmode="{{ $numeric === 'integer' ? 'numeric' : 'decimal' }}"
            pattern="{{ $numericPattern }}"
            x-init="$nextTick(() => window.academicNumber?.validate($el))"
            x-on:beforeinput="window.academicNumber?.guard($event)"
            x-on:paste="window.academicNumber?.guardPaste($event)"
            x-on:input="window.academicNumber?.validate($el)"
        @endif
        @if ($name) name="{{ $name }}" @endif
        @if ($id ?? $name) id="{{ $id ?? $name }}" @endif
        {{ $disabled ? 'disabled' : '' }}
        {{ $inputAttributes->merge([
            'aria-invalid' => $accessible && $isInvalid ? 'true' : null,
            'class' => 'w-full rounded-lg border bg-white transition-colors focus:outline-none disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed ' . $heightClass . ' ' . $paddingClass . ' ' . $borderClasses . ' ' . $placeholderClass,
        ]) }}
    />

    @if ($suffixIcon)
        <div class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400">
            @if (is_string($suffixIcon))
                <x-icon :name="$suffixIcon" class="{{ $size === 'sm' ? 'w-4 h-4' : ($size === 'lg' ? 'w-6 h-6' : 'w-5 h-5') }}" />
            @else
                {{ $suffixIcon }}
            @endif
        </div>
    @endif
</div>

@if ($numericPattern !== null)
    @once
        @push('scripts')
            <script src="{{ asset('js/academic-number.js') }}"></script>
        @endpush
    @endonce
@endif
