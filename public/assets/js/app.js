window.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.25s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 250);
        }, 5000);
    });
});
