window.addEventListener('DOMContentLoaded', () => {
    const clientInput = document.getElementById('client_search');
    const clientHidden = document.getElementById('client_id');
    const clientBox = document.getElementById('prospect_client_suggestions');
    const clientLookupInput = document.getElementById('prospect_client_lookup_url');
    const clientCreateInput = document.getElementById('prospect_client_create_url');

    const modalEl = document.getElementById('addProspectClientModal');
    const saveBtn = document.getElementById('save_new_prospect_client_btn');
    const errorBox = document.getElementById('prospect_client_create_error');

    const newFirst = document.getElementById('new_prospect_client_first_name');
    const newLast = document.getElementById('new_prospect_client_last_name');
    const newPhone = document.getElementById('new_prospect_client_phone');
    const newEmail = document.getElementById('new_prospect_client_email');

    if (!clientInput || !clientHidden || !clientBox || !clientLookupInput) {
        return;
    }

    const clientLookupUrl = clientLookupInput.value || '';
    if (!clientLookupUrl) {
        return;
    }
    const clientCreateUrl = clientCreateInput ? (clientCreateInput.value || '') : '';
    const modal = window.bootstrap && modalEl ? new window.bootstrap.Modal(modalEl) : null;
    const canQuickCreate = Boolean(
        clientCreateUrl
        && modal
        && saveBtn
        && errorBox
        && newFirst
        && newLast
        && newPhone
        && newEmail
    );

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
        clientBox.classList.add('d-none');
        clientBox.innerHTML = '';
    };

    const parseName = (value) => {
        const parts = value.trim().split(/\s+/).filter(Boolean);
        if (parts.length === 0) {
            return { firstName: '', lastName: '' };
        }
        if (parts.length === 1) {
            return { firstName: parts[0], lastName: '' };
        }
        return {
            firstName: parts.shift() || '',
            lastName: parts.join(' '),
        };
    };

    const openCreateModal = (prefill = '') => {
        if (!canQuickCreate) {
            return;
        }

        const parsed = parseName(prefill);
        if (errorBox) {
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
        }
        if (newFirst) {
            newFirst.value = parsed.firstName;
        }
        if (newLast) {
            newLast.value = parsed.lastName;
        }
        if (newPhone) {
            newPhone.value = '';
        }
        if (newEmail) {
            newEmail.value = '';
        }

        modal.show();
        window.setTimeout(() => {
            if (newFirst) {
                newFirst.focus();
            }
        }, 50);
    };

    const appendCreateOption = (term) => {
        if (!canQuickCreate || term === '') {
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
        clientBox.appendChild(addButton);
    };

    const renderSuggestions = (items, term) => {
        clientBox.innerHTML = '';
        let hasExactMatch = false;

        if (Array.isArray(items) && items.length > 0) {
            items.forEach((item) => {
                const id = (item.id || '').toString();
                if (id === '') {
                    return;
                }

                const label = (item.label || `Client #${id}`).toString();
                if (label.toLowerCase() === term.toLowerCase()) {
                    hasExactMatch = true;
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';

                const title = document.createElement('div');
                title.className = 'fw-semibold';
                title.textContent = label;
                button.appendChild(title);

                const city = (item.city || '').toString().trim();
                const state = (item.state || '').toString().trim();
                const metaText = city && state ? `${city}, ${state}` : (city || state);
                if (metaText !== '') {
                    const meta = document.createElement('small');
                    meta.className = 'text-muted';
                    meta.textContent = metaText;
                    button.appendChild(meta);
                }

                button.addEventListener('click', () => {
                    clientInput.value = label;
                    clientHidden.value = id;
                    hideSuggestions();
                });

                clientBox.appendChild(button);
            });
        }

        if (!hasExactMatch) {
            appendCreateOption(term);
        }

        if (clientBox.children.length === 0) {
            hideSuggestions();
            return;
        }

        clientBox.classList.remove('d-none');
    };

    const search = debounce(async () => {
        const q = clientInput.value.trim();
        if (q.length < 2) {
            hideSuggestions();
            return;
        }

        try {
            const response = await fetch(`${clientLookupUrl}?q=${encodeURIComponent(q)}`, {
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

    const saveClient = async () => {
        if (!canQuickCreate) {
            return;
        }

        const firstName = newFirst.value.trim();
        const lastName = newLast.value.trim();
        const phone = newPhone.value.trim();
        const email = newEmail.value.trim();
        if (firstName === '' && lastName === '') {
            errorBox.textContent = 'Provide at least a first or last name.';
            errorBox.classList.remove('d-none');
            return;
        }

        errorBox.classList.add('d-none');
        errorBox.textContent = '';

        const csrfInput = document.querySelector('input[name="csrf_token"]');
        const csrfToken = csrfInput ? csrfInput.value : '';

        const originalLabel = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const payload = new URLSearchParams({
                csrf_token: csrfToken,
                first_name: firstName,
                last_name: lastName,
                phone,
                email,
            });

            const response = await fetch(clientCreateUrl, {
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
            if (!response.ok || !result || !result.ok || !result.client) {
                const message = result && result.message ? result.message : 'Unable to save client.';
                throw new Error(message);
            }

            clientHidden.value = String(result.client.id || '');
            clientInput.value = (result.client.label || '').toString();
            hideSuggestions();
            modal.hide();
        } catch (error) {
            errorBox.textContent = error instanceof Error ? error.message : 'Unable to save client.';
            errorBox.classList.remove('d-none');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = originalLabel;
        }
    };

    clientInput.addEventListener('input', () => {
        clientHidden.value = '';
        search();
    });

    if (saveBtn) {
        saveBtn.addEventListener('click', saveClient);
    }
    [newFirst, newLast, newPhone, newEmail].forEach((input) => {
        if (!input) {
            return;
        }
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveClient();
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!clientBox.contains(event.target) && event.target !== clientInput) {
            hideSuggestions();
        }
    });
});
