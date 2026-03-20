<?php
$query = trim((string) ($query ?? ''));
$isGlobalSiteAdminContext = (bool) ($isGlobalSiteAdminContext ?? false);
$results = is_array($results ?? null) ? $results : [];
$totals = is_array($totals ?? null) ? $totals : [];

$clients = is_array($results['clients'] ?? null) ? $results['clients'] : [];
$jobs = is_array($results['jobs'] ?? null) ? $results['jobs'] : [];
$tasks = is_array($results['tasks'] ?? null) ? $results['tasks'] : [];
$sales = is_array($results['sales'] ?? null) ? $results['sales'] : [];
$purchases = is_array($results['purchases'] ?? null) ? $results['purchases'] : [];
$billing = is_array($results['billing'] ?? null) ? $results['billing'] : [];
$expenses = is_array($results['expenses'] ?? null) ? $results['expenses'] : [];
$timeEntries = is_array($results['time_entries'] ?? null) ? $results['time_entries'] : [];
$businesses = is_array($results['businesses'] ?? null) ? $results['businesses'] : [];
$siteAdminUsers = is_array($results['site_admin_users'] ?? null) ? $results['site_admin_users'] : [];

$displayName = static function (array $row, string $fallback): string {
    $full = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
    if ($full !== '') {
        return $full;
    }

    $company = trim((string) ($row['company_name'] ?? ''));
    if ($company !== '') {
        return $company;
    }

    $email = trim((string) ($row['email'] ?? ''));
    if ($email !== '') {
        return $email;
    }

    return $fallback;
};

$money = static function (mixed $value): string {
    return '$' . number_format((float) $value, 2);
};

$date = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '—';
    }

    $time = strtotime($raw);
    if ($time === false) {
        return '—';
    }

    return date('m/d/Y', $time);
};

$totalMatches = 0;
foreach ($totals as $value) {
    $totalMatches += max(0, (int) $value);
}

$queryEncoded = rawurlencode($query);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Search</h1>
        <p class="muted">Global search across your current workspace.</p>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-magnifying-glass me-2"></i>Search Everything</strong>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('/search')) ?>" class="row g-3 align-items-end">
            <div class="col-12 col-lg-9">
                <label class="form-label fw-semibold" for="global-search">Search</label>
                <input
                    id="global-search"
                    class="form-control"
                    name="global_q"
                    value="<?= e($query) ?>"
                    placeholder="Search clients, jobs, tasks, sales, billing, expenses..."
                    autocomplete="off"
                />
            </div>
            <div class="col-12 col-lg-3 d-grid d-lg-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Search</button>
                <a class="btn btn-outline-secondary flex-fill" href="<?= e(url('/search')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<?php if ($query === ''): ?>
    <section class="card index-card">
        <div class="card-body">
            <div class="record-empty">Enter a search term to find matching records.</div>
        </div>
    </section>
<?php elseif ($totalMatches <= 0): ?>
    <section class="card index-card">
        <div class="card-body">
            <div class="record-empty">No results found for "<?= e($query) ?>".</div>
        </div>
    </section>
