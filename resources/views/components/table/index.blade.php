{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-table (MPW-V3 §7.3, §8.4) --}}
@props([
    'header' => null,
    'footer' => null,
    'zebra' => null,
    'scrollLabel' => null,
    'tableClass' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden']) }}>
    <div @class([
             'overflow-x-auto w-full',
             'focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-acode-tech' => $scrollLabel,
         ])
         @if ($scrollLabel) role="region" aria-label="{{ $scrollLabel }}" tabindex="0" data-table-scroll @endif>
        <table class="w-full text-sm text-left border-collapse {{ $tableClass }}">
            {{ $columns ?? '' }}
            @if (isset($header))
                <thead class="bg-acode-primary text-white text-xs uppercase tracking-wider select-none">
                    <tr>
                        {{ $header }}
                    </tr>
                </thead>
            @endif
            <tbody @class([
                'divide-y divide-slate-200 bg-white [&>tr]:transition-colors',
                '[&>tr:nth-of-type(even):not(:hover)]:bg-slate-50 [&>tr:hover]:bg-blue-50' => $zebra === 'slate',
                '[&>tr:nth-of-type(even):not(:hover)]:bg-acode-surface/60 [&>tr:hover]:bg-blue-50/70' => $zebra !== 'slate',
            ])>
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if (isset($footer))
        <div class="px-4 py-3 border-t border-slate-200 bg-slate-50/50 flex items-center justify-between">
            {{ $footer }}
        </div>
    @endif
</div>
