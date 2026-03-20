<?php
$client = is_array($client ?? null) ? $client : [];
$financial = is_array($financial ?? null) ? $financial : [];
$jobStatusSummary = is_array($jobStatusSummary ?? null) ? $jobStatusSummary : [];
$jobs = is_array($jobs ?? null) ? $jobs : [];
$sales = is_array($sales ?? null) ? $sales : [];
$purchases = is_array($purchases ?? null) ? $purchases : [];
$contacts = is_array($contacts ?? null) ? $contacts : [];

$formatDateValue = static function (?string $value): string {
    if (function_exists('format_date')) {
        return format_date($value);
    }

    if ($value === null || trim($value) === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '—';
    }

    return date('m/d/Y', $timestamp);
};

$displayName = trim(((string) ($client['first_name'] ?? '')) . ' ' . ((string) ($client['last_name'] ?? '')));
if ($displayName === '') {
    $displayName = trim((string) ($client['company_name'] ?? ''));
}
if ($displayName === '') {
    $displayName = 'Client #' . (string) ((int) ($client['id'] ?? 0));
}

$addressStreet = implode(', ', array_filter([
    trim((string) ($client['address_line1'] ?? '')),
    trim((string) ($client['address_line2'] ?? '')),
], static fn (string $value): bool => $value !== ''));
$addressRegion = implode(', ', array_filter([
    trim((string) ($client['city'] ?? '')),
    trim((string) ($client['state'] ?? '')),
    trim((string) ($client['postal_code'] ?? '')),
], static fn (string $value): bool => $value !== ''));
if ($addressStreet === '' && $addressRegion === '') {
    $addressStreet = '—';
}

$primaryPhone = trim((string) ($client['phone'] ?? ''));
$secondaryPhone = trim((string) ($client['secondary_phone'] ?? ''));
$primaryNote = trim((string) ($client['primary_note'] ?? ''));

$canTextRaw = $client['can_text'] ?? null;
$canTextLabel = $canTextRaw === null ? 'Not Set' : (((int) $canTextRaw) === 1 ? 'Yes' : 'No');
$canTextClass = $canTextRaw === null ? 'text-flag-neutral' : ((((int) $canTextRaw) === 1) ? 'text-flag-yes' : 'text-flag-no');

