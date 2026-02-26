(function () {
    const dataNode = document.getElementById('snapshotChartData');
    if (!dataNode || typeof Chart === 'undefined') {
        return;
    }

    let payload = {};
    try {
        payload = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
        payload = {};
    }

    const comparison = payload.comparison || {};
    const expense = payload.expenses || {};

    const comparisonCanvas = document.getElementById('snapshotComparisonChart');
    if (comparisonCanvas && Array.isArray(comparison.labels) && comparison.labels.length > 0) {
        const gross = Array.isArray(comparison.gross) ? comparison.gross : [];
        const expenses = Array.isArray(comparison.expenses) ? comparison.expenses : [];
        const net = Array.isArray(comparison.net) ? comparison.net : [];

        new Chart(comparisonCanvas, {
            type: 'bar',
            data: {
                labels: comparison.labels,
                datasets: [
                    {
                        label: 'Gross',
                        backgroundColor: 'rgba(54, 162, 235, 0.75)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        data: gross,
                    },
                    {
                        label: 'Expenses',
                        backgroundColor: 'rgba(255, 159, 64, 0.75)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1,
                        data: expenses,
                    },
                    {
                        label: 'Net',
                        backgroundColor: 'rgba(75, 192, 192, 0.75)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1,
                        data: net,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [
                        {
                            ticks: {
                                callback: function (value) {
                                    return '$' + Number(value || 0).toLocaleString();
                                },
                            },
                        },
                    ],
                },
                tooltips: {
                    callbacks: {
                        label: function (tooltipItem, data) {
                            const dataset = data.datasets[tooltipItem.datasetIndex];
                            const raw = dataset.data[tooltipItem.index] || 0;
                            return dataset.label + ': $' + Number(raw).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        },
                    },
                },
            },
        });
    }

    const expenseCanvas = document.getElementById('snapshotExpenseChart');
    if (expenseCanvas && Array.isArray(expense.labels) && expense.labels.length > 0) {
        const values = Array.isArray(expense.values) ? expense.values : [];
        const palette = [
            '#2563eb', '#14b8a6', '#f59e0b', '#ef4444', '#6366f1',
            '#0ea5e9', '#22c55e', '#a855f7', '#f97316', '#64748b',
            '#06b6d4', '#84cc16',
        ];

        new Chart(expenseCanvas, {
            type: 'doughnut',
            data: {
                labels: expense.labels,
                datasets: [
                    {
                        data: values,
                        backgroundColor: expense.labels.map(function (_item, index) {
                            return palette[index % palette.length];
                        }),
                        borderWidth: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom',
                },
                tooltips: {
                    callbacks: {
                        label: function (tooltipItem, data) {
                            const label = data.labels[tooltipItem.index] || 'Category';
                            const value = (data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index] || 0);
                            return label + ': $' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        },
                    },
                },
            },
        });
    }
})();
