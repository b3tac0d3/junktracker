<?php
    $business = is_array($business ?? null) ? $business : [];
    $businessId = (int) ($business['id'] ?? 0);
    $isCurrentWorkspace = !empty($isCurrentWorkspace);
    $isActive = (int) ($business['is_active'] ?? 0) === 1;
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Company Profile</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/site-admin') ?>">Site Admin</a></li>
                <li class="breadcrumb-item active"><?= e((string) ($business['name'] ?? ('Business #' . $businessId))) ?></li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-warning" href="<?= url('/site-admin/businesses/' . $businessId . '/edit') ?>">
                <i class="fas fa-pen me-1"></i>
                Edit Business
            </a>
            <?php if ($isActive): ?>
                <form method="post" action="<?= url('/site-admin/switch-business') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="business_id" value="<?= e((string) $businessId) ?>" />
                    <input type="hidden" name="next" value="/admin" />
                    <button class="btn <?= $isCurrentWorkspace ? 'btn-outline-success' : 'btn-primary' ?>" type="submit">
                        <?= $isCurrentWorkspace ? 'Current Workspace' : 'Work Inside' ?>
                    </button>
                </form>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= url('/site-admin') ?>">Back to Site Admin</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-building me-1"></i>Business Details</span>
            <span class="badge <?= $isActive ? 'bg-success' : 'bg-danger' ?>">
                <?= $isActive ? 'Active' : 'Inactive' ?>
            </span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Business ID</div>
                    <div class="fw-semibold">#<?= e((string) $businessId) ?></div>
                </div>
                <div class="col-md-8">
                    <div class="text-muted small">Business Name</div>
                    <div class="fw-semibold"><?= e((string) ($business['name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Legal Name</div>
                    <div class="fw-semibold"><?= e((string) ($business['legal_name'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Email</div>
                    <div class="fw-semibold"><?= e((string) ($business['email'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Phone</div>
                    <div class="fw-semibold"><?= e((string) ($business['phone'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Website</div>
                    <div class="fw-semibold"><?= e((string) ($business['website'] ?? '—')) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Users</div>
                    <div class="fw-semibold"><?= e((string) ((int) ($business['users_count'] ?? 0))) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Active Users</div>
                    <div class="fw-semibold"><?= e((string) ((int) ($business['active_users_count'] ?? 0))) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Jobs</div>
                    <div class="fw-semibold"><?= e((string) ((int) ($business['jobs_count'] ?? 0))) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Created</div>
                    <div class="fw-semibold"><?= e(format_datetime($business['created_at'] ?? null)) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Updated</div>
                    <div class="fw-semibold"><?= e(format_datetime($business['updated_at'] ?? null)) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
