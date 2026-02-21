window.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.querySelector('[data-photo-preview-modal="1"]');
    if (!modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    const triggers = Array.from(document.querySelectorAll('.js-job-photo-preview'));
    if (triggers.length === 0) {
        return;
    }

    const imageElement = modalElement.querySelector('.js-job-photo-modal-image');
    const metaElement = modalElement.querySelector('.js-job-photo-modal-meta');
    const counterElement = modalElement.querySelector('.js-job-photo-modal-counter');
    const prevButton = modalElement.querySelector('.js-job-photo-prev');
    const nextButton = modalElement.querySelector('.js-job-photo-next');
    const openButton = modalElement.querySelector('.js-job-photo-open');

    if (!imageElement || !metaElement || !counterElement || !prevButton || !nextButton || !openButton) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);
    let activeIndex = 0;

    const updateSlide = (index) => {
        if (triggers.length === 0) {
            return;
        }

        const wrappedIndex = ((index % triggers.length) + triggers.length) % triggers.length;
        activeIndex = wrappedIndex;

        const trigger = triggers[wrappedIndex];
        const src = trigger.dataset.fullSrc || trigger.getAttribute('href') || '';
        const name = trigger.dataset.filename || 'Photo';
        const meta = trigger.dataset.meta || '';

        imageElement.src = src;
        imageElement.alt = name;
        metaElement.textContent = meta;
        counterElement.textContent = `${wrappedIndex + 1} / ${triggers.length}`;
        openButton.href = src;

        const disableNav = triggers.length <= 1;
        prevButton.disabled = disableNav;
        nextButton.disabled = disableNav;
    };

    triggers.forEach((trigger, index) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            updateSlide(index);
            modal.show();
        });
    });

    prevButton.addEventListener('click', () => updateSlide(activeIndex - 1));
    nextButton.addEventListener('click', () => updateSlide(activeIndex + 1));

    modalElement.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            updateSlide(activeIndex - 1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            updateSlide(activeIndex + 1);
        }
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        imageElement.src = '';
    });
});
