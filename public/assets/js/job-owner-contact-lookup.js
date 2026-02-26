window.addEventListener('DOMContentLoaded', () => {
    const ownerLookupUrlInput = document.querySelector('#owner_lookup_url');
    const ownerTypeInput = document.querySelector('#job_owner_type');
    const ownerIdInput = document.querySelector('#job_owner_id');

    const ownerFields = [
        {
            type: 'client',
            searchInput: document.querySelector('#owner_client_search'),
            idInput: document.querySelector('#owner_client_id'),
            results: document.querySelector('#jobOwnerClientResults'),
        },
        {
            type: 'estate',
            searchInput: document.querySelector('#owner_estate_search'),
            idInput: document.querySelector('#owner_estate_id'),
            results: document.querySelector('#jobOwnerEstateResults'),
        },
        {
            type: 'company',
            searchInput: document.querySelector('#owner_company_search'),
            idInput: document.querySelector('#owner_company_id'),
            results: document.querySelector('#jobOwnerCompanyResults'),
        },
    ].filter((field) => field.searchInput && field.idInput && field.results);

    const contactInput = document.querySelector('#contact_search');
    const contactResults = document.querySelector('#contactResults');
    const contactIdInput = document.querySelector('#contact_client_id');
    const contactLookupUrlInput = document.querySelector('#contact_lookup_url');
    const contactCreateUrlInput = document.querySelector('#contact_create_url');

    const form = contactInput ? contactInput.closest('form') : null;
    const csrfInput = form ? form.querySelector('input[name="csrf_token"]') : null;

    const contactModalEl = document.getElementById('addJobContactClientModal');
    const contactSaveBtn = document.getElementById('save_new_job_contact_client_btn');
    const contactErrorBox = document.getElementById('job_contact_client_create_error');
    const contactNewFirst = document.getElementById('job_new_contact_first_name');
    const contactNewLast = document.getElementById('job_new_contact_last_name');
    const contactNewPhone = document.getElementById('job_new_contact_phone');
    const contactNewEmail = document.getElementById('job_new_contact_email');

    if (
        !ownerLookupUrlInput
        || !ownerTypeInput
        || !ownerIdInput
        || ownerFields.length === 0
        || !contactInput
        || !contactResults
        || !contactIdInput
        || !contactLookupUrlInput
    ) {
        return;
    }

    const ownerLookupUrl = ownerLookupUrlInput.value || '';
    const contactLookupUrl = contactLookupUrlInput.value || '';
    const contactCreateUrl = contactCreateUrlInput ? (contactCreateUrlInput.value || '').trim() : '';
    const contactModal = window.bootstrap && contactModalEl ? new window.bootstrap.Modal(contactModalEl) : null;
    const canQuickCreateContact = Boolean(
        contactCreateUrl
        && contactModal
        && contactSaveBtn
        && contactErrorBox
        && contactNewFirst
        && contactNewLast
        && contactNewPhone
        && contactNewEmail
    );

    const debounce = (fn, delay = 200) => {
        let timer = null;
        return (...args) => {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(() => fn(...args), delay);
        };
    };

    const hideResults = (box) => {
        box.classList.add('d-none');
        box.innerHTML = '';
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

    const openContactCreateModal = (prefill = '') => {
        if (!canQuickCreateContact) {
            return;
        }

        const parsed = parseName(prefill);
        contactErrorBox.classList.add('d-none');
        contactErrorBox.textContent = '';
        contactNewFirst.value = parsed.firstName;
        contactNewLast.value = parsed.lastName;
        contactNewPhone.value = '';
        contactNewEmail.value = '';

        contactModal.show();
        window.setTimeout(() => {
            contactNewFirst.focus();
        }, 50);
    };

    const renderResults = (box, items, onSelect) => {
        if (!Array.isArray(items) || items.length === 0) {
            hideResults(box);
            return;
        }

        box.innerHTML = '';
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';

            const label = document.createElement('div');
            label.className = 'fw-semibold';
            label.textContent = item.label || '';
            button.appendChild(label);

            if (item.sub_label) {
                const meta = document.createElement('small');
                meta.className = 'text-muted';
                meta.textContent = item.sub_label;
                button.appendChild(meta);
            }

            button.addEventListener('click', () => onSelect(item));
            box.appendChild(button);
        });

        box.classList.remove('d-none');
    };

    const setPrimaryOwner = (ownerType, ownerId) => {
        ownerTypeInput.value = ownerType || '';
        ownerIdInput.value = ownerId ? String(ownerId) : '';
    };

    const firstSelectedOwner = () => {
        for (const field of ownerFields) {
            const id = Number.parseInt(field.idInput.value || '', 10);
            if (Number.isInteger(id) && id > 0) {
                return { type: field.type, id };
            }
        }
        return null;
    };

    const refreshPrimaryOwner = () => {
        const currentType = ownerTypeInput.value;
        const currentId = Number.parseInt(ownerIdInput.value || '', 10);
        if (currentType && Number.isInteger(currentId) && currentId > 0) {
            const matchingField = ownerFields.find((field) => field.type === currentType);
            if (matchingField) {
                const matchingId = Number.parseInt(matchingField.idInput.value || '', 10);
                if (Number.isInteger(matchingId) && matchingId === currentId) {
                    return;
                }
            }
        }

        const selected = firstSelectedOwner();
        if (selected) {
            setPrimaryOwner(selected.type, selected.id);
            return;
        }

        setPrimaryOwner('', '');
    };

    ownerFields.forEach((field) => {
        const searchOwners = debounce(async () => {
            const q = field.searchInput.value.trim();
            if (q.length < 2) {
                hideResults(field.results);
                return;
            }

            try {
                const response = await fetch(`${ownerLookupUrl}?type=${encodeURIComponent(field.type)}&q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    hideResults(field.results);
                    return;
                }

                const items = await response.json();
                renderResults(field.results, items, (item) => {
                    const itemId = item.owner_id || '';
                    field.searchInput.value = item.label || '';
                    field.idInput.value = itemId;
                    setPrimaryOwner(field.type, itemId);
                    hideResults(field.results);
                });
            } catch (error) {
                hideResults(field.results);
                console.error(error);
            }
        });

        field.searchInput.addEventListener('input', () => {
            field.idInput.value = '';
            refreshPrimaryOwner();
            searchOwners();
        });
    });

    const renderContactResults = (items, term) => {
        contactResults.innerHTML = '';

        if (Array.isArray(items) && items.length > 0) {
            renderResults(contactResults, items, (item) => {
                contactInput.value = item.label || '';
                contactIdInput.value = item.client_id || '';
                hideResults(contactResults);
            });
            return;
        }

        if (canQuickCreateContact && term !== '') {
            const createButton = document.createElement('button');
            createButton.type = 'button';
            createButton.className = 'list-group-item list-group-item-action text-primary fw-semibold';
            createButton.innerHTML = `<i class="fas fa-plus me-2"></i>Add "${term}"`;
            createButton.addEventListener('click', () => {
                hideResults(contactResults);
                openContactCreateModal(term);
            });
            contactResults.appendChild(createButton);
            contactResults.classList.remove('d-none');
            return;
        }

        hideResults(contactResults);
    };

    const searchContacts = debounce(async () => {
        const q = contactInput.value.trim();
        if (q.length < 2) {
            hideResults(contactResults);
            return;
        }

        try {
            const response = await fetch(`${contactLookupUrl}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                hideResults(contactResults);
                return;
            }

            const items = await response.json();
            renderContactResults(items, q);
        } catch (error) {
            hideResults(contactResults);
            console.error(error);
        }
    });

    contactInput.addEventListener('input', () => {
        contactIdInput.value = '';
        searchContacts();
    });

    document.addEventListener('click', (event) => {
        ownerFields.forEach((field) => {
            if (!field.results.contains(event.target) && event.target !== field.searchInput) {
                hideResults(field.results);
            }
        });

        if (!contactResults.contains(event.target) && event.target !== contactInput) {
            hideResults(contactResults);
        }
    });

    const saveContact = async () => {
        if (!canQuickCreateContact) {
            return;
        }

        const firstName = contactNewFirst.value.trim();
        const lastName = contactNewLast.value.trim();
        const phone = contactNewPhone.value.trim();
        const email = contactNewEmail.value.trim();

        if (firstName === '' && lastName === '') {
            contactErrorBox.textContent = 'Provide at least a first or last name.';
            contactErrorBox.classList.remove('d-none');
            return;
        }

        contactErrorBox.classList.add('d-none');
        contactErrorBox.textContent = '';

        const csrfToken = csrfInput ? csrfInput.value : '';
        const originalLabel = contactSaveBtn.textContent;
        contactSaveBtn.disabled = true;
        contactSaveBtn.textContent = 'Saving...';

        try {
            const payload = new URLSearchParams({
                csrf_token: csrfToken,
                first_name: firstName,
                last_name: lastName,
                phone,
                email,
            });

            const response = await fetch(contactCreateUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: payload.toString(),
            });

            const result = await response.json();
            if (result && result.csrf_token) {
                document.querySelectorAll('input[name="csrf_token"]').forEach((input) => {
                    input.value = result.csrf_token;
                });
            }

            if (!response.ok || !result || !result.ok || !result.client) {
                const message = result && result.message ? result.message : 'Unable to save client.';
                throw new Error(message);
            }

            contactIdInput.value = String(result.client.id || '');
            contactInput.value = (result.client.label || '').toString();
            hideResults(contactResults);
            contactModal.hide();
        } catch (error) {
            contactErrorBox.textContent = error instanceof Error ? error.message : 'Unable to save client.';
            contactErrorBox.classList.remove('d-none');
        } finally {
            contactSaveBtn.disabled = false;
            contactSaveBtn.textContent = originalLabel;
        }
    };

    if (contactSaveBtn) {
        contactSaveBtn.addEventListener('click', saveContact);
    }

    [contactNewFirst, contactNewLast, contactNewPhone, contactNewEmail].forEach((input) => {
        if (!input) {
            return;
        }
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveContact();
            }
        });
    });

    refreshPrimaryOwner();
});
