<?php
$client = is_array($client ?? null) ? $client : [];
$financial = is_array($financial ?? null) ? $financial : [];
$jobStatusSummary = is_array($jobStatusSummary ?? null) ? $jobStatusSummary : [];
$jobs = is_array($jobs ?? null) ? $jobs : [];

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
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Client Details</h1>
        <p class="muted"><?= e($displayName) ?></p>
    </div>
    <div>
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
                    <?= e($primaryPhone !== '' ? $primaryPhone : '—') ?>
                    <?php if ($primaryPhone !== ''): ?>
                        <span class="text-flag <?= e($canTextClass) ?>">Text: <?= e($canTextLabel) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="record-field">
                <span class="record-label">Secondary Phone</span>
                <span class="record-value">
                    <?= e($secondaryPhone !== '' ? $secondaryPhone : '—') ?>
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
        <div class="record-row-fields record-row-fields-3">
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
                    <div class="simple-list-row">
                        <div class="simple-list-title"><?= e(trim((string) ($job['title'] ?? '')) !== '' ? (string) $job['title'] : ('Job #' . (string) ((int) ($job['id'] ?? 0)))) ?></div>
                        <div class="simple-list-meta">
                            <span>ID #<?= e((string) ((int) ($job['id'] ?? 0))) ?></span>
                            <span class="text-capitalize"><?= e((string) ($job['status'] ?? 'pending')) ?></span>
                            <span><?= e(format_datetime((string) ($job['scheduled_start_at'] ?? null))) ?></span>
                        </div>
                    </div>
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
        <div class="record-empty">Contact log module scaffolded. Entries will appear here once implemented.</div>
    </div>
</section>
