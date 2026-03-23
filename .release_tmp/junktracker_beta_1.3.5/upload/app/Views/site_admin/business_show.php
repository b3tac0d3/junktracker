<?php
$business = is_array($business ?? null) ? $business : [];
$businessId = (int) ($business['id'] ?? 0);
$isActive = (int) ($business['is_active'] ?? 1) === 1;
$name = trim((string) ($business['name'] ?? ''));
$legal = trim((string) ($business['legal_name'] ?? ''));
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1><?= e($name !== '' ? $name : ('Business #' . (string) $businessId)) ?></h1>
        <p class="muted mb-0"><?= e($legal !== '' ? $legal : 'Company Profile') ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="<?= e(url('/site-admin/businesses/' . (string) $businessId . '/edit')) ?>">
            <i class="fas fa-pen me-2"></i>Edit Company
        </a>
        <form method="post" action="<?= e(url('/site-admin/businesses/' . (string) $businessId . '/toggle-active')) ?>" onsubmit="return confirm('Are you sure?');">
            <?= csrf_field() ?>
            <input type="hidden" name="set_active" value="<?= $isActive ? '0' : '1' ?>">
            <button class="btn <?= $isActive ? 'btn-outline-danger' : 'btn-success' ?>" type="submit">
                <?= e($isActive ? 'Deactivate Company' : 'Reactivate Company') ?>
            </button>
        </form>
        <a class="btn btn-outline-secondary" href="<?= e(url('/site-admin/businesses')) ?>">Back to Businesses</a>
    </div>
</div>

<section class="card index-card mb-3">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-building me-2"></i>Primary Information</strong>
        <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?>">
            <?= e($isActive ? 'Active' : 'Inactive') ?>
        </span>
    </div>
    <div class="card-body">
        <div class="record-row-fields record-row-fields-3">
            <div class="record-field">
                <span class="record-label">Company Name</span>
                <span class="record-value"><?= e((string) ($business['name'] ?? '—')) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Official Name</span>
                <span class="record-value"><?= e((string) ($business['legal_name'] ?? '—')) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Email</span>
                <span class="record-value"><?= e((string) ($business['email'] ?? '—')) ?></span>
            </div>
            <div class="record-field">
                <span class="record-label">Phone</span>
                <span class="record-value"><?= e((string) ($business['phone'] ?? '—')) ?></span>
            </div>
            <div class="record-field record-field-full">
                <span class="record-label">Address</span>
                <span class="record-value-stack">
                    <span><?= e((string) ($business['address_line1'] ?? '')) ?></span>
                    <?php if (trim((string) ($business['address_line2'] ?? '')) !== ''): ?><span><?= e((string) ($business['address_line2'] ?? '')) ?></span><?php endif; ?>
                    <span>
                        <?= e(trim((string) ($business['city'] ?? ''))) ?>
                        <?= e(trim((string) ($business['state'] ?? '')) !== '' ? ', ' . trim((string) ($business['state'] ?? '')) : '') ?>
                        <?= e(trim((string) ($business['postal_code'] ?? '')) !== '' ? ' ' . trim((string) ($business['postal_code'] ?? '')) : '') ?>
                    </span>
                </span>
            </div>
        </div>
    </div>
</section>
