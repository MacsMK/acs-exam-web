@props(['grade'])

<span {{ $attributes->class(['inline-flex flex-wrap items-center justify-center gap-2']) }}>
    @if ($grade['score'] !== null)
        <span class="whitespace-nowrap font-mono text-sm font-semibold text-acode-primary">
            {{ number_format($grade['score'], 2) }} / {{ $grade['scaleMax'] }}
        </span>
    @endif

    <x-badge :variant="$grade['variant']" :class="$grade['variant'] === 'danger' ? '!text-acode-danger-dark' : ''">
        {{ $grade['label'] }}
    </x-badge>
</span>
