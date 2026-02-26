window.addEventListener('DOMContentLoaded', () => {
    const lookupInput = document.getElementById('task_owner_lookup_url');
    const searchInput = document.getElementById('task_owner_search');
    const ownerIdInput = document.getElementById('assigned_user_id');
    const suggestionsBox = document.getElementById('task_owner_suggestions');
    const form = searchInput ? searchInput.closest('form') : null;

    if (!lookupInput || !searchInput || !ownerIdInput || !suggestionsBox || !form) {
        return;
    }

    const lookupUrl = (lookupInput.value || '').trim();
    if (lookupUrl === '') {
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
        suggestionsBox.classList.add('d-none');
        suggestionsBox.innerHTML = '';
    };

    const renderSuggestions = (items) => {
        suggestionsBox.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            hideSuggestions();
            return;
        }

        items.forEach((item) => {
            const id = parseInt((item && item.id) || '0', 10) || 0;
            if (id <= 0) {
                return;
            }

            const label = ((item && item.label) || '').toString().trim();
            const meta = ((item && item.meta) || '').toString().trim();

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';

            const title = document.createElement('div');
            title.className = 'fw-semibold';
            title.textContent = label !== '' ? label : `User #${id}`;
            button.appendChild(title);

            if (meta !== '') {
                const metaLine = document.createElement('small');
                metaLine.className = 'text-muted d-block';
                metaLine.textContent = meta;
                button.appendChild(metaLine);
            }

            button.addEventListener('click', () => {
                ownerIdInput.value = String(id);
                searchInput.value = label !== '' ? label : `User #${id}`;
                searchInput.setCustomValidity('');
                hideSuggestions();
            });

            suggestionsBox.appendChild(button);
        });

        if (suggestionsBox.children.length === 0) {
            hideSuggestions();
            return;
        }

        suggestionsBox.classList.remove('d-none');
    };

    const fetchSuggestions = debounce(async () => {
        const query = searchInput.value.trim();
        if (query.length < 1) {
            hideSuggestions();
            return;
        }

        try {
            const response = await fetch(`${lookupUrl}?q=${encodeURIComponent(query)}`, {
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

    searchInput.addEventListener('input', () => {
        ownerIdInput.value = '';
        searchInput.setCustomValidity('');
        fetchSuggestions();
    });

    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim() !== '') {
            fetchSuggestions();
        }
    });

    form.addEventListener('submit', (event) => {
        const ownerSearch = searchInput.value.trim();
        const ownerId = ownerIdInput.value.trim();
        if (ownerSearch !== '' && ownerId === '') {
            event.preventDefault();
            searchInput.setCustomValidity('Select an owner from suggestions or clear owner.');
            searchInput.reportValidity();
            return;
        }

        searchInput.setCustomValidity('');
    });

    document.addEventListener('click', (event) => {
        if (!suggestionsBox.contains(event.target) && event.target !== searchInput) {
            hideSuggestions();
        }
    });
});
