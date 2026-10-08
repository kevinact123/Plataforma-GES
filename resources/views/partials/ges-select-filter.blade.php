    function normalizeSearch(text) {
        return String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    function matchesSearch(text, query) {
        const words = normalizeSearch(text).split(/[\s·]+/).filter(Boolean).flatMap((word) => [word, word.replace(/[.\-]/g, '')]);
        return normalizeSearch(query).split(/\s+/).filter(Boolean).every((token) => {
            const compact = token.replace(/[.\-]/g, '');
            return words.some((word) => word.startsWith(token) || (compact && word.startsWith(compact)));
        });
    }

    function attachSelectFilter(select, placeholder) {
        const seen = new Set();
        select._allOptions = Array.from(select.options)
            .map((option) => ({ value: option.value, text: option.textContent }))
            .filter((option) => option.value !== '' && !seen.has(option.value) && seen.add(option.value));
        select.required = false;
        select.classList.add('d-none');
        let input = select._filterInput;
        if (!input) {
            input = document.createElement('input');
            input.type = 'search';
            input.className = 'form-control';
            input.placeholder = placeholder;
            input.autocomplete = 'off';
            input.required = true;
            input.setAttribute('aria-label', placeholder);
            const results = document.createElement('div');
            results.className = 'list-group position-absolute w-100 shadow-sm d-none';
            results.style.cssText = 'z-index:1055;max-height:260px;overflow-y:auto;';
            select.parentNode.classList.add('position-relative');
            select.parentNode.insertBefore(input, select);
            select.parentNode.insertBefore(results, select);
            select._filterInput = input;
            select._results = results;

            const choose = (option) => {
                select.innerHTML = `<option value="${escapeHtml(option.value)}">${escapeHtml(option.text)}</option>`;
                select.value = option.value;
                input.value = option.text;
                input.setCustomValidity('');
                results.classList.add('d-none');
            };
            const render = () => {
                const query = input.value.trim();
                const matches = query ? select._allOptions.filter((option) => matchesSearch(option.text, query)) : [];
                if (!query) { results.classList.add('d-none'); return; }
                results.innerHTML = matches.length
                    ? matches.slice(0, 50).map((option) => `<button type="button" class="list-group-item list-group-item-action" data-value="${escapeHtml(option.value)}">${escapeHtml(option.text)}</button>`).join('')
                    : '<div class="list-group-item text-muted">Sin coincidencias</div>';
                results.classList.remove('d-none');
            };
            input.addEventListener('input', () => {
                select.innerHTML = '<option value=""></option>';
                select.value = '';
                input.setCustomValidity('Selecciona un paciente de la lista.');
                render();
            });
            input.addEventListener('focus', () => { if (!select.value) render(); });
            results.addEventListener('mousedown', (event) => {
                const button = event.target.closest('[data-value]');
                if (!button) return;
                event.preventDefault();
                choose(select._allOptions.find((option) => option.value === button.dataset.value));
            });
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    const first = results.querySelector('[data-value]');
                    if (first && !select.value) { event.preventDefault(); first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); }
                }
                if (event.key === 'Escape') results.classList.add('d-none');
            });
            input.addEventListener('blur', () => results.classList.add('d-none'));
            select.form?.addEventListener('reset', () => setTimeout(() => {
                input.value = '';
                select.innerHTML = '<option value=""></option>';
                input.setCustomValidity('');
                results.classList.add('d-none');
            }));
        }

        const preselected = select._allOptions.find((option) => option.value === select.value);
        if (preselected) {
            input.value = preselected.text;
            input.setCustomValidity('');
        } else if (!select.value) {
            input.value = '';
        }
    }

    function fillSelect(id, items, labelBuilder, valueBuilder = null) {
        const select = document.getElementById(id);
        select.innerHTML += items.map((item) => `<option value="${valueBuilder ? valueBuilder(item) : item[id]}">${escapeHtml(labelBuilder(item))}</option>`).join('');
    }
