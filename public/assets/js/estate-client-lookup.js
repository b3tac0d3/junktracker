window.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('client_name');
    const hiddenId = document.getElementById('client_id');
    const box = document.getElementById('client_name_suggestions');
    const lookupUrlInput = document.getElementById('client_lookup_url');

    if (!input || !hiddenId || !box || !lookupUrlInput) {
        return;
    }

    const lookupUrl = lookupUrlInput.value || '';
    if (!lookupUrl) {
        return;
    }

    const debounce = (fn, delay = 200) => {
        let timer = null;
        return (...args) => {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(() => fn(...args), delay);
        };
    };

    const hideSuggestions = () => {
        box.classList.add('d-none');
        box.innerHTML = '';
    };

    const renderSuggestions = (items) => {
        if (!Array.isArray(items) || items.length === 0) {
            hideSuggestions();
            return;
        }

        box.innerHTML = '';
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';

            const name = document.createElement('div');
            name.className = 'fw-semibold';
            name.textContent = (item.label || '').toString();
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
                input.value = (item.label || '').toString();
                hiddenId.value = item.id ? String(item.id) : '';
                hideSuggestions();
            });

            box.appendChild(button);
        });

        box.classList.remove('d-none');
    };

    const searchClients = debounce(async () => {
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
        searchClients();
    });

    document.addEventListener('click', (event) => {
        if (!box.contains(event.target) && event.target !== input) {
            hideSuggestions();
        }
    });
});
