window.addEventListener('DOMContentLoaded', () => {
    const typeInput = document.getElementById('type');
    if (!typeInput) {
        return;
    }

    const debounce = (fn, delay = 180) => {
        let timer = null;
        return (...args) => {
            if (timer) {
                window.clearTimeout(timer);
            }
            timer = window.setTimeout(() => fn(...args), delay);
        };
    };

    const setupScrapYardLookup = () => {
        const nameInput = document.getElementById('scrap_yard_name');
        const hiddenId = document.getElementById('disposal_location_id');
        const lookupUrlInput = document.getElementById('scrap_yard_lookup_url');
        const box = document.getElementById('scrap_yard_suggestions');
        if (!nameInput || !hiddenId || !lookupUrlInput || !box) {
            return { toggle: () => {} };
        }

        const lookupUrl = lookupUrlInput.value || '';
        if (!lookupUrl) {
            return { toggle: () => {} };
        }

        const hideSuggestions = () => {
            box.classList.add('d-none');
            box.innerHTML = '';
        };

        const renderSuggestions = (items) => {
            box.innerHTML = '';

            if (!Array.isArray(items) || items.length === 0) {
                hideSuggestions();
                return;
            }

            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';

                const name = document.createElement('div');
                name.className = 'fw-semibold';
                name.textContent = (item.name || '').toString();
                button.appendChild(name);

                const city = (item.city || '').toString().trim();
                const state = (item.state || '').toString().trim();
                if (city || state) {
                    const meta = document.createElement('small');
                    meta.className = 'text-muted';
                    meta.textContent = city && state ? `${city}, ${state}` : (city || state);
                    button.appendChild(meta);
                }

                button.addEventListener('click', () => {
                    nameInput.value = (item.name || '').toString();
                    hiddenId.value = item.id ? String(item.id) : '';
                    hideSuggestions();
                });

                box.appendChild(button);
            });

            box.classList.remove('d-none');
        };

        const search = debounce(async () => {
            if (typeInput.value !== 'scrap') {
                hideSuggestions();
                return;
            }

            const q = nameInput.value.trim();
            if (q.length < 2) {
                hideSuggestions();
                return;
            }

            try {
                const response = await fetch(`${lookupUrl}?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) {
                    hideSuggestions();
                    return;
                }

                const items = await response.json();
                renderSuggestions(items);
            } catch (_error) {
                hideSuggestions();
            }
        }, 180);

        nameInput.addEventListener('input', () => {
            hiddenId.value = '';
            search();
        });

        document.addEventListener('click', (event) => {
            if (!box.contains(event.target) && event.target !== nameInput) {
                hideSuggestions();
            }
        });

        return {
            toggle: () => {
                const isScrap = typeInput.value === 'scrap';
                nameInput.disabled = !isScrap;

                if (!isScrap) {
                    nameInput.value = '';
                    hiddenId.value = '';
                    hideSuggestions();
                    return;
                }

                if (nameInput.value.trim() !== '') {
                    search();
                }
            },
        };
    };

    const setupJobLookup = () => {
        const group = document.getElementById('sale_job_lookup_group');
        const input = document.getElementById('job_search');
        const hiddenId = document.getElementById('job_id');
        const lookupUrlInput = document.getElementById('sale_job_lookup_url');
        const box = document.getElementById('sale_job_suggestions');
        if (!group || !input || !hiddenId || !lookupUrlInput || !box) {
            return { toggle: () => {} };
        }

        const lookupUrl = lookupUrlInput.value || '';
        if (!lookupUrl) {
            return { toggle: () => {} };
        }

        const hideSuggestions = () => {
            box.classList.add('d-none');
            box.innerHTML = '';
        };

        const renderSuggestions = (items) => {
            box.innerHTML = '';

            if (!Array.isArray(items) || items.length === 0) {
                hideSuggestions();
                return;
            }

            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';

                const line1 = document.createElement('div');
                line1.className = 'fw-semibold';
                const itemName = (item.name || '').toString().trim();
                line1.textContent = itemName !== '' ? itemName : `Job #${item.id || ''}`;
                button.appendChild(line1);

                const city = (item.city || '').toString().trim();
                const state = (item.state || '').toString().trim();
                const location = city && state ? `${city}, ${state}` : (city || state);
                const status = (item.job_status || '').toString().trim();
                const metaBits = [`#${(item.id || '').toString()}`];
                if (location !== '') {
                    metaBits.push(location);
                }
                if (status !== '') {
                    metaBits.push(status);
                }

                const line2 = document.createElement('small');
                line2.className = 'text-muted';
                line2.textContent = metaBits.join(' • ');
                button.appendChild(line2);

                button.addEventListener('click', () => {
                    hiddenId.value = item.id ? String(item.id) : '';
                    input.value = line1.textContent || '';
                    hideSuggestions();
                });

                box.appendChild(button);
            });

            box.classList.remove('d-none');
        };

        const search = debounce(async () => {
            if (typeInput.value !== 'scrap') {
                hideSuggestions();
                return;
            }

            const q = input.value.trim();
            if (q.length < 2) {
                hideSuggestions();
                return;
            }

            try {
                const response = await fetch(`${lookupUrl}?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) {
                    hideSuggestions();
                    return;
                }

                const items = await response.json();
                renderSuggestions(items);
            } catch (_error) {
                hideSuggestions();
            }
        }, 180);

        input.addEventListener('input', () => {
            hiddenId.value = '';
            search();
        });

        document.addEventListener('click', (event) => {
            if (!box.contains(event.target) && event.target !== input) {
                hideSuggestions();
            }
        });

        return {
            toggle: () => {
                const isScrap = typeInput.value === 'scrap';
                group.classList.toggle('d-none', !isScrap);
                input.disabled = !isScrap;

                if (!isScrap) {
                    hideSuggestions();
                    return;
                }

                if (input.value.trim() !== '' && hiddenId.value === '') {
                    search();
                }
            },
        };
    };

    const scrapYard = setupScrapYardLookup();
    const jobs = setupJobLookup();

    const toggleByType = () => {
        scrapYard.toggle();
        jobs.toggle();
    };

    typeInput.addEventListener('change', toggleByType);
    toggleByType();
});
