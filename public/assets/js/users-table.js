window.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#usersTable');
    if (!table || typeof simpleDatatables === 'undefined') {
        return;
    }

    const datatable = new simpleDatatables.DataTable(table, {
        searchable: false,
        perPage: 25,
        perPageSelect: false,
        columns: [
            { select: 0, sort: 'desc' },
        ],
    });

    const bindRows = () => {
        const root = table.closest('.datatable-container') || table;
        root.querySelectorAll('tbody tr').forEach((row) => {
            if (row.dataset.clickBound === '1') {
                return;
            }
            let href = row.dataset.href;
            if (!href) {
                const cell = row.querySelector('td[data-href]');
                href = cell ? cell.dataset.href : '';
                if (href) {
                    row.dataset.href = href;
                }
            }
            if (!href) {
                return;
            }
            row.style.cursor = 'pointer';
            row.addEventListener('click', (event) => {
                if (event.target.closest('a, button, input, label')) {
                    return;
                }
                window.location.href = href;
            });
            row.dataset.clickBound = '1';
        });
    };

    bindRows();
    datatable.on('datatable.draw', bindRows);
});
