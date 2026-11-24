@props([
    'label' => null,
    'value' => null,
    'note' => null,
    'icon' => null,
    'compact' => false,
])

@php($hasValue = isset($valueSlot) || $value !== null)

<div {{ $attributes->class(['flex min-w-0 flex-col rounded-lg border border-slate-200 bg-slate-50 p-3 sm:p-4']) }}>
    <dt @class([
        'break-words font-medium text-slate-700',
        'order-2 mt-2 text-sm leading-5' => ! $compact && $hasValue,
        'text-sm leading-5' => ! $compact && ! $hasValue,
        'text-xs leading-4' => $compact,
    ])>
        {{ $labelSlot ?? $label }}
    </dt>

    @if ($hasValue)
        <dd @class([
            'flex min-w-0 items-start justify-between gap-2 font-semibold text-acode-primary tabular-nums',
            'order-1 text-2xl leading-8 tracking-tight' => ! $compact,
            'mt-1 text-sm leading-5' => $compact,
        ])>
            <span class="min-w-0 break-words">{{ $valueSlot ?? $value }}</span>
            @if ($icon)
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-acode-surface text-acode-tech">
                    <x-icon :name="$icon" class="h-4 w-4" />
                </span>
            @endif
        </dd>
    @endif

    @if ($note || $slot->isNotEmpty())
        <dd class="order-3 mt-1 space-y-2 text-xs leading-4 text-slate-700">
            @if ($note)
                <p>{{ $note }}</p>
            @endif
            {{ $slot }}
        </dd>
    @endif
</div>
