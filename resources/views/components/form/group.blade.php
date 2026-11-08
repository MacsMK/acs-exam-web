{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-form.group (MPW-V2 §7.3, §8.5, §8.11) --}}
@props([
    'name' => null,
    'label' => null,
    'required' => false,
    'helpText' => null,
    'error' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-1.5 w-full']) }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif class="block text-[13px] font-medium text-slate-800">
            {{ $label }}
            @if ($required)
                <span class="text-acode-danger font-bold ml-0.5" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($helpText)
        <p class="text-xs text-slate-500">{{ $helpText }}</p>
    @endif

    @if ($error)
        <p class="text-xs text-acode-danger mt-1 flex items-center gap-1 font-medium">
            <x-icon name="error" class="w-3.5 h-3.5 shrink-0" />
            <span>{{ $error }}</span>
        </p>
    @elseif ($name && $errors->has($name))
        <p class="text-xs text-acode-danger mt-1 flex items-center gap-1 font-medium">
            <x-icon name="error" class="w-3.5 h-3.5 shrink-0" />
            <span>{{ $errors->first($name) }}</span>
        </p>
    @endif
</div>
