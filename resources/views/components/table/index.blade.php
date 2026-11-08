{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-table (MPW-V2 §7.3, §8.4, Tabla 29) --}}
@props([
    'header' => null,
    'footer' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6']) }}>
    <div class="overflow-x-auto w-full">
        <table class="w-full text-sm text-left border-collapse">
            @if (isset($header))
                <thead class="bg-acode-primary text-white text-xs uppercase tracking-wider select-none">
                    <tr>
                        {{ $header }}
                    </tr>
                </thead>
            @endif
            <tbody class="divide-y divide-slate-200 [&_tr:nth-child(even)]:bg-acode-surface hover:[&_tr]:bg-slate-100/60 transition-colors">
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
