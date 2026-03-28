<div class="reports-shell">
    <div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
        <div>
            <h1>Reports</h1>
            <p class="muted mb-0">Choose a report for the current business</p>
        </div>
    </div>

    <div class="admin-nav-grid row g-3">
        <div class="col-12 col-sm-6 col-xl-4">
            <a class="admin-nav-tile" href="<?= e(url('/reports/income')) ?>">
                <div class="admin-nav-tile-icon admin-nav-tile-icon--invoice-types" aria-hidden="true">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="admin-nav-tile-title">Income report</div>
                <p class="admin-nav-tile-desc">Period totals, chart, margin by job, service, sales, expenses, and purchases.</p>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <a class="admin-nav-tile" href="<?= e(url('/reports/jobs')) ?>">
                <div class="admin-nav-tile-icon admin-nav-tile-icon--employees" aria-hidden="true">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="admin-nav-tile-title">Jobs (within range)</div>
                <p class="admin-nav-tile-desc">Jobs with activity in the selected date range.</p>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <a class="admin-nav-tile" href="<?= e(url('/reports/sales')) ?>">
                <div class="admin-nav-tile-icon admin-nav-tile-icon--users" aria-hidden="true">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="admin-nav-tile-title">Sales (within range)</div>
                <p class="admin-nav-tile-desc">Individual sales recorded in the selected date range.</p>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <a class="admin-nav-tile" href="<?= e(url('/reports/purchases')) ?>">
                <div class="admin-nav-tile-icon admin-nav-tile-icon--form-values" aria-hidden="true">
                    <i class="fas fa-cart-arrow-down"></i>
                </div>
                <div class="admin-nav-tile-title">Purchases (within range)</div>
                <p class="admin-nav-tile-desc">Purchases with a date in the selected date range.</p>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <a class="admin-nav-tile" href="<?= e(url('/reports/expenses')) ?>">
                <div class="admin-nav-tile-icon admin-nav-tile-icon--business" aria-hidden="true">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="admin-nav-tile-title">Expenses (within range)</div>
                <p class="admin-nav-tile-desc">Totals and category breakdown for the selected period.</p>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <a class="admin-nav-tile" href="<?= e(url('/reports/service')) ?>">
                <div class="admin-nav-tile-icon admin-nav-tile-icon--invoice-types" aria-hidden="true">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="admin-nav-tile-title">Service (within range)</div>
                <p class="admin-nav-tile-desc">Invoices with an issue or created date in the selected range.</p>
            </a>
        </div>
    </div>
</div>
