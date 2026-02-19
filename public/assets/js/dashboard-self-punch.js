window.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('dashboardSelfPunchForm');
    const lookupUrlInput = document.getElementById('dashboard_self_job_lookup_url');
    const jobSearchInput = document.getElementById('dashboard_self_job_search');
    const jobIdInput = document.getElementById('dashboard_self_job_id');
    const suggestionsBox = document.getElementById('dashboard_self_job_suggestions');
    const modalRoot = document.getElementById('dashboardPunchInModal');

    if (!form || !lookupUrlInput || !jobSearchInput || !jobIdInput || !suggestionsBox) {
        return;
    }

    const lookupUrl = (lookupUrlInput.value || '').trim();
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

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';

            const name = (item.name || `Job #${id}`).toString().trim();
            const title = document.createElement('div');
            title.className = 'fw-semibold';
            title.textContent = `#${id} - ${name}`;
            button.appendChild(title);

            const city = (item.city || '').toString().trim();
            const state = (item.state || '').toString().trim();
            const status = (item.job_status || '').toString().trim();
            const location = city && state ? `${city}, ${state}` : (city || state);
            const metaParts = [location, status].filter(Boolean);
            if (metaParts.length > 0) {
                const meta = document.createElement('small');
                meta.className = 'text-muted d-block';
                meta.textContent = metaParts.join(' • ');
                button.appendChild(meta);
            }

            button.addEventListener('click', () => {
                jobIdInput.value = String(id);
                jobSearchInput.value = `#${id} - ${name}`;
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
        const query = jobSearchInput.value.trim();
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

    jobSearchInput.addEventListener('input', () => {
        jobIdInput.value = '';
        fetchSuggestions();
    });

    jobSearchInput.addEventListener('focus', () => {
        if (jobSearchInput.value.trim() !== '') {
            fetchSuggestions();
        }
    });

    form.addEventListener('submit', () => {
        if (jobIdInput.value.trim() !== '') {
            return;
        }

        // No selected job means non-job time.
        jobSearchInput.value = '';
        hideSuggestions();
    });

    document.addEventListener('click', (event) => {
        if (!suggestionsBox.contains(event.target) && event.target !== jobSearchInput) {
            hideSuggestions();
        }
    });

    if (modalRoot) {
        modalRoot.addEventListener('shown.bs.modal', () => {
            jobSearchInput.focus();
        });

        modalRoot.addEventListener('hidden.bs.modal', () => {
            jobIdInput.value = '';
            jobSearchInput.value = '';
            hideSuggestions();
        });
    }
});
