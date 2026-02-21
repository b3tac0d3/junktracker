window.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('company_name');
    const hiddenId = document.getElementById('company_id');
    const box = document.getElementById('company_name_suggestions');
    const lookupInput = document.getElementById('contact_company_lookup_url');

    if (!input || !hiddenId || !box || !lookupInput) {
        return;
    }

    const lookupUrl = (lookupInput.value || '').toString().trim();
    if (lookupUrl === '') {
        return;
    }

    const debounce = (fn, delay = 180) => {
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
                input.value = (item.name || '').toString();
                hiddenId.value = item.id ? String(item.id) : '';
                hideSuggestions();
            });

            box.appendChild(button);
        });

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

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (hiddenId.value === '' && input.value.trim() === '') {
                hideSuggestions();
            }
        }, 120);
    });

    document.addEventListener('click', (event) => {
        if (!box.contains(event.target) && event.target !== input) {
            hideSuggestions();
        }
    });
});
