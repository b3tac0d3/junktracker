window.addEventListener('DOMContentLoaded', () => {
    const lookupUrlInput = document.getElementById('client_duplicate_lookup_url');
    const showBaseUrlInput = document.getElementById('client_show_base_url');
    const panel = document.getElementById('client_duplicate_matches_live');
    const list = document.getElementById('client_duplicate_matches_live_list');

    const firstNameInput = document.getElementById('first_name');
    const lastNameInput = document.getElementById('last_name');
    const phoneInput = document.getElementById('phone');
    const emailInput = document.getElementById('email');
    const zipInput = document.getElementById('zip');

    if (!lookupUrlInput || !showBaseUrlInput || !panel || !list || !firstNameInput || !lastNameInput || !phoneInput || !emailInput || !zipInput) {
        return;
    }

    const lookupUrl = lookupUrlInput.value || '';
    const showBaseUrl = showBaseUrlInput.value || '';
    if (!lookupUrl || !showBaseUrl) {
        return;
    }

    let controller = null;

    const debounce = (fn, delay = 220) => {
        let timer = null;
        return (...args) => {
            if (timer) {
                window.clearTimeout(timer);
            }
            timer = window.setTimeout(() => fn(...args), delay);
        };
    };

    const normalizePhone = (value) => {
        const digits = (value || '').replace(/\D+/g, '');
        if (digits.length > 10) {
            return digits.slice(-10);
        }
        return digits;
    };

    const hideMatches = () => {
        panel.classList.add('d-none');
        list.innerHTML = '';
    };

    const statusBadge = (status) => {
        const badge = document.createElement('span');
        badge.className = 'badge';

        if (status === 'deleted') {
            badge.classList.add('bg-danger');
            badge.textContent = 'Deleted';
            return badge;
        }

        if (status === 'inactive') {
            badge.classList.add('bg-secondary');
            badge.textContent = 'Inactive';
            return badge;
        }

        badge.classList.add('bg-success');
        badge.textContent = 'Active';
        return badge;
    };

    const buildLocation = (item) => {
        const city = (item.city || '').toString().trim();
        const state = (item.state || '').toString().trim();
        if (city && state) {
            return `${city}, ${state}`;
        }
        return city || state || '';
    };

    const renderMatches = (matches) => {
        list.innerHTML = '';

        if (!Array.isArray(matches) || matches.length === 0) {
            hideMatches();
            return;
        }

        matches.forEach((item) => {
            const row = document.createElement('a');
            row.className = 'list-group-item list-group-item-action';
            row.href = `${showBaseUrl}/${encodeURIComponent(String(item.id || '0'))}`;

            const top = document.createElement('div');
            top.className = 'd-flex align-items-center justify-content-between gap-2';

            const name = document.createElement('div');
            name.className = 'fw-semibold';
            name.textContent = (item.display_name || `Client #${item.id || ''}`).toString();
            top.appendChild(name);
            top.appendChild(statusBadge((item.status || 'active').toString()));

            row.appendChild(top);

            const reasons = Array.isArray(item.match_reasons) ? item.match_reasons.join(', ') : '';
            if (reasons) {
                const reasonsText = document.createElement('div');
                reasonsText.className = 'small text-muted';
                reasonsText.textContent = `Matched on: ${reasons}`;
                row.appendChild(reasonsText);
            }

            const metaParts = [];
            const phone = (item.phone || '').toString().trim();
            const email = (item.email || '').toString().trim();
            const location = buildLocation(item);

            if (phone) {
                metaParts.push(phone);
            }
            if (email) {
                metaParts.push(email);
            }
            if (location) {
                metaParts.push(location);
            }

            if (metaParts.length > 0) {
                const meta = document.createElement('div');
                meta.className = 'small text-muted';
                meta.textContent = metaParts.join('  |  ');
                row.appendChild(meta);
            }

            list.appendChild(row);
        });

        panel.classList.remove('d-none');
    };

    const runSearch = debounce(async () => {
        const firstName = firstNameInput.value.trim();
        const lastName = lastNameInput.value.trim();
        const phone = normalizePhone(phoneInput.value);
        const email = emailInput.value.trim();
        const zip = zipInput.value.trim();

        const hasEmail = email.length >= 5 && email.includes('@');
        const hasPhone = phone.length >= 7;
        const hasName = firstName.length >= 2 && lastName.length >= 2;

        if (!hasEmail && !hasPhone && !hasName) {
            hideMatches();
            return;
        }

        if (controller) {
            controller.abort();
        }
        controller = new AbortController();

        const params = new URLSearchParams({
            first_name: firstName,
            last_name: lastName,
            phone,
            email,
            zip,
        });

        try {
            const response = await fetch(`${lookupUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (!response.ok) {
                hideMatches();
                return;
            }

            const payload = await response.json();
            const matches = payload && Array.isArray(payload.matches) ? payload.matches : [];
            renderMatches(matches);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            hideMatches();
        }
    }, 240);

    [firstNameInput, lastNameInput, phoneInput, emailInput, zipInput].forEach((input) => {
        input.addEventListener('input', runSearch);
        input.addEventListener('blur', runSearch);
    });

    runSearch();
});