$secondaryCanTextRaw = $client['secondary_can_text'] ?? null;
$secondaryCanTextLabel = $secondaryCanTextRaw === null ? 'Not Set' : (((int) $secondaryCanTextRaw) === 1 ? 'Yes' : 'No');
$secondaryCanTextClass = $secondaryCanTextRaw === null ? 'text-flag-neutral' : ((((int) $secondaryCanTextRaw) === 1) ? 'text-flag-yes' : 'text-flag-no');
$clientStatus = strtolower(trim((string) ($client['status'] ?? 'active')));
$isInactive = $clientStatus === 'inactive' || (array_key_exists('is_active', $client) && (int) ($client['is_active'] ?? 1) === 0);
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Client Details</h1>
        <p class="muted"><?= e($displayName) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isInactive): ?>
            <span class="badge text-bg-secondary align-self-center">Deactivated</span>
        <?php endif; ?>
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h me-2"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="<?= e(url('/clients/' . (string) ((int) ($client['id'] ?? 0)) . '/edit')) ?>">
                        <i class="fas fa-pen me-2"></i>Edit Client
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/jobs/create') . '?client_id=' . (string) ((int) ($client['id'] ?? 0))) ?>">
                        <i class="fas fa-briefcase me-2"></i>Add Job
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/purchases/create') . '?client_id=' . (string) ((int) ($client['id'] ?? 0))) ?>">
                        <i class="fas fa-cart-arrow-down me-2"></i>Add Purchase
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/tasks/create') . '?client_id=' . (string) ((int) ($client['id'] ?? 0))) ?>">
                        <i class="fas fa-list-check me-2"></i>Add Task
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('/clients/' . (string) ((int) ($client['id'] ?? 0)) . '/contacts/create')) ?>">
                        <i class="fas fa-phone-volume me-2"></i>Add Contact
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('/clients/' . (string) ((int) ($client['id'] ?? 0)) . '/deactivate')) ?>" onsubmit="return confirm('Deactivate this client?');">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit" <?= $isInactive ? 'disabled' : '' ?>>
                            <i class="fas fa-user-slash me-2"></i><?= $isInactive ? 'Already Deactivated' : 'Deactivate Client' ?>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/clients')) ?>">Back to Clients</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-address-card me-2"></i>Client Details</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields">
            <div class="record-field">
                <span class="record-label">Client ID</span>
                <span class="record-value"><?= e((string) ((int) ($client['id'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Name</span>
                <span class="record-value"><?= e($displayName) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Phone</span>
                <span class="record-value">
                    <?= e(format_phone($primaryPhone)) ?>
                    <?php if ($primaryPhone !== ''): ?>
                        <span class="text-flag <?= e($canTextClass) ?>">Text: <?= e($canTextLabel) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="record-field">
                <span class="record-label">Secondary Phone</span>
                <span class="record-value">
                    <?= e(format_phone($secondaryPhone)) ?>
                    <?php if ($secondaryPhone !== ''): ?>
                        <span class="text-flag <?= e($secondaryCanTextClass) ?>">Text: <?= e($secondaryCanTextLabel) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="record-field">
                <span class="record-label">Email</span>
                <span class="record-value"><?= e(trim((string) ($client['email'] ?? '')) ?: '—') ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Full Address</span>
                <span class="record-value record-value-stack">
                    <span><?= e($addressStreet) ?></span>
                    <?php if ($addressRegion !== ''): ?>
                        <span><?= e($addressRegion) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="record-field">
                <span class="record-label">Primary Note</span>
                <span class="record-value"><?= e($primaryNote !== '' ? $primaryNote : '—') ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-chart-line me-2"></i>Lifetime Financial Summary</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3 mb-3">
            <div class="record-field">
                <span class="record-label">Gross Income</span>
                <span class="record-value">$<?= e(number_format((float) ($financial['gross_income'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Net Income</span>
                <span class="record-value">$<?= e(number_format((float) ($financial['net_income'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Expenses</span>
                <span class="record-value">$<?= e(number_format((float) ($financial['expenses'] ?? 0), 2)) ?></span>
            </div>
        </div>
        <div class="record-row-fields record-row-fields-3">
            <div class="record-field">
                <span class="record-label">Sales Gross</span>
                <span class="record-value">$<?= e(number_format((float) ($financial['sales_gross'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Sales Net</span>
                <span class="record-value">$<?= e(number_format((float) ($financial['sales_net'] ?? 0), 2)) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Purchase Spend</span>
                <span class="record-value">$<?= e(number_format((float) ($financial['purchase_spend'] ?? 0), 2)) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-briefcase me-2"></i>Jobs</strong>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-5 mb-3">
            <div class="record-field">
                <span class="record-label">Prospect</span>
                <span class="record-value"><?= e((string) ((int) ($jobStatusSummary['prospect'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Pending</span>
                <span class="record-value"><?= e((string) ((int) ($jobStatusSummary['pending'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Active</span>
                <span class="record-value"><?= e((string) ((int) ($jobStatusSummary['active'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Complete</span>
                <span class="record-value"><?= e((string) ((int) ($jobStatusSummary['complete'] ?? 0))) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Cancelled</span>
                <span class="record-value"><?= e((string) ((int) ($jobStatusSummary['cancelled'] ?? 0))) ?></span>
            </div>
        </div>

        <?php if ($jobs === []): ?>
            <div class="record-empty">No jobs for this client yet.</div>
        <?php else: ?>
            <div class="simple-list-table">
                <?php foreach ($jobs as $job): ?>
                    <a class="simple-list-row simple-list-row-link" href="<?= e(url('/jobs/' . (string) ((int) ($job['id'] ?? 0)))) ?>">
                        <div class="simple-list-title"><?= e(trim((string) ($job['title'] ?? '')) !== '' ? (string) $job['title'] : ('Job #' . (string) ((int) ($job['id'] ?? 0)))) ?></div>
                        <div class="simple-list-meta">
                            <span>ID #<?= e((string) ((int) ($job['id'] ?? 0))) ?></span>
                            <span class="text-capitalize"><?= e((string) ($job['status'] ?? 'pending')) ?></span>
                            <span><?= e(format_datetime((string) ($job['scheduled_start_at'] ?? null))) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-sack-dollar me-2"></i>Sales</strong>
    </div>
    <div class="card-body">
        <?php if ($sales === []): ?>
            <div class="record-empty">No sales linked to this client yet.</div>
        <?php else: ?>
            <div class="simple-list-table">
                <?php foreach ($sales as $sale): ?>
                    <?php
                    if (!is_array($sale)) {
                        continue;
                    }
                    $saleId = (int) ($sale['id'] ?? 0);
                    if ($saleId <= 0) {
                        continue;
                    }
                    $saleName = trim((string) ($sale['name'] ?? ''));
                    if ($saleName === '') {
                        $saleName = 'Sale #' . (string) $saleId;
                    }
                    $saleType = trim((string) ($sale['sale_type'] ?? ''));
                    $saleTypeLabel = $saleType === '' ? 'Sale' : ucwords(str_replace('_', ' ', strtolower($saleType)));
                    $saleDate = $formatDateValue((string) ($sale['sale_date'] ?? null));
                    ?>
                    <a class="simple-list-row simple-list-row-link" href="<?= e(url('/sales/' . (string) $saleId)) ?>">
                        <div class="simple-list-title"><?= e($saleName) ?></div>
                        <div class="simple-list-meta">
                            <span><?= e($saleTypeLabel) ?></span>
                            <span><?= e($saleDate) ?></span>
                            <span>Gross $<?= e(number_format((float) ($sale['gross_amount'] ?? 0), 2)) ?></span>
                            <span>Net $<?= e(number_format((float) ($sale['net_amount'] ?? 0), 2)) ?></span>
                            <?php if (((int) ($sale['job_id'] ?? 0)) > 0): ?>
                                <span>Job: <?= e(trim((string) ($sale['job_title'] ?? '')) ?: ('Job #' . (string) ((int) ($sale['job_id'] ?? 0)))) ?></span>
                            <?php elseif (((int) ($sale['purchase_id'] ?? 0)) > 0): ?>
                                <span>Purchase: <?= e(trim((string) ($sale['purchase_title'] ?? '')) ?: ('Purchase #' . (string) ((int) ($sale['purchase_id'] ?? 0)))) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-cart-arrow-down me-2"></i>Purchases</strong>
    </div>
    <div class="card-body">
        <?php if ($purchases === []): ?>
            <div class="record-empty">No purchases linked to this client yet.</div>
        <?php else: ?>
            <div class="simple-list-table">
                <?php foreach ($purchases as $purchase): ?>
                    <?php
                    if (!is_array($purchase)) {
                        continue;
                    }
                    $purchaseId = (int) ($purchase['id'] ?? 0);
                    if ($purchaseId <= 0) {
                        continue;
                    }
                    $purchaseTitle = trim((string) ($purchase['title'] ?? ''));
                    if ($purchaseTitle === '') {
                        $purchaseTitle = 'Purchase #' . (string) $purchaseId;
                    }
                    $purchaseStatus = trim((string) ($purchase['status'] ?? ''));
                    $purchaseStatusLabel = $purchaseStatus === '' ? '—' : ucwords(str_replace('_', ' ', strtolower($purchaseStatus)));
                    $purchaseDate = $formatDateValue((string) ($purchase['purchase_date'] ?? ($purchase['contact_date'] ?? null)));
                    ?>
                    <a class="simple-list-row simple-list-row-link" href="<?= e(url('/purchases/' . (string) $purchaseId)) ?>">
                        <div class="simple-list-title"><?= e($purchaseTitle) ?></div>
                        <div class="simple-list-meta">
                            <span><?= e($purchaseStatusLabel) ?></span>
                            <span><?= e($purchaseDate) ?></span>
                            <span>Price $<?= e(number_format((float) ($purchase['purchase_price'] ?? 0), 2)) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card index-card">
    <div class="card-header index-card-header">
        <strong><i class="fas fa-phone-volume me-2"></i>Client Contact Log</strong>
    </div>
    <div class="card-body">
        <?php if ($contacts === []): ?>
            <div class="record-empty">No contact records yet.</div>
        <?php else: ?>
            <div class="simple-list-table">
                <?php foreach ($contacts as $contact): ?>
                    <?php
                    if (!is_array($contact)) {
                        continue;
                    }
                    $contactType = trim((string) ($contact['contact_type'] ?? ''));
                    $contactTypeLabel = $contactType === '' ? 'Contact' : ucwords(str_replace('_', ' ', strtolower($contactType)));
                    $contactedAt = format_datetime((string) ($contact['contacted_at'] ?? null));
                    $contactBy = trim((string) ($contact['created_by_name'] ?? ''));
                    $contactNote = trim((string) ($contact['note'] ?? ''));
                    ?>
                    <div class="simple-list-row">
                        <div class="simple-list-title"><?= e($contactTypeLabel) ?></div>
                        <div class="simple-list-meta">
                            <span><?= e($contactedAt) ?></span>
                            <?php if ($contactBy !== ''): ?>
                                <span>By <?= e($contactBy) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($contactNote !== ''): ?>
                            <div class="mt-1 muted"><?= e($contactNote) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
