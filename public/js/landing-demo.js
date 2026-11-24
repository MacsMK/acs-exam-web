(() => {
    const form = document.querySelector('[data-exam-demo]');
    if (!form) return;

    const question = form.querySelector('fieldset');
    const submit = form.querySelector('button[type="submit"]');
    const feedback = form.querySelector('[role="status"]');
    const correct = question.querySelector('input[value="bucle"]');
    const submitLabel = submit.textContent;

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (question.disabled) {
            form.reset();
            for (const option of question.querySelectorAll('[data-result]')) {
                option.removeAttribute('data-result');
            }
            feedback.textContent = '';
            question.disabled = false;
            submit.textContent = submitLabel;
            question.querySelector('input').focus();
            return;
        }

        const selected = question.querySelector('input:checked');
        if (!selected) return;

        const isCorrect = selected === correct;
        correct.closest('label').dataset.result = 'correct';
        if (!isCorrect) selected.closest('label').dataset.result = 'incorrect';
        feedback.textContent = isCorrect
            ? '¡Correcto! Un bucle repite el bloque mientras la condición sea verdadera.'
            : 'La respuesta correcta es «Un bucle». Repite el bloque mientras la condición sea verdadera.';
        question.disabled = true;
        submit.textContent = 'Volver a intentar';
    });

    question.disabled = false;
    form.querySelector('details').hidden = true;
    submit.hidden = false;
})();
