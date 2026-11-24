{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-unsaved-changes (MPW-V3 §7.3, §8.3) --}}
@props(['formId', 'cancelUrl', 'hasRestoredDraft' => false])

<div
    x-data="{
        formId: @js($formId),
        cancelUrl: @js($cancelUrl),
        modalName: @js('unsaved-changes-' . $formId),
        hasRestoredDraft: @js($hasRestoredDraft),
        form: null,
        initialValues: null,
        destination: null,
        submitting: false,

        init() {
            this.form = document.getElementById(this.formId);
            this.cancelUrl = new URL(this.cancelUrl, document.baseURI).href;

            if (this.form) {
                this.$nextTick(() => { this.initialValues = this.snapshot(); });
            }
        },

        snapshot() {
            const values = [];
            for (const [name, value] of new FormData(this.form)) {
                if (name === '_token') continue;

                const normalizedValue = typeof value === 'string'
                    ? value
                    : (value.name ? [value.name, value.size, value.type, value.lastModified] : '');
                values.push([name, normalizedValue]);
            }

            return JSON.stringify(values);
        },

        hasChanges() {
            return !this.submitting && this.initialValues !== null
                && (this.hasRestoredDraft || this.snapshot() !== this.initialValues);
        },

        confirmNavigation(destination, trigger) {
            this.destination = destination;
            trigger.dispatchEvent(new CustomEvent('open-modal', { bubbles: true, detail: this.modalName }));
        },

        guardLink(event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            const link = event.target.closest?.('a[href]');
            if (!link || link.hasAttribute('download') || (link.target && link.target.toLowerCase() !== '_self')) return;

            const marker = link.closest('[data-cancel-form]');
            const markedForThisForm = marker?.dataset.cancelForm === this.formId
                || (marker?.dataset.cancelForm === '' && this.$el.contains(link));

            if ((link.href === this.cancelUrl || markedForThisForm) && this.hasChanges()) {
                event.preventDefault();
                this.confirmNavigation(link.href, link);
            }
        },

        cancel(trigger) {
            if (this.hasChanges()) {
                this.confirmNavigation(this.cancelUrl, trigger);
            } else {
                window.location.assign(this.cancelUrl);
            }
        },

        discard() {
            if (!this.destination) return;

            this.submitting = true;
            this.$dispatch('close-modal', this.modalName);
            window.location.assign(this.destination);
        },
    }"
    x-on:click.document="guardLink($event)"
    x-on:submit.document="if ($event.target === form && !$event.defaultPrevented) submitting = true"
    x-on:pageshow.window="submitting = false"
    x-on:beforeunload.window="if (hasChanges()) { $event.preventDefault(); $event.returnValue = ''; }"
>
    <x-button
        type="button"
        variant="neutral"
        size="md"
        @click="cancel($event.currentTarget)"
    >
        Cancelar
    </x-button>

    <x-modal :name="'unsaved-changes-' . $formId" title="¿Descartar los cambios?" max-width="md">
        <p>Tienes cambios sin guardar. Si sales de esta pantalla, se perderán.</p>

        <x-slot:footer>
            <x-button type="button" variant="neutral" size="md" @click="$dispatch('close-modal', modalName)">
                Continuar editando
            </x-button>
            <x-button type="button" variant="danger" size="md" @click="discard()">
                Descartar cambios
            </x-button>
        </x-slot:footer>
    </x-modal>
</div>
