window.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#devBugsTable');
    if (!table || typeof simpleDatatables === 'undefined') {
        return;
    }

    // Bug board is server-filtered; keep client search off for predictable filters.
    new simpleDatatables.DataTable(table, {
        searchable: false,
        perPage: 25,
        perPageSelect: [10, 25, 50, 100],
        columns: [
            { select: 0, sort: 'desc' },
        ],
    });
});

