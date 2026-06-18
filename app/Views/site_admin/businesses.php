<?php
$businesses = is_array($businesses ?? null) ? $businesses : [];
$pagination = is_array($pagination ?? null) ? $pagination : pagination_meta(1, 25, count($businesses), count($businesses));
$counts = is_array($counts ?? null) ? $counts : ['all' => (int) count($businesses), 'active' => 0, 'inactive' => 0];
$usersByBusiness = is_array($usersByBusiness ?? null) ? $usersByBusiness : [];
$status = (string) ($status ?? 'active');
$query = trim((string) ($query ?? ''));
$businessCount = (int) ($pagination['total_rows'] ?? count($businesses));
?>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-2">
    <div>
        <h1>Companies</h1>
        <p class="muted mb-0">Search companies and enter a workspace.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/site-admin')) ?>">
            <i class="fas fa-chart-line me-2"></i>Platform
        </a>
        <a class="btn btn-primary" href="<?= e(url('/site-admin/businesses/create')) ?>">
            <i class="fas fa-plus me-2"></i>Add Company
        </a>
    </div>
</div>

<div class="d-flex flex-wrap gap-3 mb-3 small">
    <div><span class="muted">Total</span> <strong><?= e((string) ((int) ($counts['all'] ?? 0))) ?></strong></div>
    <div><span class="muted">Active</span> <strong class="text-success"><?= e((string) ((int) ($counts['active'] ?? 0))) ?></strong></div>
    <div><span class="muted">Inactive</span> <strong class="text-secondary"><?= e((string) ((int) ($counts['inactive'] ?? 0))) ?></strong></div>
</div>

<section class="card index-card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('/site-admin/businesses')) ?>" class="row g-2">
            <div class="col-12 col-lg-7">
                <label class="form-label fw-semibold" for="company-quick-search">Quick Company Search</label>
                <input id="company-quick-search" type="text" name="q" class="form-control" maxlength="160" placeholder="Search by name, legal name, email, city, state..." value="<?= e($query) ?>" />
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label fw-semibold" for="company-status-filter">Status</label>
                <select id="company-status-filter" name="status" class="form-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12 col-lg-3 d-flex align-items-end justify-content-lg-end gap-2">
                <button class="btn btn-primary" type="submit">Search</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/site-admin/businesses')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card index-card mb-3">
    <div class="card-header index-card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-building me-2"></i>Companies</strong>
        <span class="small muted"><?= e((string) $businessCount) ?> shown · <?= e((string) ((int) ($counts['all'] ?? 0))) ?> total</span>
    </div>
    <div class="card-body">
        <?php
        $basePath = '/site-admin/businesses';
        require base_path('app/Views/components/index_pagination.php');
        ?>

        <div class="record-list-simple mt-3">
            <?php foreach ($businesses as $business): ?>
                <?php
                $businessId = (int) ($business['id'] ?? 0);
                $name = trim((string) ($business['name'] ?? ''));
                $legal = trim((string) ($business['legal_name'] ?? ''));
                $isActive = (int) ($business['is_active'] ?? 1) === 1;
                $userCount = (int) ($usersByBusiness[$businessId] ?? 0);
                ?>
                <div class="record-row-simple d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="record-row-main">
                        <h3 class="record-title-simple mb-0"><?= e($name !== '' ? $name : ('Business #' . (string) $businessId)) ?></h3>
                        <div class="record-subline muted">
                            <?php if ($legal !== ''): ?><span><?= e($legal) ?></span><?php endif; ?>
                            <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e($isActive ? 'Active' : 'Inactive') ?></span>
                            <span><?= e((string) $userCount) ?> user<?= $userCount === 1 ? '' : 's' ?></span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="<?= e(url('/site-admin/businesses/' . (string) $businessId)) ?>">View</a>
                        <form method="post" action="<?= e(url('/site-admin/switch-business')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="business_id" value="<?= e((string) $businessId) ?>">
                            <button class="btn btn-primary" type="submit" <?= $isActive ? '' : 'disabled' ?>>Enter Workspace</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($businesses === []): ?>
                <div class="record-empty">No companies found.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="card index-card">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="muted small">Exit the current workspace and return to global site admin context.</div>
        <form method="post" action="<?= e(url('/site-admin/exit-workspace')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary" type="submit">Clear Workspace Context</button>
        </form>
    </div>
</section>
