window.addEventListener('DOMContentLoaded', () => {
    const lookupInput = document.getElementById('user_employee_lookup_url');
    const currentUserInput = document.getElementById('user_employee_current_user_id');
    const searchInput = document.getElementById('user_employee_search');
    const employeeIdInput = document.getElementById('user_employee_id');
    const suggestionsBox = document.getElementById('user_employee_suggestions');
    const errorBox = document.getElementById('user_employee_error');
    const form = searchInput ? searchInput.closest('form') : null;

    if (!lookupInput || !searchInput || !employeeIdInput || !suggestionsBox || !form) {
        return;
    }

    const lookupUrl = lookupInput.value || '';
    const currentUserId = parseInt((currentUserInput && currentUserInput.value) || '0', 10) || 0;
    if (!lookupUrl) {
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
        suggestionsBox.classList.add('d-none');
        suggestionsBox.innerHTML = '';
    };

    const showError = (message) => {
        if (!errorBox) {
            return;
        }

        const text = (message || '').toString().trim();
        if (text === '') {
            errorBox.textContent = '';
            errorBox.classList.add('d-none');
            return;
        }

        errorBox.textContent = text;
        errorBox.classList.remove('d-none');
    };

    const renderSuggestions = (items) => {
        suggestionsBox.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            hideSuggestions();
            return;
        }

        items.forEach((item) => {
            const itemId = parseInt((item && item.id) || '0', 10) || 0;
            if (itemId <= 0) {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';

            const title = document.createElement('div');
            title.className = 'fw-semibold';
            title.textContent = (item.name || '').toString().trim() || `Employee #${itemId}`;
            button.appendChild(title);

            const metaParts = [];
            const email = (item.email || '').toString().trim();
            const phone = (item.phone || '').toString().trim();
            if (email) {
                metaParts.push(email);
            }
            if (phone) {
                metaParts.push(phone);
            }

            const linkedUserId = parseInt((item.linked_user_id ?? 0).toString(), 10) || 0;
            if (linkedUserId > 0) {
                const linkedName = (item.linked_user_name || '').toString().trim() || `User #${linkedUserId}`;
                if (linkedUserId === currentUserId) {
                    metaParts.push('Already linked to this user');
                } else {
                    metaParts.push(`Currently linked to ${linkedName}`);
                }
            }

            if (metaParts.length > 0) {
                const meta = document.createElement('small');
                meta.className = 'text-muted d-block';
                meta.textContent = metaParts.join(' • ');
                button.appendChild(meta);
            }

            button.addEventListener('click', () => {
                employeeIdInput.value = String(itemId);
                searchInput.value = (item.name || '').toString().trim() || `Employee #${itemId}`;
                hideSuggestions();
                showError('');
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
        employeeIdInput.value = '';
        showError('');
        fetchSuggestions();
    });

    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim() !== '') {
            fetchSuggestions();
        }
    });

    form.addEventListener('submit', (event) => {
        if ((employeeIdInput.value || '').trim() === '') {
            event.preventDefault();
            showError('Select an employee from the suggestion list first.');
            return;
        }

        showError('');
    });

    document.addEventListener('click', (event) => {
        if (!suggestionsBox.contains(event.target) && event.target !== searchInput) {
            hideSuggestions();
        }
    });
});
