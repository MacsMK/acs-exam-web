(function () {
    function permitsInsertion(input, text) {
        const { value, selectionStart, selectionEnd } = input;
        const next = value.slice(0, selectionStart) + text + value.slice(selectionEnd);
        const partial = input.dataset.numeric === 'integer'
            ? /^[0-9]*$/
            : /^(?:[0-9]+(?:[.,][0-9]{0,2})?)?$/;

        return partial.test(next);
    }

    window.academicNumber = {
        guard(event) {
            if (!event.cancelable || event.isComposing || !event.inputType?.startsWith('insert')) return;
            if (typeof event.data === 'string' && !permitsInsertion(event.target, event.data)) event.preventDefault();
        },

        guardPaste(event) {
            const text = event.clipboardData?.getData('text');
            if (text && !permitsInsertion(event.target, text)) event.preventDefault();
        },

        validate(input) {
            const { value } = input;
            let message = '';
            if (value !== '') {
                const integer = input.dataset.numeric === 'integer';
                const format = integer ? /^[0-9]+$/ : /^[0-9]+(?:[.,][0-9]{1,2})?$/;
                const number = Number(value.replace(',', '.'));
                const min = input.getAttribute('min');
                const max = input.getAttribute('max');

                if (!format.test(value)) {
                    message = integer ? 'Escribe un número entero.' : 'Escribe un número con hasta dos decimales.';
                } else if (min !== null && number < Number(min)) {
                    message = 'El valor debe ser mayor o igual a ' + min + '.';
                } else if (max !== null && number > Number(max)) {
                    message = 'El valor debe ser menor o igual a ' + max + '.';
                }
            }

            input.setCustomValidity(message);
        },
    };
})();
