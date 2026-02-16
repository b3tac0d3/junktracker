window.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#timeOpenTable');
    if (!table || typeof simpleDatatables === 'undefined') {
        return;
    }

    new simpleDatatables.DataTable(table, {
        searchable: false,
        perPage: 25,
        perPageSelect: [10, 25, 50, 100],
    });
});