<?php else: ?>
    <section class="card index-card mb-3">
        <div class="card-body py-2">
            <span class="small muted">Found <?= e((string) $totalMatches) ?> match(es) for "<?= e($query) ?>".</span>
        </div>
    </section>

    <div class="row g-3">
        <?php if ($isGlobalSiteAdminContext): ?>
            <div class="col-12 col-xl-6">
                <section class="card index-card h-100">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-building me-2"></i>Businesses</strong>
                        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/site-admin/businesses')) ?>">Open Businesses</a>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($businesses === []): ?>
                            <div class="record-empty">No businesses matched.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($businesses as $business): ?>
                                    <?php $businessId = (int) ($business['id'] ?? 0); ?>
                                    <article class="record-row-simple">
                                        <div class="record-row-main">
                                            <h3 class="record-title-simple"><?= e(trim((string) ($business['name'] ?? '')) ?: ('Business #' . $businessId)) ?></h3>
                                            <div class="record-subline small muted"><?= e(trim((string) ($business['legal_name'] ?? '')) ?: '—') ?></div>
                                        </div>
                                        <div class="record-row-fields record-row-fields-compact mt-1">
                                            <div class="record-field">
                                                <span class="record-label">Business ID</span>
                                                <span class="record-value"><?= e((string) $businessId) ?></span>
                                            </div>
                                            <div class="record-field">
                                                <span class="record-label">Status</span>
                                                <span class="record-value"><?= (int) ($business['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span>
                                            </div>
                                            <div class="record-field">
                                                <span class="record-label">Type</span>
                                                <span class="record-value">Workspace</span>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card index-card h-100">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-user-shield me-2"></i>Site Admin Users</strong>
                        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/admin/users?q=' . $queryEncoded)) ?>">Open Users</a>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($siteAdminUsers === []): ?>
                            <div class="record-empty">No site admin users matched.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($siteAdminUsers as $user): ?>
                                    <?php $userId = (int) ($user['id'] ?? 0); ?>
                                    <article class="record-row-simple">
                                        <a class="record-row-link" href="<?= e(url('/admin/users/' . (string) $userId . '/edit')) ?>">
                                            <div class="record-row-main">
                                                <h3 class="record-title-simple"><?= e($displayName($user, 'User #' . $userId)) ?></h3>
                                                <div class="record-subline small muted"><?= e(trim((string) ($user['email'] ?? '')) ?: '—') ?></div>
                                            </div>
                                            <div class="record-row-fields record-row-fields-compact">
                                                <div class="record-field">
                                                    <span class="record-label">User ID</span>
                                                    <span class="record-value"><?= e((string) $userId) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Role</span>
                                                    <span class="record-value"><?= e((string) ($user['role'] ?? 'site_admin')) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Scope</span>
                                                    <span class="record-value">Global</span>
                                                </div>
                                            </div>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        <?php else: ?>
            <div class="col-12 col-xl-6">
                <section class="card index-card h-100">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-users me-2"></i>Clients</strong>
                        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/clients?q=' . $queryEncoded)) ?>">Open Clients</a>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($clients === []): ?>
                            <div class="record-empty">No clients matched.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($clients as $client): ?>
                                    <?php $clientId = (int) ($client['id'] ?? 0); ?>
                                    <article class="record-row-simple">
                                        <a class="record-row-link" href="<?= e(url('/clients/' . (string) $clientId)) ?>">
                                            <div class="record-row-main">
                                                <h3 class="record-title-simple"><?= e($displayName($client, 'Client #' . $clientId)) ?></h3>
                                            </div>
                                            <div class="record-row-fields record-row-fields-compact">
                                                <div class="record-field">
                                                    <span class="record-label">Client ID</span>
                                                    <span class="record-value"><?= e((string) $clientId) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Phone</span>
                                                    <span class="record-value"><?= e(trim((string) ($client['phone'] ?? '')) ?: '—') ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">City</span>
                                                    <span class="record-value"><?= e(trim((string) ($client['city'] ?? '')) ?: '—') ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card index-card h-100">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-briefcase me-2"></i>Jobs</strong>
                        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/jobs?q=' . $queryEncoded)) ?>">Open Jobs</a>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($jobs === []): ?>
                            <div class="record-empty">No jobs matched.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($jobs as $job): ?>
                                    <?php $jobId = (int) ($job['id'] ?? 0); ?>
                                    <article class="record-row-simple">
                                        <a class="record-row-link" href="<?= e(url('/jobs/' . (string) $jobId)) ?>">
                                            <div class="record-row-main">
                                                <h3 class="record-title-simple"><?= e(trim((string) ($job['title'] ?? '')) ?: ('Job #' . $jobId)) ?></h3>
                                            </div>
                                            <div class="record-row-fields record-row-fields-compact">
                                                <div class="record-field">
                                                    <span class="record-label">Status</span>
                                                    <span class="record-value"><?= e(ucfirst((string) ($job['status'] ?? ''))) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Client</span>
                                                    <span class="record-value"><?= e(trim((string) ($job['client_name'] ?? '')) ?: '—') ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Scheduled</span>
                                                    <span class="record-value"><?= e(format_datetime((string) ($job['scheduled_start_at'] ?? ''))) ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card index-card h-100">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-list-check me-2"></i>Tasks</strong>
                        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/tasks?q=' . $queryEncoded)) ?>">Open Tasks</a>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($tasks === []): ?>
                            <div class="record-empty">No tasks matched.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($tasks as $task): ?>
                                    <?php
                                    $taskId = (int) ($task['id'] ?? 0);
                                    $taskStatus = strtolower(trim((string) ($task['status'] ?? 'open')));
                                    $isClosed = $taskStatus === 'closed';
                                    ?>
                                    <article class="record-row-simple">
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <a class="record-row-link flex-grow-1" href="<?= e(url('/tasks/' . (string) $taskId)) ?>">
                                                <div class="record-row-main">
                                                    <h3 class="record-title-simple<?= $isClosed ? ' text-decoration-line-through text-muted' : '' ?>"><?= e(trim((string) ($task['title'] ?? '')) ?: ('Task #' . $taskId)) ?></h3>
                                                </div>
                                                <div class="record-row-fields record-row-fields-compact">
                                                    <div class="record-field">
                                                        <span class="record-label">Status</span>
                                                        <span class="record-value"><?= e(ucfirst((string) ($task['status'] ?? 'open'))) ?></span>
                                                    </div>
                                                    <div class="record-field">
                                                        <span class="record-label">Owner</span>
                                                        <span class="record-value"><?= e(trim((string) ($task['owner_name'] ?? '')) ?: '—') ?></span>
                                                    </div>
                                                    <div class="record-field">
                                                        <span class="record-label">Due</span>
                                                        <span class="record-value"><?= e(format_datetime((string) ($task['due_at'] ?? ''))) ?></span>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card index-card h-100">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-sack-dollar me-2"></i>Sales</strong>
                        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/sales?q=' . $queryEncoded)) ?>">Open Sales</a>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($sales === []): ?>
                            <div class="record-empty">No sales matched.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($sales as $sale): ?>
                                    <?php $saleId = (int) ($sale['id'] ?? 0); ?>
                                    <article class="record-row-simple">
                                        <a class="record-row-link" href="<?= e(url('/sales/' . (string) $saleId)) ?>">
                                            <div class="record-row-main">
                                                <h3 class="record-title-simple"><?= e(trim((string) ($sale['name'] ?? '')) ?: ('Sale #' . $saleId)) ?></h3>
                                            </div>
                                            <div class="record-row-fields record-row-fields-compact">
                                                <div class="record-field">
                                                    <span class="record-label">Type</span>
                                                    <span class="record-value"><?= e((string) ($sale['sale_type'] ?? '—')) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Gross</span>
                                                    <span class="record-value"><?= e($money($sale['gross_amount'] ?? 0)) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Date</span>
                                                    <span class="record-value"><?= e($date($sale['sale_date'] ?? '')) ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card index-card h-100">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-cart-arrow-down me-2"></i>Purchasing</strong>
                        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/purchases?q=' . $queryEncoded)) ?>">Open Purchasing</a>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($purchases === []): ?>
                            <div class="record-empty">No purchases matched.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($purchases as $purchase): ?>
                                    <?php $purchaseId = (int) ($purchase['id'] ?? 0); ?>
                                    <article class="record-row-simple">
                                        <a class="record-row-link" href="<?= e(url('/purchases/' . (string) $purchaseId)) ?>">
                                            <div class="record-row-main">
                                                <h3 class="record-title-simple"><?= e(trim((string) ($purchase['title'] ?? '')) ?: ('Purchase #' . $purchaseId)) ?></h3>
                                            </div>
                                            <div class="record-row-fields record-row-fields-compact">
                                                <div class="record-field">
                                                    <span class="record-label">Status</span>
                                                    <span class="record-value"><?= e(ucwords(str_replace('_', ' ', (string) ($purchase['status'] ?? '')))) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Client</span>
                                                    <span class="record-value"><?= e(trim((string) ($purchase['client_name'] ?? '')) ?: '—') ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Purchase</span>
                                                    <span class="record-value"><?= e($date($purchase['purchase_date'] ?? '')) ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card index-card h-100">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-file-invoice-dollar me-2"></i>Billing</strong>
                        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/billing?q=' . $queryEncoded)) ?>">Open Billing</a>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($billing === []): ?>
                            <div class="record-empty">No billing records matched.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($billing as $invoice): ?>
                                    <?php $invoiceId = (int) ($invoice['id'] ?? 0); ?>
                                    <article class="record-row-simple">
                                        <a class="record-row-link" href="<?= e(url('/billing/' . (string) $invoiceId)) ?>">
                                            <div class="record-row-main">
                                                <h3 class="record-title-simple"><?= e(trim((string) ($invoice['invoice_number'] ?? '')) ?: ('Invoice #' . $invoiceId)) ?></h3>
                                            </div>
                                            <div class="record-row-fields record-row-fields-compact">
                                                <div class="record-field">
                                                    <span class="record-label">Type</span>
                                                    <span class="record-value"><?= e(ucfirst((string) ($invoice['type'] ?? 'invoice'))) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Status</span>
                                                    <span class="record-value"><?= e(ucwords(str_replace('_', ' ', (string) ($invoice['status'] ?? '')))) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Total</span>
                                                    <span class="record-value"><?= e($money($invoice['total'] ?? 0)) ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card index-card h-100">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-receipt me-2"></i>Expenses</strong>
                        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/expenses?q=' . $queryEncoded)) ?>">Open Expenses</a>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($expenses === []): ?>
                            <div class="record-empty">No expenses matched.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($expenses as $expense): ?>
                                    <?php $expenseId = (int) ($expense['id'] ?? 0); ?>
                                    <article class="record-row-simple">
                                        <a class="record-row-link" href="<?= e(url('/expenses/' . (string) $expenseId)) ?>">
                                            <div class="record-row-main">
                                                <h3 class="record-title-simple"><?= e(trim((string) ($expense['name'] ?? '')) ?: ('Expense #' . $expenseId)) ?></h3>
                                            </div>
                                            <div class="record-row-fields record-row-fields-compact">
                                                <div class="record-field">
                                                    <span class="record-label">Category</span>
                                                    <span class="record-value"><?= e(trim((string) ($expense['category'] ?? '')) ?: '—') ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Amount</span>
                                                    <span class="record-value"><?= e($money($expense['amount'] ?? 0)) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Date</span>
                                                    <span class="record-value"><?= e($date($expense['expense_date'] ?? '')) ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card index-card h-100">
                    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-clock me-2"></i>Time Entries</strong>
                        <a class="small fw-semibold text-decoration-none" href="<?= e(url('/time-tracking?q=' . $queryEncoded)) ?>">Open Time Tracking</a>
                    </div>
                    <div class="card-body p-2 p-lg-3">
                        <?php if ($timeEntries === []): ?>
                            <div class="record-empty">No time entries matched.</div>
                        <?php else: ?>
                            <div class="record-list-simple">
                                <?php foreach ($timeEntries as $entry): ?>
                                    <?php $entryId = (int) ($entry['id'] ?? 0); ?>
                                    <article class="record-row-simple">
                                        <a class="record-row-link" href="<?= e(url('/time-tracking/' . (string) $entryId)) ?>">
                                            <div class="record-row-main">
                                                <h3 class="record-title-simple"><?= e(trim((string) ($entry['employee_name'] ?? '')) ?: ('Entry #' . $entryId)) ?></h3>
                                                <div class="record-subline small muted"><?= e(trim((string) ($entry['job_title'] ?? '')) ?: 'Non-Job Time') ?></div>
                                            </div>
                                            <div class="record-row-fields record-row-fields-compact">
                                                <div class="record-field">
                                                    <span class="record-label">Clock In</span>
                                                    <span class="record-value"><?= e(format_datetime((string) ($entry['clock_in_at'] ?? ''))) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Clock Out</span>
                                                    <span class="record-value"><?= e(format_datetime((string) ($entry['clock_out_at'] ?? ''))) ?></span>
                                                </div>
                                                <div class="record-field">
                                                    <span class="record-label">Duration</span>
                                                    <span class="record-value"><?= e(((int) ($entry['duration_minutes'] ?? 0)) > 0 ? ((string) ((int) ($entry['duration_minutes'] ?? 0)) . ' min') : '—') ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
