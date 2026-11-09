@props(['name'])

@php
    $paths = [
        'home' => [
            '<path d="m3 10 9-7 9 7"/>',
            '<path d="M5 9v12h14V9"/>',
            '<path d="M9 21v-8h6v8"/>',
        ],
        'groups' => [
            '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>',
            '<circle cx="9" cy="7" r="4"/>',
            '<path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
            '<path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        ],
        'questions' => [
            '<path d="M12 7v14"/>',
            '<path d="M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13"/>',
            '<path d="M3 18a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2"/>',
            '<path d="m16 10 2 2 3-3"/>',
        ],
        'exams' => [
            '<rect x="5" y="4" width="14" height="18" rx="2"/>',
            '<path d="M9 4.5V3h6v1.5"/>',
            '<path d="m9 12 2 2 4-4"/>',
            '<path d="M9 18h6"/>',
        ],
        'review' => [
            '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h7"/>',
            '<path d="M14 2v6h6"/>',
            '<path d="M14 2l6 6"/>',
            '<path d="M8 12h4"/>',
            '<circle cx="16" cy="16" r="4"/>',
            '<path d="m19 19 3 3"/>',
        ],
        'grades' => [
            '<path d="M4 20V12"/>',
            '<path d="M10 20V8"/>',
            '<path d="M16 20V4"/>',
            '<path d="M2 20h20"/>',
        ],
        'subscription' => [
            '<rect x="2" y="5" width="20" height="14" rx="2"/>',
            '<path d="M2 10h20"/>',
            '<path d="M6 15h4"/>',
        ],
        'ai-credits' => [
            '<path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"/>',
            '<path d="m19 17 .7 2.3L22 20l-2.3.7L19 23l-.7-2.3L16 20l2.3-.7L19 17Z"/>',
            '<path d="M4 2v4"/>',
            '<path d="M2 4h4"/>',
        ],
    ];
@endphp

<svg {{ $attributes->merge([
        'class' => 'h-5 w-5 shrink-0',
    ]) }}
     xmlns="http://www.w3.org/2000/svg"
     width="24"
     height="24"
     viewBox="0 0 24 24"
     fill="none"
     stroke="currentColor"
     stroke-width="1.8"
     stroke-linecap="round"
     stroke-linejoin="round"
     aria-hidden="true">
    @foreach ($paths[$name] ?? [] as $path)
        {!! $path !!}
    @endforeach
</svg>
