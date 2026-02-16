window.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#timeTrackingTable');
    if (!table || typeof simpleDatatables === 'undefined') {
        return;
    }

    new simpleDatatables.DataTable(table, {
        searchable: false,
        perPage: 25,
        perPageSelect: [10, 25, 50, 100],
        columns: [
            { select: 0, sort: 'desc' },
        ],
    });
});
