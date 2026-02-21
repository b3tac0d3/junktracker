window.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('photosSelectAll');
    const countEl = document.getElementById('photoSelectedCount');
    const items = Array.from(document.querySelectorAll('.photo-select-item'));

    if (!selectAll || !countEl || items.length === 0) {
        return;
    }

    const updateCount = () => {
        const checkedCount = items.filter((item) => item.checked).length;
        countEl.textContent = String(checkedCount);

        if (checkedCount === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }

        if (checkedCount === items.length) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
            return;
        }

        selectAll.checked = false;
        selectAll.indeterminate = true;
    };

    selectAll.addEventListener('change', () => {
        items.forEach((item) => {
            item.checked = selectAll.checked;
        });
        updateCount();
    });

    items.forEach((item) => {
        item.addEventListener('change', updateCount);
    });

    updateCount();
});

