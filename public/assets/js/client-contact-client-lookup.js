window.addEventListener('DOMContentLoaded', () => {
    const clientInput = document.getElementById('contact_client_search');
    const clientHidden = document.getElementById('contact_client_id');
    const clientBox = document.getElementById('client_contact_client_suggestions');
    const clientLookupInput = document.getElementById('client_contact_client_lookup_url');
    const taskBaseInput = document.getElementById('client_contact_task_base_url');
    const addTaskBtn = document.getElementById('add_task_for_client_btn');

    if (!clientInput || !clientHidden || !clientBox || !clientLookupInput) {
        return;
    }

    const lookupUrl = clientLookupInput.value || '';
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

    const setTaskButton = () => {
        if (!addTaskBtn) {
            return;
        }

        const taskBaseUrl = taskBaseInput ? (taskBaseInput.value || '/tasks/new') : '/tasks/new';
        const clientId = (clientHidden.value || '').trim();
        if (clientId === '') {
            addTaskBtn.classList.add('disabled');
            addTaskBtn.href = taskBaseUrl;
            return;
        }

        addTaskBtn.classList.remove('disabled');
        addTaskBtn.href = `${taskBaseUrl}?link_type=client&link_id=${encodeURIComponent(clientId)}`;
    };

    const hideSuggestions = () => {
        clientBox.classList.add('d-none');
        clientBox.innerHTML = '';
    };

    const renderSuggestions = (items) => {
        clientBox.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            hideSuggestions();
            return;
        }

        items.forEach((item) => {
            const id = (item.id || '').toString();
            if (id === '') {
                return;
            }

            const label = (item.label || `Client #${id}`).toString();
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
                setTaskButton();
            });

            clientBox.appendChild(button);
        });

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

    clientInput.addEventListener('input', () => {
        clientHidden.value = '';
        setTaskButton();
        search();
    });

    document.addEventListener('click', (event) => {
        if (!clientBox.contains(event.target) && event.target !== clientInput) {
            hideSuggestions();
        }
    });

    setTaskButton();
});
