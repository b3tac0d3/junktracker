window.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.25s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 250);
        }, 5000);
    });

    const largeViewport = window.matchMedia('(min-width: 992px)');
    const filterCards = Array.from(document.querySelectorAll('.index-card')).filter((card) => {
        const header = card.querySelector(':scope > .index-card-header');
        if (!header) {
            return false;
        }

        return header.textContent.toLowerCase().includes('filters');
    });

    const setFilterState = (entry, expanded) => {
        entry.expanded = expanded;
        entry.body.style.display = expanded ? '' : 'none';
        entry.toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        entry.toggle.innerHTML = expanded
            ? '<i class="fas fa-chevron-up me-1"></i>Hide'
            : '<i class="fas fa-chevron-down me-1"></i>Show';
    };

    const responsiveEntries = filterCards.map((card, index) => {
        const header = card.querySelector(':scope > .index-card-header');
        const body = card.querySelector(':scope > .card-body');
        if (!header || !body) {
            return null;
        }

        header.classList.add('d-flex', 'align-items-center', 'justify-content-between');
        if (!header.id) {
            header.id = `responsive-filter-header-${index + 1}`;
        }

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'btn btn-outline-secondary btn-sm d-lg-none';
        toggle.setAttribute('aria-controls', `responsive-filter-body-${index + 1}`);
        body.id = `responsive-filter-body-${index + 1}`;

        const entry = { card, header, body, toggle, expanded: false };
        setFilterState(entry, false);

        toggle.addEventListener('click', () => {
            setFilterState(entry, !entry.expanded);
        });

        header.appendChild(toggle);
        return entry;
    }).filter(Boolean);

    const applyResponsiveFilterMode = () => {
        responsiveEntries.forEach((entry) => {
            if (largeViewport.matches) {
                entry.body.style.display = '';
                entry.toggle.classList.add('d-none');
                entry.toggle.setAttribute('aria-expanded', 'true');
                return;
            }

            entry.toggle.classList.remove('d-none');
            setFilterState(entry, !!entry.expanded);
        });
    };

    if (responsiveEntries.length > 0) {
        applyResponsiveFilterMode();
        largeViewport.addEventListener('change', applyResponsiveFilterMode);
    }
});
