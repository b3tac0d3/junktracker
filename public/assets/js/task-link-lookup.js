window.addEventListener('DOMContentLoaded', () => {
    const linkType = document.getElementById('link_type');
    const linkInput = document.getElementById('link_search');
    const linkHidden = document.getElementById('link_id');
    const linkBox = document.getElementById('task_link_suggestions');
    const lookupUrlInput = document.getElementById('task_link_lookup_url');

    if (!linkType || !linkInput || !linkHidden || !linkBox || !lookupUrlInput) {
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
        linkBox.classList.add('d-none');
        linkBox.innerHTML = '';
    };

    const setLinkInputState = () => {
        const type = (linkType.value || 'general').toLowerCase();
        const disabled = type === 'general';
        linkInput.disabled = disabled;
        if (disabled) {
            linkInput.value = '';
            linkHidden.value = '';
            hideSuggestions();
        }
    };

    const renderSuggestions = (items) => {
        linkBox.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            hideSuggestions();
            return;
        }

        items.forEach((item) => {
            const id = (item.id || '').toString();
            if (id === '') {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';

            const label = (item.label || '').toString();
            const title = document.createElement('div');
            title.className = 'fw-semibold';
            title.textContent = label !== '' ? label : `#${id}`;
            button.appendChild(title);

            const metaText = (item.meta || '').toString().trim();
            if (metaText !== '') {
                const meta = document.createElement('small');
                meta.className = 'text-muted';
                meta.textContent = metaText;
                button.appendChild(meta);
            }

            button.addEventListener('click', () => {
                linkInput.value = label !== '' ? label : `#${id}`;
                linkHidden.value = id;
                hideSuggestions();
            });

            linkBox.appendChild(button);
        });

        if (linkBox.children.length === 0) {
            hideSuggestions();
            return;
        }

        linkBox.classList.remove('d-none');
    };

    const search = debounce(async () => {
        const type = (linkType.value || 'general').toLowerCase();
        const q = linkInput.value.trim();
        if (type === 'general' || q.length < 2) {
            hideSuggestions();
            return;
        }

        try {
            const response = await fetch(
                `${lookupUrl}?type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`,
                { headers: { Accept: 'application/json' } },
            );
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

    linkType.addEventListener('change', () => {
        linkHidden.value = '';
        setLinkInputState();
    });

    linkInput.addEventListener('input', () => {
        linkHidden.value = '';
        search();
    });

    document.addEventListener('click', (event) => {
        if (!linkBox.contains(event.target) && event.target !== linkInput) {
            hideSuggestions();
        }
    });

    setLinkInputState();
});
