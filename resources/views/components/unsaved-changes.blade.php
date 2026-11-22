{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-unsaved-changes (MPW-V2 §7.3, §8.3) --}}
@props(['formId', 'cancelUrl'])

<div
    x-data="{
        dirty: false,
        submitting: false,
        leaving: false,
        historyGuard: false,
        showConfirm: false,
        destination: @js($cancelUrl),

        init() {
            const form = document.getElementById(@js($formId));

            if (!form) return;

            const serialize = () => [...new FormData(form).entries()]
                .filter(([key]) => key !== '_token');

            let initialValues = [];
            this.$nextTick(() => {
                initialValues = serialize();
            });

            const updateDirty = () => {
                const current = serialize();

                this.dirty =
                    JSON.stringify(current) !== JSON.stringify(initialValues);

                if (this.dirty) {
                    this.armHistoryGuard();
                }
            };

            form.addEventListener('input', updateDirty);
            form.addEventListener('change', updateDirty);

            form.addEventListener('submit', () => {
                this.submitting = true;
                this.dirty = false;
            });

            document.addEventListener('click', (event) => {
                if (!this.dirty || this.submitting) return;

                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                const link = event.target.closest('a[href]');
                if (!link) return;

                const rawHref = (link.getAttribute('href') || '').trim();
                if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:') || link.target === '_blank' || link.hasAttribute('download')) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                this.destination = link.href;
                this.showConfirm = true;
            }, true);

            // Botón Atrás del navegador: la entrada extra del historial se
            // consume sin salir de la página y se muestra el modal propio.
            window.addEventListener('popstate', () => {
                if (!this.historyGuard) return;

                this.historyGuard = false;

                if (!this.dirty || this.submitting) {
                    history.back();
                    return;
                }

                this.destination = null;
                this.showConfirm = true;
            });

            // Recargar o cerrar la pestaña: el navegador no permite un modal
            // propio en estos casos; solo su aviso nativo evita perder datos.
            window.addEventListener('beforeunload', (event) => {
                if (!this.dirty || this.submitting || this.leaving) return;

                event.preventDefault();
                event.returnValue = '';
            });
        },

        armHistoryGuard() {
            if (this.historyGuard) return;

            history.pushState({ acsUnsavedChanges: true }, '', window.location.href);
            this.historyGuard = true;
        },

        keepEditing() {
            this.showConfirm = false;

            if (this.dirty) {
                this.armHistoryGuard();
            }
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
            this.leaving = true;
            this.showConfirm = false;

            if (this.destination === null) {
                history.back();
                return;
            }

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

    <template x-teleport="body">
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
                        @click="keepEditing()"
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
                        Cancelar cambios
                    </x-button>
                </div>
            </div>
        </div>
    </template>
</div>
