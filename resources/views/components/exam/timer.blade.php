{{-- Proyecto: acs-exam-web | Organización: A-Code Solutions | Componente: x-exam.timer (MPW-V2 §7.5.1, §7.5.2, §8.7, §8.9.1, ADR-020) --}}
@props([
    'seconds' => 60,
    'formRef' => 'answerForm',
    'title' => 'Tiempo restante',
])

<div
    x-data="{
        remaining: {{ (int) $seconds }},
        timerId: null,
        submitting: false,
        init() {
            this.timerId = setInterval(() => this.tick(), 1000);
        },
        tick() {
            if (this.remaining > 0) {
                this.remaining--;
            }
            if (this.remaining <= 0 && !this.submitting) {
                this.expire();
            }
        },
        expire() {
            this.submitting = true;
            clearInterval(this.timerId);

            // Obtener el formulario objetivo
            const form = this.$root.closest('form') || document.querySelector('form[x-ref=\'{{ $formRef }}\']') || this.$refs['{{ $formRef }}'] || document.querySelector('form');

            if (form) {
                // Deshabilitar validación HTML5 para permitir auto-envío de respuestas en blanco (ADR-020)
                form.querySelectorAll('[required]').forEach(el => el.removeAttribute('required'));

                // Notificación institucional SweetAlert2 antes de auto-envío
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '¡Tiempo agotado!',
                        text: 'El tiempo asignado para este reactivo ha concluido. Tu respuesta se enviará automáticamente.',
                        icon: 'warning',
                        iconColor: '#F4B942',
                        timer: 1600,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        customClass: {
                            popup: 'rounded-2xl shadow-xl font-sans'
                        }
                    }).then(() => {
                        form.submit();
                    });
                } else if (typeof AcodeAlert !== 'undefined') {
                    AcodeAlert.warning('¡Tiempo agotado!', 'Enviando respuesta automáticamente...').then(() => {
                        form.submit();
                    });
                } else {
                    form.submit();
                }
            }
        },
        formattedTime() {
            const mins = Math.floor(this.remaining / 60).toString().padStart(2, '0');
            const secs = (this.remaining % 60).toString().padStart(2, '0');
            return `${mins}:${secs}`;
        }
    }"
    class="inline-flex items-center gap-2 select-none"
>
    <div
        class="font-mono text-sm px-3.5 py-1.5 rounded-lg font-bold flex items-center gap-2 transition-all duration-300 border"
        :class="remaining <= 10
            ? 'bg-acode-danger/10 text-acode-danger border-acode-danger/30 animate-pulse ring-2 ring-acode-danger/20'
            : 'bg-acode-surface text-acode-primary border-slate-200'"
        role="timer"
        aria-live="polite"
        :aria-label="'Tiempo restante: ' + formattedTime()"
    >
        <x-icon name="clock" class="w-4 h-4" ::class="remaining <= 10 ? 'text-acode-danger' : 'text-acode-primary'" />
        <span x-text="formattedTime()"></span>
    </div>
</div>
