@props(['title'])

<section aria-label="{{ $title }}" {{ $attributes }}>
    <x-card padding="p-4 sm:p-5">
        <x-section-header :title="$title" institutional class="mb-4">
            @isset($actions)
                <x-slot:actions>{{ $actions }}</x-slot:actions>
            @endisset
        </x-section-header>

        <x-summary-list>
            {{ $slot }}
        </x-summary-list>
    </x-card>
</section>
