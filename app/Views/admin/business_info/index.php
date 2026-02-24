<?php
$business = is_array($business ?? null) ? $business : [];
$businessId = (int) ($businessId ?? current_business_id());
$isSiteAdmin = !empty($isSiteAdmin);
$logoPath = trim((string) ($business['logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? url('/' . ltrim($logoPath, '/')) : '';
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Business Info</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Business Info</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <?php if ($isSiteAdmin && $businessId > 0): ?>
                <a class="btn btn-primary" href="<?= url('/site-admin/businesses/' . $businessId . '/edit') ?>">
                    <i class="fas fa-pen me-1"></i>
                    Edit Business Profile
                </a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('/admin') ?>">Back to Admin</a>
        </div>
    </div>

    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-building me-1"></i>Current Business Profile</span>
            <span class="badge bg-secondary">ID #<?= e((string) $businessId) ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($business)): ?>
                <div class="text-muted">No business profile found for the active workspace.</div>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Business Name</div>
                        <div class="fw-semibold"><?= e((string) ($business['name'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Legal Name</div>
                        <div class="fw-semibold"><?= e((string) ($business['legal_name'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Email</div>
                        <div class="fw-semibold"><?= e((string) ($business['email'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Phone</div>
                        <div class="fw-semibold"><?= e((string) ($business['phone'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Website</div>
                        <div class="fw-semibold"><?= e((string) ($business['website'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-8">
                        <div class="text-muted small">Address</div>
                        <div class="fw-semibold">
                            <?php
                                $parts = [];
                                foreach (['address_line1', 'address_line2'] as $k) {
                                    $line = trim((string) ($business[$k] ?? ''));
                                    if ($line !== '') {
                                        $parts[] = $line;
                                    }
                                }
                                $cityStateZip = trim(
                                    (string) ($business['city'] ?? '')
                                    . ((string) ($business['city'] ?? '') !== '' && (string) ($business['state'] ?? '') !== '' ? ', ' : '')
                                    . (string) ($business['state'] ?? '')
                                    . ((string) ($business['postal_code'] ?? '') !== '' ? ' ' . (string) $business['postal_code'] : '')
                                );
                                if ($cityStateZip !== '') {
                                    $parts[] = $cityStateZip;
                                }
                                if (!empty($business['country'])) {
                                    $parts[] = (string) $business['country'];
                                }
                            ?>
                            <?= e(!empty($parts) ? implode(' | ', $parts) : '—') ?>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Tax ID</div>
                        <div class="fw-semibold"><?= e((string) (($business['tax_id'] ?? '') !== '' ? $business['tax_id'] : '—')) ?></div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Default Tax Rate</div>
                        <div class="fw-semibold"><?= e(number_format((float) ($business['invoice_default_tax_rate'] ?? 0), 2) . '%') ?></div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted small">Timezone</div>
                        <div class="fw-semibold"><?= e((string) (($business['timezone'] ?? '') !== '' ? $business['timezone'] : '—')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Logo</div>
                        <?php if ($logoUrl !== ''): ?>
                            <img src="<?= e($logoUrl) ?>" alt="Business logo" style="max-height:72px; max-width:220px; border:1px solid #dbe4f0; border-radius:8px; padding:4px; background:#fff;" />
                        <?php else: ?>
                            <div class="fw-semibold">—</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
