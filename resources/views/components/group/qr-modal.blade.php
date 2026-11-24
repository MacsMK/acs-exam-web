{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-group.qr-modal (MPW-V3 §§7.3, 8.6, 8.7, 8.9.2) --}}
@props([
    'name' => 'group-qr-modal',
    'groupName' => 'Grupo escolar',
    'folio' => '',
    'joinUrl' => null,
])

<div
    x-data="{
        modalName: @js($name),
        groupName: @js($groupName),
        folio: @js($folio),
        qrData: @js($joinUrl ?? $folio),
        qrFailed: false,
        copyStatus: '',
        get qrEndpoint() {
            const query = new URLSearchParams({ size: '220x220', data: this.qrData, color: '142B4A', bgcolor: 'FFFFFF', qzone: '4' });
            return 'https://api.qrserver.com/v1/create-qr-code/?' + query;
        },
        openForGroup(group) {
            if (!group?.folio) return;
            this.groupName = group.name;
            this.folio = group.folio;
            this.qrData = group.folio;
            this.$dispatch('open-modal', this.modalName);
        },
        async copyFolio(dialog) {
            const folio = this.folio;
            const copied = await window.copyGroupFolio?.(folio);
            if (this.folio !== folio || !dialog?.open) return;

            this.copyStatus = copied
                ? 'Folio copiado al portapapeles.'
                : 'No se pudo copiar. Selecciona el folio para copiarlo manualmente.';
            if (copied) window.AcodeAlert?.toast(this.copyStatus, 'success');
        }
    }"
    @open-group-qr.window="openForGroup($event.detail)"
    @modal-closed="qrFailed = false; copyStatus = ''"
>
    <x-modal :name="$name" max-width="md">
        <x-slot:title>
            Código QR y Folio de Acceso · <span x-text="groupName">{{ $groupName }}</span>
        </x-slot:title>

        <div class="flex flex-col items-center gap-4 text-center">
            <p class="max-w-xs text-sm text-slate-700">
                Comparte el código QR o el folio con tus estudiantes para que se inscriban al grupo.
            </p>

            <template x-if="show && qrData && !qrFailed">
                <img
                    :src="qrEndpoint"
                    :alt="'Código QR de acceso al grupo ' + groupName"
                    width="220"
                    height="220"
                    class="h-auto max-w-full rounded-lg border border-slate-200 bg-white"
                    x-on:error="qrFailed = true"
                >
            </template>
            <p x-show="qrFailed" role="status" class="text-sm text-slate-700">
                No se pudo cargar el código QR. Puedes compartir el folio de acceso.
            </p>

            <div class="w-full rounded-xl border border-slate-200 bg-acode-surface p-3">
                <span class="mb-1 block text-xs font-semibold text-slate-700">Folio de acceso</span>
                <span class="block select-all break-all font-mono text-2xl font-bold tracking-wider text-acode-primary" x-text="folio">{{ $folio }}</span>
            </div>
            <p role="status" aria-atomic="true" class="text-sm text-slate-700" x-text="copyStatus"></p>
        </div>

        <x-slot:footer>
            <x-button type="button" variant="neutral" size="md" @click="$dispatch('close-modal', modalName)">
                Cerrar
            </x-button>
            <x-button type="button" variant="primary" size="md" @click="copyFolio($el.closest('dialog'))">
                <x-icon name="copy" class="h-4 w-4" />
                Copiar folio
            </x-button>
        </x-slot:footer>
    </x-modal>
</div>

@once
    @push('scripts')
        <script src="{{ asset('js/group-access.js') }}"></script>
    @endpush
@endonce
