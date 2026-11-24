{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-modal (MPW-V3 §7.3, §8.6) --}}
@props([
    'name' => 'modal',
    'title' => null,
    'maxWidth' => 'lg', // sm, md, lg, xl, 2xl
    'show' => false,
    'focusClose' => true,
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
    x-data="{
        show: false,
        opener: null,
        init() {
            if (@js($show)) this.$nextTick(() => this.openDialog());
        },
        openDialog(trigger = document.activeElement) {
            if (this.$refs.dialog.open) return;
            this.opener = trigger?.closest?.('button, a[href], [tabindex]') ?? document.activeElement;
            this.$refs.dialog.showModal();
            this.show = true;
        },
        closeDialog() {
            this.$refs.dialog.close();
        },
        afterClose() {
            this.show = false;
            if (this.opener?.isConnected) this.opener.focus();
            this.opener = null;
            this.$dispatch('modal-closed', @js($name));
        }
    }"
    @open-modal.window="if ($event.detail === @js($name)) openDialog($event.target)"
    @close-modal.window="if ($event.detail === @js($name)) closeDialog()"
>
    <dialog
        x-ref="dialog"
        @close="afterClose()"
        @click.self="closeDialog()"
        aria-labelledby="{{ $name }}-title"
        class="m-auto max-h-[90dvh] w-11/12 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/60 backdrop:backdrop-blur-sm {{ $maxWidthClass }}"
    >
        <div class="flex items-center justify-between gap-3 border-b border-acode-tech/25 bg-slate-50 px-6 py-4">
            <h3 id="{{ $name }}-title" class="min-w-0 break-words text-base font-semibold leading-6 text-acode-primary">
                {{ $title ?? 'Ventana informativa' }}
            </h3>
            <x-button type="button" variant="neutral" size="sm" :icon-only="true" @click="closeDialog()" aria-label="Cerrar ventana" class="shrink-0" :autofocus="$focusClose">
                <x-icon name="xMark" class="h-4 w-4" />
            </x-button>
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
    </dialog>
</div>
