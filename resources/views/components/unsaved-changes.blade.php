@props(['formId', 'cancelUrl'])

<div
    x-data="{
        dirty: false,
        submitting: false,
        showConfirm: false,
        destination: @js($cancelUrl),

        init() {
            const form = document.getElementById(@js($formId));

            if (!form) return;

            const initial = new FormData(form);
            const initialValues = [...initial.entries()]
                .filter(([key]) => key !== '_token');

            const serialize = () => [...new FormData(form).entries()]
                .filter(([key]) => key !== '_token');

            const updateDirty = () => {
                const current = serialize();

                this.dirty =
                    JSON.stringify(current) !== JSON.stringify(initialValues);
            };

            form.addEventListener('input', updateDirty);
            form.addEventListener('change', updateDirty);

            form.addEventListener('submit', () => {
                this.submitting = true;
            });

            this.$el.addEventListener('click', (event) => {
                const link = event.target.closest('a[data-cancel-form]');

                if (!link) return;

                if (this.dirty) {
                    event.preventDefault();
                    this.destination = link.href;
                    this.showConfirm = true;
                }
            });

            window.addEventListener('beforeunload', (event) => {
                if (this.dirty && !this.submitting) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },

        cancel() {
            if (this.dirty) {
                this.destination = @js($cancelUrl);
                this.showConfirm = true;
            } else {
                window.location.href = @js($cancelUrl);
            }
        },

        discard() {
            this.dirty = false;
            this.showConfirm = false;
            window.location.href = this.destination;
        }
    }"
>
    <button
        type="button"
        @click="cancel()"
        class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-acode-primary hover:bg-slate-50">
        Cancelar
    </button>

    <div
        x-show="showConfirm"
        x-cloak
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="discard-title"
    >
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h2 id="discard-title"
                class="text-lg font-bold text-acode-primary">
                ¿Descartar los cambios?
            </h2>

            <p class="mt-3 text-sm text-slate-600">
                Tienes información sin guardar. Si sales,
                perderás los cambios realizados.
            </p>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    @click="showConfirm = false"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-acode-primary">
                    Continuar editando
                </button>

                <button
                    type="button"
                    @click="discard()"
                    class="rounded-lg bg-acode-danger px-4 py-2 text-sm font-semibold text-white">
                    Descartar cambios
                </button>
            </div>
        </div>
    </div>
</div>
