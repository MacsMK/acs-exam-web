{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-unsaved-changes (MPW-V3 §7.3, §8.3) --}}
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
    <x-button
        type="button"
        variant="neutral"
        size="md"
        @click="cancel()"
    >
        Cancelar
    </x-button>

    <div
        x-show="showConfirm"
        x-cloak
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="discard-title"
    >
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-acode-warning/20 text-amber-800">
                    <x-icon name="warning" class="w-5 h-5" />
                </div>
                <div>
                    <h2 id="discard-title" class="text-lg font-bold text-acode-primary">
                        ¿Descartar los cambios?
                    </h2>
                    <p class="mt-1.5 text-sm text-slate-600 leading-relaxed">
                        Tienes información sin guardar. Si sales de esta pantalla, perderás los ajustes realizados.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2.5 border-t border-slate-100 pt-4">
                <x-button
                    type="button"
                    variant="neutral"
                    size="md"
                    class="rounded-xl"
                    @click="showConfirm = false"
                >
                    Continuar editando
                </x-button>

                <x-button
                    type="button"
                    variant="danger"
                    size="md"
                    class="rounded-xl"
                    @click="discard()"
                >
                    Descartar cambios
                </x-button>
            </div>
        </div>
    </div>
</div>
