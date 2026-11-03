{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-modal (MPW-V2 §7.3, §8.6, Tabla 29) --}}
@props([
    'name' => 'modal',
    'title' => null,
    'maxWidth' => 'lg', // sm, md, lg, xl, 2xl
    'show' => false,
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        default => 'sm:max-w-lg',
    };
@endphp

<div
    x-data="{ show: @js($show) }"
    x-on:open-modal.window="$event.detail === '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail === '{{ $name }}' ? show = false : null"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
    style="display: none;"
    role="dialog"
    aria-modal="true"
>
    {{-- Fondo oscurecido y desenfocado normativo --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
        @click="show = false"
        aria-hidden="true"
    ></div>

    {{-- Contenedor de la ventana modal (16px rounded-2xl, shadow-xl) --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        class="relative bg-white rounded-2xl shadow-xl overflow-hidden transform transition-all w-full border border-slate-200 z-10 {{ $maxWidthClass }}"
    >
        {{-- Encabezado con título institucional y botón de cierre 'X' --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/70">
            <h3 class="text-base font-bold text-acode-primary tracking-tight">
                {{ $title ?? 'Ventana informativa' }}
            </h3>
            <button
                type="button"
                @click="show = false"
                class="text-slate-400 hover:text-slate-600 rounded-lg p-1 transition focus:outline-none focus:ring-2 focus:ring-acode-tech"
                aria-label="Cerrar modal"
            >
                <x-icon name="xMark" class="w-5 h-5" />
            </button>
        </div>

        {{-- Cuerpo del modal --}}
        <div class="px-6 py-5 text-sm text-slate-700 max-h-[calc(100vh-14rem)] overflow-y-auto">
            {{ $slot }}
        </div>

        {{-- Pie de acciones opcional --}}
        @if (isset($footer))
            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
