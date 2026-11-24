(() => {
    for (const form of document.querySelectorAll('[data-catalog-search]')) {
        const resultsId = form.dataset.catalogSearch;
        const field = form.elements.namedItem(form.dataset.catalogSearchField);
        const status = form.querySelector('[data-catalog-status]');
        const clear = form.querySelector('[data-catalog-clear]');
        const clearButton = clear?.querySelector('button');
        const clearForm = clearButton?.form;
        const clearUrl = clearForm && new URL(clearForm.action);
        const clearFields = clearForm ? [field, ...form.querySelectorAll('select[name]')] : [];
        let timer;
        let request;
        let hasSearchHistory = false;
        let tableScrollLeft = 0;

        function stopSearch() {
            clearTimeout(timer);
            timer = undefined;
            request?.abort();
        }

        function updateHistory(url) {
            if (hasSearchHistory) {
                history.replaceState(history.state, '', url);
                return;
            }

            // Keep Back available for the state from before the search started.
            const state = { ...history.state, catalogSearch: resultsId };
            history.replaceState(state, '');
            history.pushState(state, '', url);
            hasSearchHistory = true;
        }

        function searchUrl() {
            const url = new URL(form.action);
            url.search = new URLSearchParams(new FormData(form));
            return url;
        }

        function clearValue(control) {
            return clearUrl.searchParams.get(control.name) ??
                (control instanceof HTMLSelectElement ? (control.options[0]?.value ?? '') : '');
        }

        function setClearVisible(visible) {
            if (!clear) return;

            clear.hidden = !visible;
            if (clearButton) clearButton.disabled = !visible;
        }

        async function search(url = searchUrl()) {
            timer = undefined;
            const current = document.getElementById(resultsId);
            if (!current || url.href === location.href) return;

            const controller = new AbortController();
            request = controller;
            current.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(url, {
                    headers: { 'X-Catalog-Fragment': resultsId },
                    signal: controller.signal,
                    cache: 'no-store',
                });
                if (!response.ok || response.redirected) throw new Error('Search response unavailable');

                const documentResponse = new DOMParser().parseFromString(await response.text(), 'text/html');
                const updated = documentResponse.getElementById(resultsId);
                if (controller.signal.aborted) return;
                if (!updated) throw new Error('Search results missing');
                if (document.querySelector('dialog[open], .swal2-container')) {
                    status.classList.remove('sr-only');
                    status.textContent = 'Pulsa Buscar para actualizar los resultados.';
                    return;
                }

                const previousFocus = document.activeElement;
                const restoreFocus = current.contains(previousFocus);
                const previousTable = current.querySelector('[data-table-scroll]');
                const nextTable = updated.querySelector('[data-table-scroll]');
                tableScrollLeft = previousTable?.scrollLeft ?? tableScrollLeft;
                updateHistory(url);
                current.replaceWith(updated);
                if (nextTable) nextTable.scrollLeft = tableScrollLeft;
                if (restoreFocus) {
                    const focusTarget = previousFocus === previousTable && nextTable ? nextTable : updated;
                    focusTarget.focus({ preventScroll: true });
                }
                if (clear) {
                    const noFilters = updated.dataset.hasActiveFilters === '0';
                    if (noFilters && clear.contains(previousFocus)) field.focus({ preventScroll: true });
                    setClearVisible(!noFilters);
                }
                status.classList.add('sr-only');
                status.textContent = updated.dataset.searchStatus || 'Resultados actualizados';
            } catch {
                if (controller.signal.aborted) return;
                status.classList.remove('sr-only');
                status.textContent = 'No se pudo actualizar la búsqueda. Pulsa Buscar para reintentar.';
            } finally {
                if (request === controller) {
                    current.removeAttribute('aria-busy');
                    request = null;
                }
            }
        }

        function queueSearch() {
            stopSearch();
            if (clearFields.some(control => control.value !== clearValue(control))) setClearVisible(true);
            timer = setTimeout(search, 300);
        }

        field.addEventListener('input', event => {
            if (event.isComposing) {
                stopSearch();
                return;
            }
            queueSearch();
        });
        field.addEventListener('compositionend', queueSearch);
        form.addEventListener('change', event => {
            if (event.target instanceof HTMLSelectElement) queueSearch();
        });
        clearForm?.addEventListener('submit', event => {
            event.preventDefault();
            stopSearch();
            for (const control of clearFields) control.value = clearValue(control);
            field.focus({ preventScroll: true });
            if (clearUrl.href === location.href) setClearVisible(false);
            search(clearUrl);
        });
        form.addEventListener('submit', stopSearch);
        document.addEventListener('open-modal', () => {
            if (!timer && !request) return;

            stopSearch();
            status.classList.remove('sr-only');
            status.textContent = 'Pulsa Buscar para actualizar los resultados.';
        });
        window.addEventListener('pagehide', stopSearch);
        window.addEventListener('popstate', event => {
            if (event.state?.catalogSearch !== resultsId) return;

            stopSearch();
            location.reload();
        });
    }
})();
