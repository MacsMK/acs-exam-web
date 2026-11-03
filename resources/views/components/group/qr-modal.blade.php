{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-group.qr-modal (MPW-V2 §7.3, §7.8.2, §8.6, §8.7, §8.9.2, Tabla 20, Tabla 31) --}}
@props([
    'name' => 'group-qr-modal',
    'groupName' => 'Grupo escolar',
    'folio' => '',
    'joinUrl' => null,
])

@php
    $qrData = $joinUrl ?? $folio;
    $qrEndpoint = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($qrData) . '&color=14-43-74&bgcolor=255-255-255&margin=1';
@endphp

<x-modal :name="$name" :title="'Código QR y Folio de Acceso · ' . $groupName" maxWidth="md">
    <div
        x-data="{
            folio: @js($folio),
            copied: false,
            copyFolio() {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(this.folio).then(() => {
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2500);

                        if (typeof AcodeAlert !== 'undefined') {
                            AcodeAlert.toast('Folio copiado al portapapeles con éxito', 'success');
                        } else if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                iconColor: '#16A6A1',
                                title: 'Folio copiado al portapapeles con éxito',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true,
                                customClass: {
                                    popup: 'rounded-xl shadow-lg text-sm font-medium font-sans'
                                }
                            });
                        }
                    });
                }
            }
        }"
        class="flex flex-col items-center text-center p-2"
    >
        <p class="text-xs text-slate-500 mb-4 max-w-xs">
            Comparte este código QR o el folio alfanumérico con los estudiantes para que se matriculen en el grupo.
        </p>

        {{-- Visualizador de Código QR en alta definición --}}
        <div class="p-3 bg-white border border-slate-200 rounded-2xl shadow-sm mb-4 inline-flex items-center justify-center">
            <img
                src="{{ $qrEndpoint }}"
                alt="Código QR de acceso al grupo {{ $groupName }}"
                class="w-48 h-48 object-contain rounded-lg"
                loading="lazy"
            />
        </div>

        {{-- Contenedor del Folio Alfanumérico --}}
        <div class="w-full bg-acode-surface border border-slate-200 rounded-xl p-3 mb-4">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 block mb-1">
                Folio alfanumérico oficial
            </span>
            <span class="font-mono text-2xl font-bold tracking-widest text-acode-primary block select-all">
                {{ $folio }}
            </span>
        </div>

        {{-- Botón de Copiar folio con Toast institucional --}}
        <div class="flex items-center justify-center gap-3 w-full">
            <x-button
                type="button"
                variant="accent"
                size="md"
                class="w-full"
                @click="copyFolio()"
            >
                <x-icon name="check" class="w-4 h-4 mr-1.5" x-show="copied" />
                <x-icon name="qrCode" class="w-4 h-4 mr-1.5" x-show="!copied" />
                <span x-text="copied ? '¡Folio copiado!' : 'Copiar folio'"></span>
            </x-button>
        </div>
    </div>

    <x-slot:footer>
        <x-button
            type="button"
            variant="neutral"
            size="md"
            x-on:click="$dispatch('close-modal', '{{ $name }}')"
        >
            Cerrar
        </x-button>
    </x-slot:footer>
</x-modal>
