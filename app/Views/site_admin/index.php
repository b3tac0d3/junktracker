<?php
    $businesses = is_array($businesses ?? null) ? $businesses : [];
    $query = trim((string) ($query ?? ''));
    $status = in_array(($status ?? 'active'), ['active', 'inactive', 'all'], true) ? (string) $status : 'active';
    $activeWorkspaceId = (int) ($activeWorkspaceId ?? 0);
    $currentBusiness = is_array($currentBusiness ?? null) ? $currentBusiness : null;
    $businessTableReady = !empty($businessTableReady);
    $summary = is_array($summary ?? null) ? $summary : [];
    $recentChanges = is_array($recentChanges ?? null) ? $recentChanges : [];
    $supportSummary = is_array($supportSummary ?? null) ? $supportSummary : [];
    $recentLimit = (int) ($recentLimit ?? 10);
    $recentLimitOptions = is_array($recentLimitOptions ?? null) ? $recentLimitOptions : [10, 25, 50, 100];
    $activeFilterCount = count(array_filter([
        $query !== '',
        $status !== 'active',
    ]));
?>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Global Admin Dashboard</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('/site-admin') ?>">Site Admin</a></li>
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </div>
        <div class="d-flex gap-2 mobile-two-col-buttons">
            <a class="btn btn-success" href="<?= url('/site-admin/businesses/new') ?>">
                <i class="fas fa-plus me-1"></i>
                Add Business
            </a>
            <a class="btn btn-outline-primary" href="<?= url('/site-admin/users') ?>">Global Users</a>
            <a class="btn btn-primary" href="<?= url('/site-admin/support') ?>">Open Support Queue</a>
        </div>
    </div>

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
            <div class="text-muted small"><?= $activeWorkspaceId > 0 ? 'Current Business' : 'Context' ?></div>
            <div class="h5 mb-1"><?= e((string) ($currentBusiness['name'] ?? 'Global Site Admin Dashboard')) ?></div>
            <?php if ($activeWorkspaceId > 0): ?>
                <div class="text-muted small mb-3">ID #<?= e((string) $activeWorkspaceId) ?></div>
                <form method="post" action="<?= url('/site-admin/exit-business') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Exit Workspace</button>
                </form>
            <?php else: ?>
                <div class="text-muted small mb-0">Use “Work Inside” on a business below to open that company workspace.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <a class="text-decoration-none" href="<?= url('/site-admin') ?>">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase">Businesses</div>
                        <div class="display-6 fw-bold"><?= e((string) ((int) ($summary['business_total'] ?? 0))) ?></div>
                        <div class="small text-muted">
                            Active: <?= e((string) ((int) ($summary['business_active'] ?? 0))) ?>
                            &middot; Inactive: <?= e((string) ((int) ($summary['business_inactive'] ?? 0))) ?>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <a class="text-decoration-none" href="<?= url('/site-admin/users') ?>">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase">Users</div>
                        <div class="display-6 fw-bold"><?= e((string) ((int) ($summary['users_total'] ?? 0))) ?></div>
                        <div class="small text-muted">Global admins: <?= e((string) ((int) ($summary['global_admins_total'] ?? 0))) ?></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <a class="text-decoration-none" href="<?= url('/site-admin/support?status=all') ?>">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase">Support Queue</div>
                        <div class="display-6 fw-bold"><?= e((string) ((int) ($supportSummary['total_count'] ?? 0))) ?></div>
                        <div class="small text-muted">
                            Open: <?= e((string) (((int) ($supportSummary['unopened_count'] ?? 0)) + ((int) ($supportSummary['pending_count'] ?? 0)) + ((int) ($supportSummary['working_count'] ?? 0)))) ?>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <a class="text-decoration-none" href="<?= url('/site-admin/users?invite=pending') ?>">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase">Pending Invites</div>
                        <div class="display-6 fw-bold"><?= e((string) ((int) ($summary['pending_invites'] ?? 0))) ?></div>
                        <div class="small text-muted">Global + company accounts</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="fas fa-clock-rotate-left me-1"></i> Recent Changes</span>
            <div class="d-flex align-items-center gap-2">
                <form class="d-flex align-items-center gap-2" method="get" action="<?= url('/site-admin') ?>">
                    <input type="hidden" name="q" value="<?= e($query) ?>" />
                    <input type="hidden" name="status" value="<?= e($status) ?>" />
                    <label class="small text-muted mb-0" for="recent_limit">Rows</label>
                    <select class="form-select form-select-sm w-auto" id="recent_limit" name="recent_limit" onchange="this.form.submit()">
                        <?php foreach ($recentLimitOptions as $limitOption): ?>
                            <?php $limitOption = (int) $limitOption; ?>
                            <option value="<?= e((string) $limitOption) ?>" <?= $recentLimit === $limitOption ? 'selected' : '' ?>>
                                <?= e((string) $limitOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <a class="small" href="<?= url('/admin/audit') ?>">Open Audit Log</a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($recentChanges)): ?>
                <div class="text-muted">No recent changes recorded.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentChanges as $change): ?>
                        <?php
                            $actionKey = trim((string) ($change['action_key'] ?? 'event'));
                            $actionLabel = ucwords(str_replace('_', ' ', $actionKey));
                            $summaryText = trim((string) ($change['summary'] ?? ''));
                            $actorName = trim((string) ($change['actor_name'] ?? 'System'));
                            $entityTable = trim((string) ($change['entity_table'] ?? ''));
                            $entityId = isset($change['entity_id']) ? (int) $change['entity_id'] : 0;
                        ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div class="fw-semibold"><?= e($summaryText !== '' ? $summaryText : $actionLabel) ?></div>
                                <div class="small text-muted"><?= e(format_datetime($change['created_at'] ?? null)) ?></div>
                            </div>
                            <div class="small text-muted">
                                <?= e($actorName !== '' ? $actorName : 'System') ?>
                                <?php if ($entityTable !== ''): ?>
                                    &middot; <?= e($entityTable) ?><?= $entityId > 0 ? ' #' . e((string) $entityId) : '' ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3 jt-filter-card">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div class="d-flex align-items-center">
                <i class="fas fa-filter me-2 text-primary"></i>
                <span class="fw-semibold">Company Filters</span>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge bg-primary ms-2 rounded-pill"><?= e((string) $activeFilterCount) ?> active</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get" action="<?= url('/site-admin') ?>">
                <input type="hidden" name="recent_limit" value="<?= e((string) $recentLimit) ?>" />
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="q">Search</label>
                    <input class="form-control" id="q" name="q" type="text" value="<?= e($query) ?>" placeholder="Search by name or email..." />
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                    </select>
                </div>
                <div class="col-12 col-lg-3 d-flex gap-2 mobile-two-col-buttons">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="<?= url('/site-admin') ?>">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-diagram-project me-1"></i>
            Company Directory
        </div>
        <div class="card-body">
            <?php if (empty($businesses)): ?>
                <div class="text-muted">No businesses found.</div>
            <?php else: ?>
                <div class="d-none d-md-block table-responsive">
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
                                    $isCurrent = $businessId > 0 && $businessId === $activeWorkspaceId;
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
                                                    <input type="hidden" name="next" value="/" />
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

                <div class="d-grid gap-3 d-md-none">
                    <?php foreach ($businesses as $business): ?>
                        <?php
                            $businessId = (int) ($business['id'] ?? 0);
                            $isCurrent = $businessId > 0 && $businessId === $activeWorkspaceId;
                            $isActive = (int) ($business['is_active'] ?? 0) === 1;
                        ?>
                        <div class="card-list-item p-3">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                <div class="fw-semibold"><?= e((string) ($business['name'] ?? ('Business #' . $businessId))) ?></div>
                                <?php if ($isCurrent): ?>
                                    <span class="badge bg-success">Current</span>
                                <?php elseif ($isActive): ?>
                                    <span class="badge bg-secondary">Available</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="card-list-field-label">ID</div>
                                    <div class="card-list-field-value">#<?= e((string) $businessId) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="card-list-field-label">Users</div>
                                    <div class="card-list-field-value"><?= e((string) ((int) ($business['users_count'] ?? 0))) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="card-list-field-label">Jobs</div>
                                    <div class="card-list-field-value"><?= e((string) ((int) ($business['jobs_count'] ?? 0))) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="card-list-field-label">Updated</div>
                                    <div class="card-list-field-value small"><?= e(format_datetime($business['updated_at'] ?? null)) ?></div>
                                </div>
                            </div>
                            <?php if (!empty($business['legal_name'])): ?>
                                <div class="small text-muted mb-3"><?= e((string) ($business['legal_name'] ?? '')) ?></div>
                            <?php endif; ?>
                            <div class="d-grid gap-2">
                                <a class="btn btn-outline-primary btn-sm w-100" href="<?= url('/site-admin/businesses/' . $businessId) ?>">View Profile</a>
                                <?php if ($isActive): ?>
                                    <form method="post" action="<?= url('/site-admin/switch-business') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="business_id" value="<?= e((string) $businessId) ?>" />
                                        <input type="hidden" name="next" value="/" />
                                        <button class="btn btn-sm <?= $isCurrent ? 'btn-outline-success' : 'btn-primary' ?> w-100" type="submit">
                                            <?= $isCurrent ? 'Current Workspace' : 'Work Inside' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
