{{-- Acciones de un grupo definido en la colección reactiva del catálogo. --}}
<div class="flex flex-wrap items-center gap-1">
    <x-button ::href="group.url" ::aria-label="'Abrir grupo: ' + group.name"
              variant="secondary" size="sm" class="whitespace-nowrap">
        Abrir grupo
    </x-button>
    <a :href="group.periods_url"
       :aria-label="'Abrir periodos: ' + group.name"
       class="inline-flex min-h-8 items-center whitespace-nowrap rounded-lg px-2 text-xs font-semibold text-acode-tech hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-acode-tech">
        Abrir periodos
    </a>

    <template x-if="!group.is_closed && group.folio">
        <div class="flex flex-wrap items-center gap-1">
            <x-button
                type="button"
                variant="neutral"
                size="sm"
                ::aria-label="'Copiar folio: ' + group.name"
                @click="copyFolio(group)"
                class="whitespace-nowrap"
            >
                <x-icon name="copy" class="h-3.5 w-3.5" />
                Copiar folio
            </x-button>
            <x-button
                type="button"
                variant="neutral"
                size="sm"
                :icon-only="true"
                ::aria-label="'Abrir QR: ' + group.name"
                title="Abrir QR"
                @click="$dispatch('open-group-qr', { name: group.name, folio: group.folio })"
            >
                <x-icon name="qrCode" class="h-4 w-4" />
            </x-button>
        </div>
    </template>
</div>

<p x-show="copyResult?.groupId === group.id"
   x-cloak
   aria-hidden="true"
   class="mt-1 text-xs text-slate-700"
   x-text="copyResult?.message"></p>
