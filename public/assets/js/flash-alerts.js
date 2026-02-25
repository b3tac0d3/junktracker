document.addEventListener('DOMContentLoaded', () => {
    const alerts = Array.from(document.querySelectorAll('.alert'));
    if (alerts.length === 0) {
        return;
    }

    alerts.forEach((alertEl) => {
        const autoDismiss = String(alertEl.getAttribute('data-auto-dismiss') || 'on').toLowerCase();
        if (autoDismiss === 'off' || alertEl.classList.contains('alert-persist')) {
            return;
        }

        window.setTimeout(() => {
            if (!alertEl.isConnected) {
                return;
            }

            const height = alertEl.offsetHeight;
            alertEl.style.maxHeight = `${height}px`;
            alertEl.style.overflow = 'hidden';
            alertEl.style.transition = 'opacity 0.35s ease, transform 0.35s ease, max-height 0.35s ease, margin 0.35s ease, padding 0.35s ease';

            requestAnimationFrame(() => {
                alertEl.style.opacity = '0';
                alertEl.style.transform = 'translateY(-4px)';
                alertEl.style.maxHeight = '0';
                alertEl.style.marginTop = '0';
                alertEl.style.marginBottom = '0';
                alertEl.style.paddingTop = '0';
                alertEl.style.paddingBottom = '0';
            });

            window.setTimeout(() => {
                if (alertEl.isConnected) {
                    alertEl.remove();
                }
            }, 400);
        }, 5000);
    });
});
