window.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('job_crew_search');
    const hiddenEmployeeId = document.getElementById('job_crew_employee_id');
    const suggestions = document.getElementById('job_crew_suggestions');
    const lookupUrlInput = document.getElementById('job_crew_lookup_url');

    if (!searchInput || !hiddenEmployeeId || !suggestions || !lookupUrlInput) {
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
        suggestions.classList.add('d-none');
        suggestions.innerHTML = '';
    };

    const renderSuggestions = (items) => {
        suggestions.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            hideSuggestions();
            return;
        }

        items.forEach((item) => {
            const id = String(item.id || '');
            if (!id) {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';

            const label = (item.name || `Employee #${id}`).toString();
            const title = document.createElement('div');
            title.className = 'fw-semibold';
            title.textContent = label;
            button.appendChild(title);

            const email = (item.email || '').toString().trim();
            const phone = (item.phone || '').toString().trim();
            const metaText = [phone, email].filter(Boolean).join(' • ');
            if (metaText !== '') {
                const meta = document.createElement('small');
                meta.className = 'text-muted';
                meta.textContent = metaText;
                button.appendChild(meta);
            }

            button.addEventListener('click', () => {
                searchInput.value = label;
                hiddenEmployeeId.value = id;
                hideSuggestions();
            });

            suggestions.appendChild(button);
        });

        if (suggestions.children.length === 0) {
            hideSuggestions();
            return;
        }

        suggestions.classList.remove('d-none');
    };

    const search = debounce(async () => {
        const q = searchInput.value.trim();
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

    searchInput.addEventListener('input', () => {
        hiddenEmployeeId.value = '';
        search();
    });

    document.addEventListener('click', (event) => {
        if (!suggestions.contains(event.target) && event.target !== searchInput) {
            hideSuggestions();
        }
    });
});
