window.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('estate-search-input');
    const list = document.getElementById('estateSearchSuggestions');
    const lookupUrlInput = document.getElementById('estate_lookup_url');

    if (!input || !list || !lookupUrlInput) {
        return;
    }

    const lookupUrl = lookupUrlInput.value;
    if (!lookupUrl) {
        return;
    }

    let timer = null;

    const clearOptions = () => {
        list.innerHTML = '';
    };

    input.addEventListener('input', () => {
        const term = input.value.trim();

        if (timer) {
            window.clearTimeout(timer);
        }

        if (term.length < 2) {
            clearOptions();
            return;
        }

        timer = window.setTimeout(async () => {
            try {
                const response = await fetch(`${lookupUrl}?q=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    clearOptions();
                    return;
                }

                const items = await response.json();
                clearOptions();

                if (!Array.isArray(items)) {
                    return;
                }

                items.slice(0, 10).forEach((item) => {
                    const option = document.createElement('option');
                    const city = (item.city || '').toString().trim();
                    const state = (item.state || '').toString().trim();
                    const location = city && state ? ` (${city}, ${state})` : '';
                    option.value = (item.name || '').toString();
                    option.label = `${(item.name || '').toString()}${location}`;
                    list.appendChild(option);
                });
            } catch (_error) {
                clearOptions();
            }
        }, 180);
    });
});
