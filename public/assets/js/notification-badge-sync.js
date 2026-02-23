(function () {
    const navLink = document.querySelector('.nav-notification-link[data-sync-url]');
    if (!navLink) {
        return;
    }

    const syncUrl = (navLink.dataset.syncUrl || '').trim();
    if (syncUrl === '') {
        return;
    }

    const topBadge = document.querySelector('.nav-notification-badge');
    const sideBadge = document.querySelector('.nav-notification-sidebar-badge');
    const badges = [topBadge, sideBadge].filter(Boolean);
    if (badges.length === 0) {
        return;
    }

    let inFlight = false;

    const setUnread = (count) => {
        const unread = Number.isFinite(count) ? Math.max(0, Math.floor(count)) : 0;
        badges.forEach((badge) => {
            badge.textContent = String(unread);
            badge.classList.toggle('d-none', unread <= 0);
        });
    };

    const refresh = () => {
        if (inFlight) {
            return;
        }

        inFlight = true;
        const separator = syncUrl.includes('?') ? '&' : '?';
        const url = syncUrl + separator + '_ts=' + Date.now();

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Notification summary request failed');
                }
                return response.json();
            })
            .then((payload) => {
                const unread = Number(payload?.summary?.unread ?? 0);
                setUnread(unread);
            })
            .catch(() => {
                // Fail quietly; keep current badge values.
            })
            .finally(() => {
                inFlight = false;
            });
    };

    refresh();
    window.setInterval(refresh, 30000);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refresh();
        }
    });
})();
