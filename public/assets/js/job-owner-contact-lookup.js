window.addEventListener('DOMContentLoaded', () => {
    const ownerInput = document.querySelector('#job_owner_search');
    const ownerResults = document.querySelector('#jobOwnerResults');
    const ownerTypeInput = document.querySelector('#job_owner_type');
    const ownerIdInput = document.querySelector('#job_owner_id');
    const ownerLookupUrlInput = document.querySelector('#owner_lookup_url');
    const contactInput = document.querySelector('#contact_search');
    const contactResults = document.querySelector('#contactResults');
    const contactIdInput = document.querySelector('#contact_client_id');
    const contactLookupUrlInput = document.querySelector('#contact_lookup_url');

    if (!ownerInput || !ownerResults || !ownerTypeInput || !ownerIdInput || !ownerLookupUrlInput || !contactInput || !contactResults || !contactIdInput || !contactLookupUrlInput) {
        return;
    }

    const ownerLookupUrl = ownerLookupUrlInput.value || '';
    const contactLookupUrl = contactLookupUrlInput.value || '';

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

    const searchOwners = debounce(async () => {
        const q = ownerInput.value.trim();
        if (q.length < 2) {
            hideResults(ownerResults);
            return;
        }

        try {
            const response = await fetch(`${ownerLookupUrl}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                hideResults(ownerResults);
                return;
            }

            const items = await response.json();
            renderResults(ownerResults, items, (item) => {
                ownerInput.value = item.label || '';
                ownerTypeInput.value = item.owner_type || '';
                ownerIdInput.value = item.owner_id || '';
                hideResults(ownerResults);
            });
        } catch (error) {
            hideResults(ownerResults);
            console.error(error);
        }
    });

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
            renderResults(contactResults, items, (item) => {
                contactInput.value = item.label || '';
                contactIdInput.value = item.client_id || '';
                hideResults(contactResults);
            });
        } catch (error) {
            hideResults(contactResults);
            console.error(error);
        }
    });

    ownerInput.addEventListener('input', () => {
        ownerTypeInput.value = '';
        ownerIdInput.value = '';
        searchOwners();
    });

    contactInput.addEventListener('input', () => {
        contactIdInput.value = '';
        searchContacts();
    });

    document.addEventListener('click', (event) => {
        if (!ownerResults.contains(event.target) && event.target !== ownerInput) {
            hideResults(ownerResults);
        }
        if (!contactResults.contains(event.target) && event.target !== contactInput) {
            hideResults(contactResults);
        }
    });
});
