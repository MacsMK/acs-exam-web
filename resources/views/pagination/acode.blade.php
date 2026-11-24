@if ($paginator->hasPages())
    <nav aria-label="{{ $ariaLabel ?? 'Paginación' }}" class="flex flex-wrap items-center justify-end gap-1.5">
        <x-button
            :href="$paginator->previousPageUrl()"
            :disabled="$paginator->onFirstPage()"
            :rel="$paginator->onFirstPage() ? null : 'prev'"
            type="button"
            variant="neutral"
            size="sm"
        >
            Anterior
        </x-button>

        @foreach ($elements as $element)
            @if (is_string($element))
                <span aria-hidden="true" class="hidden px-1 text-sm text-slate-600 sm:inline">{{ $element }}</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span aria-current="page" class="inline-flex h-8 items-center gap-1 rounded-lg bg-acode-primary px-3 text-xs font-semibold text-white">
                            <span class="sr-only">Página </span>{{ $page }}
                            <span class="sm:sr-only">de {{ $paginator->lastPage() }}</span>
                        </span>
                    @else
                        <span class="hidden sm:inline-flex">
                            <x-button :href="$url" :aria-label="'Ir a la página ' . $page" variant="neutral" size="sm">
                                {{ $page }}
                            </x-button>
                        </span>
                    @endif
                @endforeach
            @endif
        @endforeach

        <x-button
            :href="$paginator->nextPageUrl()"
            :disabled="! $paginator->hasMorePages()"
            :rel="$paginator->hasMorePages() ? 'next' : null"
            type="button"
            variant="neutral"
            size="sm"
        >
            Siguiente
        </x-button>
    </nav>
@endif
