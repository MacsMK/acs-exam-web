/**
 * Proyecto: acs-exam-web | Organización: A-Code Solutions
 * Módulo: alerts.js (MPW-V2 §8.7) - Homologación de SweetAlert2 con paleta A-Code
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
                global.alert(`${title}\n${text}`);
                return Promise.resolve({ isConfirmed: true });
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
                global.alert(`Error: ${title}\n${text}`);
                return Promise.resolve({ isConfirmed: true });
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
                global.alert(`Atención: ${title}\n${text}`);
                return Promise.resolve({ isConfirmed: true });
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
            onConfirm = null
        } = {}) {
            if (!hasSwal()) {
                const confirmed = global.confirm(`${title}\n${text}`);
                if (confirmed && typeof onConfirm === 'function') {
                    onConfirm();
                }
                return Promise.resolve({ isConfirmed: confirmed });
            }

            return global.Swal.fire({
                icon: isDanger ? 'warning' : 'question',
                iconColor: isDanger ? PALETTE.danger : PALETTE.tech,
                title: title,
                text: text,
                showCancelButton: true,
                confirmButtonColor: isDanger ? PALETTE.danger : PALETTE.primary,
                cancelButtonColor: PALETTE.neutral,
                confirmButtonText: confirmButtonText,
                cancelButtonText: cancelButtonText,
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl shadow-xl font-sans',
                    confirmButton: 'px-5 py-2.5 rounded-lg text-sm font-semibold',
                    cancelButton: 'px-5 py-2.5 rounded-lg text-sm font-semibold'
                }
            }).then((result) => {
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
            if (!hasSwal()) {
                const val = global.prompt(`${title}\n${text}`);
                if (val !== null && typeof onConfirm === 'function') {
                    onConfirm(val);
                }
                return Promise.resolve({ isConfirmed: val !== null, value: val });
            }

            return global.Swal.fire({
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
            }).then((result) => {
                if (result.isConfirmed && typeof onConfirm === 'function') {
                    onConfirm(result.value);
                }
                return result;
            });
        }
    };

    global.AcodeAlert = AcodeAlert;
})(typeof window !== 'undefined' ? window : this);
