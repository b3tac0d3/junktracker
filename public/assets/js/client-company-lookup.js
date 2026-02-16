window.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('company_name');
    const hiddenId = document.getElementById('company_id');
    const box = document.getElementById('company_name_suggestions');
    const lookupUrlInput = document.getElementById('company_lookup_url');
    const createUrlInput = document.getElementById('company_create_url');

    const modalEl = document.getElementById('addCompanyModal');
    const addBtn = document.getElementById('add_company_btn');
    const saveBtn = document.getElementById('save_new_company_btn');
    const errorBox = document.getElementById('company_create_error');

    const newName = document.getElementById('new_company_name');
    const newPhone = document.getElementById('new_company_phone');
    const newWeb = document.getElementById('new_company_web_address');
    const newCity = document.getElementById('new_company_city');
    const newState = document.getElementById('new_company_state');
    const newNote = document.getElementById('new_company_note');

    if (!input || !hiddenId || !box || !lookupUrlInput || !createUrlInput || !modalEl || !saveBtn || !newName) {
        return;
    }

    const lookupUrl = lookupUrlInput.value || '';
    const createUrl = createUrlInput.value || '';
    if (!lookupUrl || !createUrl) {
        return;
    }

    const modal = window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;

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

    const openCreateModal = (prefill = '') => {
        if (!modal) {
            return;
        }
        if (errorBox) {
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
        }
        newName.value = prefill;
        if (newPhone) {
            newPhone.value = '';
        }
        if (newWeb) {
            newWeb.value = '';
        }
        if (newCity) {
            newCity.value = '';
        }
        if (newState) {
            newState.value = '';
        }
        if (newNote) {
            newNote.value = '';
        }
        modal.show();
        window.setTimeout(() => newName.focus(), 50);
    };

    const appendCreateOption = (term) => {
        if (!term) {
            return;
        }

        const addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'list-group-item list-group-item-action text-primary fw-semibold';
        addButton.innerHTML = `<i class="fas fa-plus me-2"></i>Add "${term}"`;
        addButton.addEventListener('click', () => {
            hideSuggestions();
            openCreateModal(term);
        });

        box.appendChild(addButton);
    };

    const renderSuggestions = (items, term) => {
        box.innerHTML = '';
        let hasExactMatch = false;

        if (Array.isArray(items) && items.length > 0) {
            items.forEach((item) => {
                const itemName = (item.name || '').toString();
                if (itemName.toLowerCase() === term.toLowerCase()) {
                    hasExactMatch = true;
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';

                const name = document.createElement('div');
                name.className = 'fw-semibold';
                name.textContent = itemName;
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
        }

        if (!hasExactMatch) {
            appendCreateOption(term);
        }

        if (box.children.length === 0) {
            hideSuggestions();
            return;
        }

        box.classList.remove('d-none');
    };

    const searchCompanies = debounce(async () => {
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
            renderSuggestions(items, q);
        } catch (_error) {
            hideSuggestions();
        }
    }, 180);

    const saveCompany = async () => {
        const name = newName.value.trim();
        if (name === '') {
            if (errorBox) {
                errorBox.textContent = 'Company name is required.';
                errorBox.classList.remove('d-none');
            }
            return;
        }

        if (errorBox) {
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
        }

        saveBtn.disabled = true;
        const originalLabel = saveBtn.textContent;
        saveBtn.textContent = 'Saving...';

        const csrfInput = document.querySelector('input[name="csrf_token"]');
        const csrfToken = csrfInput ? csrfInput.value : '';

        try {
            const payload = new URLSearchParams({
                csrf_token: csrfToken,
                name,
                phone: newPhone ? newPhone.value.trim() : '',
                web_address: newWeb ? newWeb.value.trim() : '',
                city: newCity ? newCity.value.trim() : '',
                state: newState ? newState.value.trim() : '',
                note: newNote ? newNote.value.trim() : '',
            });

            const response = await fetch(createUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: payload.toString(),
            });

            const result = await response.json();
            if (result && result.csrf_token && csrfInput) {
                csrfInput.value = result.csrf_token;
            }
            if (!response.ok || !result || !result.ok || !result.company) {
                const message = result && result.message ? result.message : 'Unable to save company.';
                throw new Error(message);
            }

            hiddenId.value = String(result.company.id || '');
            input.value = (result.company.name || '').toString();
            hideSuggestions();
            if (modal) {
                modal.hide();
            }
        } catch (error) {
            if (errorBox) {
                errorBox.textContent = error instanceof Error ? error.message : 'Unable to save company.';
                errorBox.classList.remove('d-none');
            }
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = originalLabel;
        }
    };

    input.addEventListener('input', () => {
        hiddenId.value = '';
        searchCompanies();
    });

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (hiddenId.value === '' && input.value.trim() === '') {
                hideSuggestions();
            }
        }, 120);
    });

    if (addBtn) {
        addBtn.addEventListener('click', () => {
            openCreateModal(input.value.trim());
        });
    }

    saveBtn.addEventListener('click', saveCompany);

    newName.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            saveCompany();
        }
    });

    document.addEventListener('click', (event) => {
        if (!box.contains(event.target) && event.target !== input) {
            hideSuggestions();
        }
    });
});
