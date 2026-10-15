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

    function attachSelectFilter(select, placeholder, searchInput = null) {
        const seen = new Set();
        select._allOptions = Array.from(select.options)
            .map((option) => ({ value: option.value, text: option.textContent }))
            .filter((option) => option.value !== '' && !seen.has(option.value) && seen.add(option.value));
        select.required = false;
        select.classList.add('d-none');
        let input = select._filterInput;
        if (!input) {
            input = searchInput || document.createElement('input');
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
            if (!searchInput) select.parentNode.insertBefore(input, select);
            select.parentNode.insertBefore(results, select);
            select._filterInput = input;
            select._results = results;

            const choose = (option) => {
                select.innerHTML = `<option value="${escapeHtml(option.value)}">${escapeHtml(option.text)}</option>`;
                select.value = option.value;
                input.value = option.text;
                input.setCustomValidity('');
                results.classList.add('d-none');
                select.dispatchEvent(new Event('change', { bubbles: true }));
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
                input.setCustomValidity('Selecciona una opción de la lista.');
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

    function renderRecordTypeOptions(container, types, selectedIds = [], complexityByType = {}) {
        const selected = new Set(selectedIds.map(Number));
        const orderedTypes = [
            ...selectedIds.map((id) => types.find((type) => Number(type.id_tipo_registro) === Number(id))).filter(Boolean),
            ...types.filter((type) => !selected.has(Number(type.id_tipo_registro))),
        ];

        container.innerHTML = orderedTypes.map((type) => {
            const score = complexityByType[type.id_tipo_registro];
            const scoreLabel = score === undefined || score === null
                ? 'Falta registrar su puntaje promedio'
                : `Promedio registrado: ${Number(score).toFixed(2)} puntos`;

            return `<div class="col">
                <label class="record-type-option d-flex align-items-start gap-2 border rounded p-3 h-100 m-0" for="${container.id}-${type.id_tipo_registro}">
                    <input class="form-check-input flex-shrink-0 m-0 mt-1" type="checkbox" value="${type.id_tipo_registro}" id="${container.id}-${type.id_tipo_registro}" ${selected.has(Number(type.id_tipo_registro)) ? 'checked' : ''}>
                    <span class="fw-semibold">${escapeHtml(type.nombre)}<span class="d-block small text-muted fw-normal mt-1">${escapeHtml(scoreLabel)}</span></span>
                </label>
            </div>`;
        }).join('');
        container.querySelectorAll('.record-type-option').forEach((option) => {
            const checkbox = option.querySelector('input[type="checkbox"]');
            option.style.cursor = 'pointer';
            option.classList.toggle('border-primary', checkbox.checked);
            option.classList.toggle('bg-primary-subtle', checkbox.checked);
            checkbox.addEventListener('change', () => {
                option.classList.toggle('border-primary', checkbox.checked);
                option.classList.toggle('bg-primary-subtle', checkbox.checked);
            });
        });
    }

    function selectedRecordTypeIds(container) {
        return Array.from(container.querySelectorAll('input[type="checkbox"]:checked'), (input) => Number(input.value));
    }

    function updateRecordTypeSummary(container, hiddenInput, summary, complexityByType) {
        const selectedIds = selectedRecordTypeIds(container);
        hiddenInput.value = selectedIds[0] || '';
        if (!selectedIds.length) {
            summary.textContent = 'Selecciona uno o más tipos de registro.';
            return selectedIds;
        }

        const hasMissingScore = selectedIds.some((id) => complexityByType[id] === undefined || complexityByType[id] === null);
        if (hasMissingScore) {
            summary.textContent = `${selectedIds.length} tipo(s) seleccionado(s). No se puede calcular el total: falta registrar el puntaje promedio de uno o más tipos en “Registrar complejidad por tipo”.`;
        } else {
            const total = selectedIds.reduce((sum, id) => sum + Number(complexityByType[id]), 0);
            summary.textContent = `${selectedIds.length} tipo(s) seleccionado(s). Complejidad estimada total: ${total.toFixed(2)} puntos (suma de promedios).`;
        }

        return selectedIds;
    }
