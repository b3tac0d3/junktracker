window.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('job_search');
    const hiddenId = document.getElementById('job_id');
    const box = document.getElementById('expense_job_suggestions');
    const lookupUrlInput = document.getElementById('expense_job_lookup_url');

    if (!input || !hiddenId || !box || !lookupUrlInput) {
        return;
    }

    const lookupUrl = lookupUrlInput.value || '';
    if (!lookupUrl) {
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
            const id = (item.id || '').toString();
            if (id === '') {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';

            const label = (item.name || `Job #${id}`).toString();
            const title = document.createElement('div');
            title.className = 'fw-semibold';
            title.textContent = label;
            button.appendChild(title);

            const city = (item.city || '').toString().trim();
            const state = (item.state || '').toString().trim();
            const status = (item.job_status || '').toString().trim();
            const parts = [city && state ? `${city}, ${state}` : (city || state), status].filter(Boolean);
            if (parts.length > 0) {
                const meta = document.createElement('small');
                meta.className = 'text-muted';
                meta.textContent = parts.join(' • ');
                button.appendChild(meta);
            }

            button.addEventListener('click', () => {
                input.value = label;
                hiddenId.value = id;
                hideSuggestions();
            });

            box.appendChild(button);
        });

        if (box.children.length === 0) {
            hideSuggestions();
            return;
        }

        box.classList.remove('d-none');
    };

    const search = debounce(async () => {
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
});
