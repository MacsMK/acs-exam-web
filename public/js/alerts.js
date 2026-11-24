/**
 * Proyecto: acs-exam-web | Organización: A-Code Solutions
 * Módulo: alerts.js (MPW-V3 §8.7) - Homologación de SweetAlert2 con paleta A-Code
 */
(function (global) {
    'use strict';

    const PALETTE = {
        primary: '#142B4A',
        tech: '#2563A6',
        accent: '#16A6A1',
        warning: '#F4B942',
        danger: '#DC2626',
        surface: '#E9F0F5',
        neutral: '#64748B'
    };

    const hasSwal = () => typeof global.Swal !== 'undefined';
    let fallbackDialogSequence = 0;

    function fallbackDialog({ title, text, confirmButtonText, cancelButtonText, isDanger = false, focusCancel = isDanger, inputPlaceholder, institutional = false }) {
        const trigger = document.activeElement;
        const dialog = document.createElement('dialog');
        if (typeof dialog.showModal !== 'function') return Promise.resolve({ isConfirmed: false });

        const id = `acode-alert-${++fallbackDialogSequence}`;
        dialog.setAttribute('aria-labelledby', `${id}-title`);
        dialog.className = 'm-auto w-11/12 max-w-md rounded-2xl border border-slate-200 bg-white p-0 text-slate-700 shadow-xl backdrop:bg-slate-900/60 backdrop:backdrop-blur-sm';
        if (institutional) {
            dialog.classList.add('border-t-4', isDanger ? 'border-t-acode-danger' : 'border-t-acode-primary');
        }

        const form = document.createElement('form');
        form.method = 'dialog';
        form.className = 'space-y-4 p-6';
        dialog.append(form);

        const heading = document.createElement('h2');
        heading.id = `${id}-title`;
        heading.textContent = title;
        heading.className = 'text-xl font-semibold text-acode-primary';
        form.append(heading);

        if (text) {
            const description = document.createElement('p');
            description.id = `${id}-description`;
            description.textContent = text;
            dialog.setAttribute('aria-describedby', description.id);
            form.append(description);
        }

        let input;
        if (inputPlaceholder !== undefined) {
            input = document.createElement('input');
            input.type = 'text';
            input.placeholder = inputPlaceholder;
            input.setAttribute('aria-label', inputPlaceholder || title);
            input.className = 'h-10 w-full rounded-lg border border-slate-300 px-3 focus:border-acode-tech focus:ring-2 focus:ring-acode-tech';
            input.autofocus = true;
            form.append(input);
        }

        const actions = document.createElement('div');
        actions.className = 'flex flex-wrap justify-end gap-2';
        form.append(actions);

        const buttonClasses = 'min-h-10 rounded-lg px-4 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-acode-tech';
        if (cancelButtonText) {
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.textContent = cancelButtonText;
            cancel.className = `${buttonClasses} border border-slate-300 bg-white text-slate-700`;
            cancel.autofocus = focusCancel;
            cancel.addEventListener('click', () => dialog.close('cancel'));
            actions.append(cancel);
        }

        const confirm = document.createElement('button');
        confirm.type = 'submit';
        confirm.value = 'confirm';
        confirm.textContent = confirmButtonText;
        confirm.className = `${buttonClasses} text-white ${isDanger ? 'bg-acode-danger' : 'bg-acode-primary'}`;
        confirm.autofocus = !focusCancel && !input;
        actions.append(confirm);

        return new Promise(resolve => {
            dialog.addEventListener('close', () => {
                const isConfirmed = dialog.returnValue === 'confirm';
                dialog.remove();
                if (trigger?.isConnected) trigger.focus();
                resolve(input && isConfirmed ? { isConfirmed, value: input.value } : { isConfirmed });
            }, { once: true });
            document.body.append(dialog);
            dialog.showModal();
        });
    }

    const AcodeAlert = {
        palette: PALETTE,

        /**
         * Alerta de Éxito institucional
         * @param {string} title
         * @param {string} text
         * @returns {Promise}
         */
        success(title, text = '') {
            if (!hasSwal()) {
                return fallbackDialog({ title: title || 'Operación exitosa', text, confirmButtonText: 'Aceptar' });
            }

            return global.Swal.fire({
                icon: 'success',
                iconColor: PALETTE.accent,
                title: title || 'Operación exitosa',
                text: text,
                confirmButtonColor: PALETTE.primary,
                confirmButtonText: 'Aceptar',
                customClass: {
                    popup: 'rounded-2xl shadow-xl font-sans',
                    confirmButton: 'px-5 py-2.5 rounded-lg text-sm font-semibold'
                }
            });
        },

        /**
         * Alerta de Error institucional
         * @param {string} title
         * @param {string} text
         * @returns {Promise}
         */
        error(title, text = '') {
            if (!hasSwal()) {
                return fallbackDialog({ title: title || 'Ocurrió un error', text, confirmButtonText: 'Aceptar' });
            }

            return global.Swal.fire({
                icon: 'error',
                iconColor: PALETTE.danger,
                title: title || 'Ocurrió un error',
                text: text,
                confirmButtonColor: PALETTE.primary,
                confirmButtonText: 'Aceptar',
                customClass: {
                    popup: 'rounded-2xl shadow-xl font-sans',
                    confirmButton: 'px-5 py-2.5 rounded-lg text-sm font-semibold'
                }
            });
        },

        /**
         * Alerta de Advertencia institucional (ej. cronómetro <= 10s o pérdida de foco)
         * @param {string} title
         * @param {string} text
         * @returns {Promise}
         */
        warning(title, text = '') {
            if (!hasSwal()) {
                return fallbackDialog({ title: title || 'Atención', text, confirmButtonText: 'Entendido' });
            }

            return global.Swal.fire({
                icon: 'warning',
                iconColor: PALETTE.warning,
                title: title || 'Atención',
                text: text,
                confirmButtonColor: PALETTE.primary,
                confirmButtonText: 'Entendido',
                customClass: {
                    popup: 'rounded-2xl shadow-xl font-sans',
                    confirmButton: 'px-5 py-2.5 rounded-lg text-sm font-semibold'
                }
            });
        },

        /**
         * Cuadro de diálogo de confirmación con los 18 verbos de acción oficiales
         * @param {Object} options
         * @returns {Promise}
         */
        confirm({
            title = '¿Confirmar acción?',
            text = '',
            confirmButtonText = 'Aceptar',
            cancelButtonText = 'Cancelar',
            isDanger = false,
            focusCancel = isDanger,
            institutional = false,
            onConfirm = null
        } = {}) {
            const popup = 'rounded-2xl shadow-xl font-sans';
            const actionBorder = isDanger ? '!border-t-acode-danger' : '!border-t-acode-primary';
            const button = 'px-5 py-2.5 rounded-lg text-sm font-semibold';
            const dialog = hasSwal() ? global.Swal.fire({
                icon: isDanger ? 'warning' : 'question',
                iconColor: isDanger ? PALETTE.danger : PALETTE.tech,
                title: title,
                text: text,
                showCancelButton: true,
                focusCancel,
                confirmButtonColor: isDanger ? PALETTE.danger : PALETTE.primary,
                cancelButtonColor: PALETTE.neutral,
                confirmButtonText: confirmButtonText,
                cancelButtonText: cancelButtonText,
                reverseButtons: true,
                backdrop: institutional ? 'rgba(15, 23, 42, 0.6)' : true,
                customClass: institutional ? {
                    container: 'backdrop-blur-sm',
                    popup: `${popup} !border-solid !border-t-4 ${actionBorder}`,
                    title: '!px-6 !text-left !text-xl !font-semibold !leading-7 !text-acode-primary',
                    htmlContainer: '!mx-0 !px-6 !text-left !text-sm !text-slate-700',
                    actions: '!mx-0 !w-full !justify-end !px-6',
                    confirmButton: button,
                    cancelButton: `${button} !border !border-slate-300 !bg-white !text-slate-700`,
                } : { popup, confirmButton: button, cancelButton: button }
            }) : fallbackDialog({ title, text, confirmButtonText, cancelButtonText, isDanger, focusCancel, institutional });

            return dialog.then((result) => {
                if (result.isConfirmed && typeof onConfirm === 'function') {
                    onConfirm();
                }
                return result;
            });
        },

        /**
         * Notificación Toast superior derecha de 3000 ms
         * @param {string} message
         * @param {string} icon ('success' | 'error' | 'warning' | 'info')
         * @returns {Promise}
         */
        toast(message, icon = 'success') {
            const iconColors = {
                success: PALETTE.accent,
                error: PALETTE.danger,
                warning: PALETTE.warning,
                info: PALETTE.tech
            };

            if (!hasSwal()) {
                console.log(`[Toast] (${icon}): ${message}`);
                return Promise.resolve();
            }

            return global.Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon,
                iconColor: iconColors[icon] || PALETTE.accent,
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                customClass: {
                    popup: 'rounded-xl shadow-lg text-sm font-medium font-sans'
                }
            });
        },

        /**
         * Cuadro de entrada modal
         * @param {Object} options
         * @returns {Promise}
         */
        prompt({
            title = 'Ingresar información',
            text = '',
            inputPlaceholder = '',
            onConfirm = null
        } = {}) {
            const dialog = hasSwal() ? global.Swal.fire({
                title: title,
                text: text,
                input: 'text',
                inputPlaceholder: inputPlaceholder,
                showCancelButton: true,
                confirmButtonColor: PALETTE.primary,
                cancelButtonColor: PALETTE.neutral,
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl shadow-xl font-sans',
                    input: 'rounded-lg border-slate-300 text-sm focus:ring-acode-tech',
                    confirmButton: 'px-5 py-2.5 rounded-lg text-sm font-semibold',
                    cancelButton: 'px-5 py-2.5 rounded-lg text-sm font-semibold'
                }
            }) : fallbackDialog({ title, text, inputPlaceholder, confirmButtonText, cancelButtonText });

            return dialog.then((result) => {
                if (result.isConfirmed && typeof onConfirm === 'function') {
                    onConfirm(result.value);
                }
                return result;
            });
        }
    };

    global.AcodeAlert = AcodeAlert;
})(typeof window !== 'undefined' ? window : this);
