window.addEventListener('DOMContentLoaded', () => {
    if (typeof simpleDatatables === 'undefined') {
        return;
    }

    ['#disposalLocationsTable', '#expenseCategoriesTable'].forEach((selector) => {
        const table = document.querySelector(selector);
        if (!table) {
            return;
        }

        new simpleDatatables.DataTable(table, {
            searchable: true,
            perPage: 25,
            perPageSelect: [10, 25, 50, 100],
            columns: [
                { select: 0, sort: 'desc' },
            ],
        });
    });
});
