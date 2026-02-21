window.addEventListener('DOMContentLoaded', () => {
    if (typeof bootstrap === 'undefined') {
        return;
    }

    const modalElement = document.getElementById('openClockPunchInModal');
    const form = document.getElementById('openClockPunchInForm');
    const employeeIdInput = document.getElementById('open_clock_punch_employee_id');
    const employeeNameLabel = document.getElementById('openClockPunchInModalLabel');
    const lookupUrlInput = document.getElementById('open_clock_job_lookup_url');
    const jobSearchInput = document.getElementById('open_clock_punch_job_search');
    const jobIdInput = document.getElementById('open_clock_punch_job_id');
    const suggestionsBox = document.getElementById('open_clock_punch_job_suggestions');
    const triggers = Array.from(document.querySelectorAll('.js-open-clock-punch-in'));

    if (
        !modalElement
        || !form
        || !employeeIdInput
        || !employeeNameLabel
        || !lookupUrlInput
        || !jobSearchInput
        || !jobIdInput
        || !suggestionsBox
        || triggers.length === 0
    ) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);
    const lookupUrl = (lookupUrlInput.value || '').trim();
    let activeEmployeeName = '';

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

    const setModalTitle = () => {
        const label = activeEmployeeName !== '' ? `Punch In: ${activeEmployeeName}` : 'Punch In';
        employeeNameLabel.textContent = label;
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

            const name = (item.name || `Job #${id}`).toString().trim();
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.innerHTML = `<div class="fw-semibold">#${id} - ${name}</div>`;

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
        if (query.length < 1 || lookupUrl === '') {
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

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const employeeId = (trigger.getAttribute('data-employee-id') || '').trim();
            activeEmployeeName = (trigger.getAttribute('data-employee-name') || '').trim();
            employeeIdInput.value = employeeId;
            setModalTitle();
            modal.show();
        });
    });

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
        jobSearchInput.value = '';
        hideSuggestions();
    });

    document.addEventListener('click', (event) => {
        if (!suggestionsBox.contains(event.target) && event.target !== jobSearchInput) {
            hideSuggestions();
        }
    });

    modalElement.addEventListener('shown.bs.modal', () => {
        jobSearchInput.focus();
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        employeeIdInput.value = '';
        activeEmployeeName = '';
        setModalTitle();
        jobIdInput.value = '';
        jobSearchInput.value = '';
        hideSuggestions();
    });
});
