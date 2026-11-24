{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-form.group (MPW-V3 §7.3, §8.5, §8.11) --}}
@props([
    'name' => null,
    'fieldId' => null,
    'label' => null,
    'required' => false,
    'helpText' => null,
    'error' => null,
    'accessible' => false,
])

@php($errorMessage = $error ?: ($name && $errors->has($name) ? $errors->first($name) : null))

<div {{ $attributes->merge(['class' => 'space-y-1.5 w-full']) }}>
    @if ($label)
        <label @if ($fieldId ?? $name) for="{{ $fieldId ?? $name }}" @endif class="block text-[13px] font-medium text-slate-800">
            {{ $label }}
            @if ($required)
                <span class="text-acode-danger font-bold ml-0.5" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($helpText)
        <p @if ($accessible && ($fieldId ?? $name)) id="{{ $fieldId ?? $name }}-help" @endif class="text-xs text-slate-600">{{ $helpText }}</p>
    @endif

    @if ($errorMessage)
        <p @if ($accessible && ($fieldId ?? $name)) id="{{ $fieldId ?? $name }}-error" @endif class="text-xs text-acode-danger-dark mt-1 flex items-center gap-1 font-medium">
            <x-icon name="error" class="w-3.5 h-3.5 shrink-0" />
            <span>{{ $errorMessage }}</span>
        </p>
    @endif
</div>
