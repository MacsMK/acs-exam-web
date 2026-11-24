<div {{ $attributes->class(['space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5']) }}>
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1 basis-48 [&_input]:placeholder:!text-slate-500">{{ $field }}</div>
        <div class="flex max-w-full shrink-0 flex-wrap items-center gap-2">
            <x-button type="submit" variant="secondary">
                <x-icon name="search" class="h-4 w-4" />
                Buscar
            </x-button>
            {{ $actions ?? '' }}
        </div>
    </div>

    @isset($filters)
        <div class="flex flex-wrap items-end gap-3 pt-1 [&>*]:max-w-full">{{ $filters }}</div>
    @endisset

    {{ $feedback ?? '' }}
</div>
