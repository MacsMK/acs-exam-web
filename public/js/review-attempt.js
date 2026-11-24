window.acodeReviewAttempt = function (skipReevaluationWarning, refreshAvailable) {
    const reloadDraftKey = 'acodeReviewReloadDraft';

    return {
        submission: null,
        submissionMessage: '',
        refreshAvailable,
        refreshError: '',
        fieldErrors: {},
        pendingAiForm: null,
        dismissWarning: false,
        destination: null,

        init() {
            this.$nextTick(() => {
                this.restoreReloadDraft();

                const feedback = this.$root.querySelector('[data-review-feedback]')
                    ?? Array.from(this.$root.parentElement.children).find(element =>
                        element !== this.$root && element.matches('[role="status"], [role="alert"]')
                    );

                if (feedback) {
                    feedback.tabIndex = -1;
                    feedback.classList.add('scroll-mt-20');
                    feedback.focus();
                }
            });
        },

        editableFields() {
            return Array.from(this.$refs.reviewForm.querySelectorAll('[data-review-field]:enabled'));
        },

        changedFields() {
            const decimal = /^[0-9]+(?:[.,][0-9]{1,2})?$/;
            return this.editableFields()
                .filter(field => {
                    const { value, dataset } = field;
                    const initial = dataset.reviewInitial;
                    if (dataset.reviewField === 'score' && decimal.test(value) && decimal.test(initial)) {
                        return Number(value.replace(',', '.')) !== Number(initial.replace(',', '.'));
                    }
                    return value !== initial;
                });
        },

        setReloadDraft(draft) {
            const state = { ...window.history.state };
            if (draft === null) {
                if (!Object.hasOwn(state, reloadDraftKey)) return true;
                delete state[reloadDraftKey];
            } else {
                state[reloadDraftKey] = draft;
            }

            try {
                window.history.replaceState(state, '');
                return true;
            } catch {
                return false;
            }
        },

        preserveReloadDraft() {
            const edited = this.changedFields();
            if (edited.length === 0 && !this.refreshAvailable) return true;

            const fields = Object.fromEntries(edited.map(field => [field.id, field.value]));
            const saved = this.setReloadDraft({ fields, refreshAvailable: this.refreshAvailable });
            return saved || edited.length === 0;
        },

        restoreReloadDraft() {
            const draft = window.history.state?.[reloadDraftKey];
            if (!draft) return;

            this.setReloadDraft(null);
            if (window.performance.getEntriesByType('navigation')[0]?.type !== 'reload') return;

            this.refreshAvailable ||= draft.refreshAvailable;
            for (const field of this.editableFields()) {
                if (Object.hasOwn(draft.fields, field.id)) field.value = draft.fields[field.id];
            }
        },

        refreshReview() {
            if (this.submission !== null) return;

            if (!this.preserveReloadDraft()) {
                this.refreshError = 'El navegador no pudo conservar tus cambios. Guarda la revisión antes de actualizar.';
                return;
            }

            this.submission = 'refresh';
            this.submissionMessage = 'Actualizando sugerencias de IA.';
            window.location.reload();
        },

        guardUnload(event) {
            if (this.submission === null && this.changedFields().length > 0) {
                event.preventDefault();
                event.returnValue = '';
            }
        },

        guardNavigation(event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const link = event.target.closest('a[href]');
            if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) {
                return;
            }

            if (this.submission !== null) {
                event.preventDefault();
                return;
            }

            if (this.changedFields().length > 0) {
                event.preventDefault();
                this.destination = link.href;
                this.openDialog(link, 'review-discard');
            }
        },

        discardChanges() {
            if (!this.destination) return;

            this.setReloadDraft(null);
            this.submission = 'discard';
            window.location.assign(this.destination);
        },

        openDialog(trigger, name) {
            trigger.dispatchEvent(new CustomEvent('open-modal', { bubbles: true, detail: name }));
        },

        showFieldError(event) {
            const field = event.currentTarget;
            this.fieldErrors[field.id] = field.validationMessage;
        },

        clearFieldError(event) {
            const field = event.currentTarget;
            if (this.fieldErrors[field.id]) {
                this.$nextTick(() => {
                    this.fieldErrors[field.id] = field.validationMessage;
                });
            }
        },

        startSubmission(event, key, message) {
            if (this.submission !== null) {
                event.preventDefault();
                return false;
            }

            this.submission = key;
            this.submissionMessage = message;
            return true;
        },

        submitReview(event) {
            if (this.startSubmission(event, 'review', 'Guardando revisión.')) this.setReloadDraft(null);
        },

        submitAi(event, key) {
            if (!this.startSubmission(event, key, 'Solicitando evaluación con IA.')) return;

            const form = event.currentTarget;
            form.querySelectorAll('[data-review-draft]').forEach(field => field.remove());

            for (const field of this.changedFields()) {
                const draft = document.createElement('input');
                draft.type = 'hidden';
                draft.name = `review_draft[${field.dataset.reviewAnswer}][${field.dataset.reviewField}]`;
                draft.value = field.value;
                draft.setAttribute('data-review-draft', '');
                form.append(draft);
            }
        },

        requestAi(answerId, reevaluate, trigger) {
            if (this.submission !== null) return;

            const form = document.getElementById(`ai-eval-form-${answerId}`);
            if (!form) return;

            if (reevaluate && !skipReevaluationWarning) {
                this.pendingAiForm = form;
                this.dismissWarning = false;
                this.openDialog(trigger, 'review-reevaluate-ai');
                return;
            }

            this.submitAnswer(form, reevaluate, false);
        },

        confirmReevaluation() {
            if (!this.pendingAiForm || this.submission !== null) return;

            this.$dispatch('close-modal', 'review-reevaluate-ai');
            this.submitAnswer(this.pendingAiForm, true, this.dismissWarning);
            this.pendingAiForm = null;
        },

        submitAnswer(form, reevaluate, dismissWarning) {
            form.elements.is_reevaluation.value = reevaluate ? '1' : '0';
            form.elements.dismiss_warning.value = dismissWarning ? '1' : '0';
            form.requestSubmit();
        },

        resetSubmission() {
            this.submission = null;
            this.submissionMessage = '';
        },
    };
};
