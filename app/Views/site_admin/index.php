<?php
    $businesses = is_array($businesses ?? null) ? $businesses : [];
    $query = trim((string) ($query ?? ''));
    $status = in_array(($status ?? 'active'), ['active', 'inactive', 'all'], true) ? (string) $status : 'active';
    $currentBusinessId = (int) ($currentBusinessId ?? current_business_id());
    $currentBusiness = is_array($currentBusiness ?? null) ? $currentBusiness : null;
    $businessTableReady = !empty($businessTableReady);
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Site Admin</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Site Admin</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-success" href="<?= url('/site-admin/businesses/new') ?>">
                <i class="fas fa-plus me-1"></i>
                Add Business
            </a>
            <a class="btn btn-outline-primary" href="<?= url('/admin') ?>">Open Admin Workspace</a>
            <a class="btn btn-primary" href="<?= url('/site-admin/support') ?>">Open Support Queue</a>
        </div>
    </div>

    <?php if ($success = flash('success')): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash('error')): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$businessTableReady): ?>
        <div class="alert alert-warning">
            `businesses` table is not available yet. Run the latest migration bundle first.
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-circle-check me-1"></i>
            Active Workspace
        </div>
        <div class="card-body">
            <div class="text-muted small">Current Business</div>
            <div class="h5 mb-1"><?= e((string) ($currentBusiness['name'] ?? 'Not selected')) ?></div>
            <div class="text-muted small mb-3">
                ID #<?= e((string) ($currentBusiness['id'] ?? $currentBusinessId)) ?>
            </div>
            <p class="text-muted small mb-0">Use “Work Inside” on a business below to switch context for users, jobs, tasks, and notifications.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-diagram-project me-1"></i>
            Company Directory
        </div>
        <div class="card-body">
            <form class="row g-2 align-items-end mb-3" method="get" action="<?= url('/site-admin') ?>">
                <div class="col-md-6">
                    <label class="form-label" for="q">Search</label>
                    <input class="form-control" id="q" name="q" type="text" value="<?= e($query) ?>" placeholder="Search by name or email..." />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2 mobile-two-col-buttons">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="<?= url('/site-admin') ?>">Clear</a>
                </div>
            </form>

            <?php if (empty($businesses)): ?>
                <div class="text-muted">No businesses found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Status</th>
                                <th>Users</th>
                                <th>Jobs</th>
                                <th>Updated</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($businesses as $business): ?>
                                <?php
                                    $businessId = (int) ($business['id'] ?? 0);
                                    $isCurrent = $businessId > 0 && $businessId === $currentBusinessId;
                                    $isActive = (int) ($business['is_active'] ?? 0) === 1;
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) ($business['name'] ?? ('Business #' . $businessId))) ?></div>
                                        <div class="small text-muted">
                                            ID #<?= e((string) $businessId) ?>
                                            <?php if (!empty($business['legal_name'])): ?>
                                                &middot; <?= e((string) ($business['legal_name'] ?? '')) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isCurrent): ?>
                                            <span class="badge bg-success">Current Workspace</span>
                                        <?php elseif ($isActive): ?>
                                            <span class="badge bg-secondary">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string) ((int) ($business['users_count'] ?? 0))) ?></td>
                                    <td><?= e((string) ((int) ($business['jobs_count'] ?? 0))) ?></td>
                                    <td><?= e(format_datetime($business['updated_at'] ?? null)) ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 mobile-two-col-buttons">
                                            <a class="btn btn-outline-primary btn-sm" href="<?= url('/site-admin/businesses/' . $businessId) ?>">
                                                View Profile
                                            </a>
                                            <?php if ($isActive): ?>
                                                <form method="post" action="<?= url('/site-admin/switch-business') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="business_id" value="<?= e((string) $businessId) ?>" />
                                                    <input type="hidden" name="next" value="/admin" />
                                                    <button class="btn btn-sm <?= $isCurrent ? 'btn-outline-success' : 'btn-primary' ?>" type="submit">
                                                        <?= $isCurrent ? 'Current' : 'Work Inside' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
