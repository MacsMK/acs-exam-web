(() => {
    const disallowedCharacters = {
        name: /[^\p{L}\p{M} ]/gu,
        digits: /[^0-9]/g,
    };

    function allowMexicanPhonePaste(field) {
        let pasteError;

        field.addEventListener('input', () => pasteError?.remove());
        field.addEventListener('paste', (event) => {
            const clipboardText = event.clipboardData?.getData('text/plain');
            if (!clipboardText) {
                return;
            }

            event.preventDefault();

            const pastedText = clipboardText.trim();
            const hasMexicanPrefix = pastedText.startsWith('+52');
            const numberText = hasMexicanPrefix ? pastedText.slice(3) : pastedText;
            const digits = numberText.replace(/[\p{Zs}\t().-]/gu, '');
            const isComplete = digits.length === field.maxLength;
            const start = isComplete ? 0 : field.selectionStart;
            const end = isComplete ? field.value.length : field.selectionEnd;
            const resultingLength = field.value.length - (end - start) + digits.length;

            if (digits === '' || /[^0-9]/.test(digits)
                || (hasMexicanPrefix && !isComplete)
                || resultingLength > field.maxLength) {
                if (!pasteError?.isConnected) {
                    pasteError = document.createElement('p');
                    pasteError.className = 'text-xs text-acode-danger mt-1 font-medium';
                    pasteError.setAttribute('role', 'alert');
                    pasteError.textContent = 'El teléfono debe contener 10 dígitos, con o sin el prefijo +52';
                    field.parentElement.after(pasteError);
                }
                return;
            }

            field.setRangeText(digits, start, end, 'end');
            field.dispatchEvent(new InputEvent('input', {
                bubbles: true,
                inputType: 'insertFromPaste',
                data: digits,
            }));
        });
    }

    document.querySelectorAll('input[data-character-filter]').forEach((field) => {
        const pattern = disallowedCharacters[field.dataset.characterFilter];

        function clean() {
            const original = field.value;
            const filtered = original.replace(pattern, '');

            if (filtered === original) {
                return;
            }

            const cursor = original.slice(0, field.selectionStart).replace(pattern, '').length;
            field.value = filtered;
            field.setSelectionRange(cursor, cursor);
        }

        field.addEventListener('input', (event) => {
            if (!event.isComposing) {
                clean();
            }
        });
        field.addEventListener('compositionend', clean);

        if (field.hasAttribute('data-mexican-phone')) {
            allowMexicanPhonePaste(field);
        }
    });
})();
